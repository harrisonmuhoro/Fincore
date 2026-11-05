<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Finance\FinancialPeriodGuard;
use App\Domain\Shared\AuditRecorder;
use App\Domain\Shared\OutboxPublisher;
use App\Domain\Workflow\WorkflowService;
use App\Http\Controllers\Controller;
use App\Models\WorkflowInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BudgetLedgerController extends Controller
{
    public function __construct(private readonly AuditRecorder $audit, private readonly OutboxPublisher $outbox) {}

    public function calls(Request $request): JsonResponse
    {
        return $this->respond($request, 'Budget calls retrieved.', DB::table('budget_calls')->orderByDesc('id')->paginate($this->pageSize($request)));
    }

    public function createCall(Request $request): JsonResponse
    {
        $data = $request->validate(['financial_year_id' => ['required', 'integer', 'exists:financial_years,id'], 'code' => ['required', 'string', 'max:100', 'unique:budget_calls,code'], 'name' => ['required', 'string', 'max:200'], 'submission_deadline' => ['nullable', 'date']]);
        $id = DB::table('budget_calls')->insertGetId([...$data, 'status' => 'open', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record($request, 'budget_planning', 'call.created', 'budget_call', $id);

        return $this->respond($request, 'Budget call created.', DB::table('budget_calls')->find($id), 201);
    }

    public function ceilings(Request $request, int $call): JsonResponse
    {
        abort_unless(DB::table('budget_calls')->where('id', $call)->exists(), 404);
        return $this->respond($request, 'Budget ceilings retrieved.', DB::table('budget_ceilings')->where('budget_call_id', $call)->orderBy('organization_unit_id')->paginate($this->pageSize($request)));
    }

    public function createCeiling(Request $request, int $call): JsonResponse
    {
        $data = $request->validate(['organization_unit_id' => ['required', 'integer'], 'budget_type' => ['required', 'string', 'max:40'], 'amount' => ['required', 'regex:/^\\d{1,16}(\\.\\d{1,2})?$/'], 'currency_code' => ['sometimes', 'string', 'size:3', 'exists:finance_currencies,code']]);
        abort_unless(DB::table('budget_calls')->where('id', $call)->where('status', 'open')->exists(), 409, 'Budget call is not open.');
        $id = DB::table('budget_ceilings')->insertGetId(['budget_call_id' => $call, ...$data, 'currency_code' => $data['currency_code'] ?? 'KES', 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->record($request, 'budget_planning', 'ceiling.created', 'budget_ceiling', $id);
        return $this->respond($request, 'Budget ceiling recorded.', DB::table('budget_ceilings')->find($id), 201);
    }

    public function estimates(Request $request): JsonResponse
    {
        $q = DB::table('budget_estimates')->orderByDesc('id');
        if ($request->filled('budget_call_id')) $q->where('budget_call_id', $request->integer('budget_call_id'));
        return $this->respond($request, 'Budget estimates retrieved.', $q->paginate($this->pageSize($request)));
    }

    public function createEstimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'budget_call_id' => ['required', 'integer', 'exists:budget_calls,id'], 'organization_unit_id' => ['required', 'integer'],
            'lines' => ['required', 'array', 'min:1', 'max:500'], 'lines.*.account_combination_id' => ['required', 'integer', 'exists:finance_account_combinations,id'],
            'lines.*.budget_type' => ['required', 'string', 'max:40'], 'lines.*.amount' => ['required', 'regex:/^\d{1,16}(\.\d{1,2})?$/'], 'lines.*.rationale' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless(DB::table('budget_calls')->where('id', $data['budget_call_id'])->where('status', 'open')->exists(), 409, 'The budget call is not open.');
        return DB::transaction(function () use ($request, $data): JsonResponse {
            $estimate = DB::table('budget_estimates')->where('budget_call_id', $data['budget_call_id'])->where('organization_unit_id', $data['organization_unit_id'])->lockForUpdate()->first();
            if ($estimate) {
                abort_unless($estimate->status === 'draft' || $estimate->status === 'returned', 409, 'Only a draft or returned estimate may be changed.');
                DB::table('budget_estimate_lines')->where('budget_estimate_id', $estimate->id)->delete();
                DB::table('budget_estimates')->where('id', $estimate->id)->update(['version' => $estimate->version + 1, 'status' => 'draft', 'updated_at' => now()]);
                $id = $estimate->id;
            } else {
                $id = DB::table('budget_estimates')->insertGetId(['budget_call_id' => $data['budget_call_id'], 'organization_unit_id' => $data['organization_unit_id'], 'status' => 'draft', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($data['lines'] as $line) DB::table('budget_estimate_lines')->insert([...$line, 'budget_estimate_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit->record($request, 'budget_planning', 'estimate.saved', 'budget_estimate', $id);
            return $this->respond($request, 'Budget estimate saved as draft.', $this->estimateDetail($id), 201);
        });
    }

    public function submitEstimate(Request $request, int $estimate): JsonResponse
    {
        $record = DB::table('budget_estimates')->where('id', $estimate)->firstOrFail();
        abort_unless(in_array($record->status, ['draft', 'returned'], true), 409, 'Estimate is not in a submittable state.');
        abort_unless(DB::table('budget_calls')->where('id', $record->budget_call_id)->where('status', 'open')->exists(), 409, 'The budget call is closed.');
        DB::table('budget_estimates')->where('id', $estimate)->update(['status' => 'submitted', 'submitted_by' => $request->user()->id, 'submitted_at' => now(), 'updated_at' => now()]);
        $this->outbox->record('BudgetEstimateSubmitted', 'budget_planning', 'budget_estimate', $estimate, ['budget_call_id' => $record->budget_call_id, 'organization_unit_id' => $record->organization_unit_id], $request->attributes->get('request_id'));
        $this->audit->record($request, 'budget_planning', 'estimate.submitted', 'budget_estimate', $estimate);
        return $this->respond($request, 'Budget estimate submitted.', $this->estimateDetail($estimate));
    }

    public function versions(Request $request): JsonResponse
    {
        $query = DB::table('budget_versions')->orderByDesc('id');
        if ($request->filled('budget_call_id')) $query->where('budget_call_id', $request->integer('budget_call_id'));
        return $this->respond($request, 'Consolidated budget versions retrieved.', $query->paginate($this->pageSize($request)));
    }

    public function consolidate(Request $request, int $call, WorkflowService $workflows): JsonResponse
    {
        return DB::transaction(function () use ($request, $call, $workflows): JsonResponse {
            $record = DB::table('budget_calls')->where('id', $call)->lockForUpdate()->firstOrFail();
            $estimates = DB::table('budget_estimates')->where('budget_call_id', $call)->where('status', 'submitted')->get();
            abort_if($estimates->isEmpty(), 409, 'There are no submitted estimates to consolidate.');
            $version = (int) DB::table('budget_versions')->where('budget_call_id', $call)->max('version') + 1;
            $versionId = DB::table('budget_versions')->insertGetId(['budget_call_id' => $call, 'version' => $version, 'status' => 'in_review', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $lines = DB::table('budget_estimate_lines as l')->join('budget_estimates as e', 'e.id', '=', 'l.budget_estimate_id')->whereIn('e.id', $estimates->pluck('id'))->select('e.organization_unit_id', 'l.account_combination_id', 'l.budget_type', 'l.amount')->get();
            foreach ($lines as $line) DB::table('budget_version_lines')->insert(['budget_version_id' => $versionId, ...((array) $line), 'created_at' => now(), 'updated_at' => now()]);
            $hash = hash('sha256', json_encode($lines->values()->all(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            DB::table('budget_versions')->where('id', $versionId)->update(['content_hash' => $hash]);
            $flow = $workflows->start('budget.version.approve', 'budget_version', $versionId, $request->user()->id, ['budget_call_id' => $call], $request->attributes->get('request_id'));
            DB::table('budget_versions')->where('id', $versionId)->update(['approval_workflow_id' => (string) $flow->id]);
            $this->outbox->record('BudgetVersionSubmittedForApproval', 'budget_planning', 'budget_version', $versionId, ['version' => $version, 'content_hash' => $hash], $request->attributes->get('request_id'));
            $this->audit->record($request, 'budget_planning', 'version.consolidated', 'budget_version', $versionId, ['content_hash' => $hash]);
            return $this->respond($request, 'Consolidated version submitted for independent approval.', DB::table('budget_versions')->find($versionId), 202);
        });
    }

    public function publishBudget(Request $request, int $version): JsonResponse
    {
        return DB::transaction(function () use ($request, $version): JsonResponse {
            $record = DB::table('budget_versions')->where('id', $version)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'in_review' && $record->approval_workflow_id, 409, 'Budget version is not awaiting approval.');
            $flow = WorkflowInstance::query()->findOrFail($record->approval_workflow_id);
            abort_unless($flow->status === 'approved' && $flow->requested_by !== $request->user()->id, 403, 'Independent workflow approval is required.');
            DB::table('budget_versions')->where('budget_call_id', $record->budget_call_id)->where('status', 'approved')->update(['status' => 'superseded', 'updated_at' => now()]);
            DB::table('budget_versions')->where('id', $version)->update(['status' => 'approved', 'approved_at' => now(), 'updated_at' => now()]);
            $this->outbox->record('ApprovedBudgetVersionPublished', 'budget_planning', 'budget_version', $version, ['version' => $record->version, 'content_hash' => $record->content_hash], $request->attributes->get('request_id'));
            $this->audit->record($request, 'budget_planning', 'version.published', 'budget_version', $version, ['content_hash' => $record->content_hash]);
            return $this->respond($request, 'Approved budget version published.', DB::table('budget_versions')->find($version));
        });
    }

    public function allocations(Request $request): JsonResponse
    {
        $rows = DB::table('budget_allocations as a')->join('budget_version_lines as l', 'l.id', '=', 'a.budget_version_line_id')->join('budget_versions as v', 'v.id', '=', 'l.budget_version_id')->where('v.status', 'approved')->select('a.*', 'l.organization_unit_id', 'l.account_combination_id', 'l.budget_type')->orderByDesc('a.id')->paginate($this->pageSize($request));
        return $this->respond($request, 'Active allocations retrieved.', $rows);
    }

    public function createAllocation(Request $request): JsonResponse
    {
        $data = $request->validate(['budget_version_line_id' => ['required', 'integer', 'exists:budget_version_lines,id'], 'amount' => ['required', 'regex:/^\d{1,16}(\.\d{1,2})?$/']]);
        $id = DB::transaction(function () use ($request, $data): int {
            $line = DB::table('budget_version_lines as l')->join('budget_versions as v', 'v.id', '=', 'l.budget_version_id')->where('l.id', $data['budget_version_line_id'])->where('v.status', 'approved')->lockForUpdate()->select('l.*')->first();
            abort_unless($line, 409, 'Only published approved budget lines can be allocated.');
            $allocated = DB::table('budget_allocations')->where('budget_version_line_id', $line->id)->where('status', 'active')->sum('amount');
            abort_if($this->cents($allocated) + $this->cents($data['amount']) > $this->cents($line->amount), 409, 'Allocation exceeds the approved budget line.');
            return DB::table('budget_allocations')->insertGetId([...$data, 'status' => 'active', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        });
        $this->audit->record($request, 'budget_execution', 'allocation.created', 'budget_allocation', $id);
        return $this->respond($request, 'Budget allocation created.', DB::table('budget_allocations')->find($id), 201);
    }

    public function commitments(Request $request): JsonResponse
    {
        return $this->respond($request, 'Budget commitments retrieved.', DB::table('budget_commitments')->orderByDesc('id')->paginate($this->pageSize($request)));
    }

    public function createCommitment(Request $request): JsonResponse
    {
        $data = $request->validate(['budget_allocation_id' => ['required', 'integer', 'exists:budget_allocations,id'], 'source_module' => ['required', 'string', 'max:80'], 'source_reference' => ['required', 'string', 'max:120'], 'idempotency_key' => ['required', 'string', 'max:120'], 'amount' => ['required', 'regex:/^\d{1,16}(\.\d{1,2})?$/']]);
        return DB::transaction(function () use ($request, $data): JsonResponse {
            $existing = DB::table('budget_commitments')->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless($existing->budget_allocation_id == $data['budget_allocation_id'] && $existing->amount === $this->decimal($data['amount']) && $existing->source_module === $data['source_module'] && $existing->source_reference === $data['source_reference'], 409, 'Idempotency key was already used for a different request.');
                return $this->respond($request, 'Existing idempotent commitment returned.', $existing);
            }
            $allocation = DB::table('budget_allocations')->where('id', $data['budget_allocation_id'])->lockForUpdate()->firstOrFail();
            abort_unless($allocation->status === 'active', 409, 'Allocation is not active.');
            $existing = DB::table('budget_commitments')->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless($existing->budget_allocation_id == $data['budget_allocation_id'] && $existing->amount === $this->decimal($data['amount']) && $existing->source_module === $data['source_module'] && $existing->source_reference === $data['source_reference'], 409, 'Idempotency key was already used for a different request.');
                return $this->respond($request, 'Existing idempotent commitment returned.', $existing);
            }
            $committed = DB::table('budget_commitments')->where('budget_allocation_id', $allocation->id)->where('status', 'active')->sum('remaining_amount');
            abort_if($this->cents($committed) + $this->cents($data['amount']) > $this->cents($allocation->amount), 409, 'Commitment exceeds the remaining allocation.');
            $id = DB::table('budget_commitments')->insertGetId([...$data, 'remaining_amount' => $data['amount'], 'status' => 'active', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('budget_commitment_movements')->insert(['budget_commitment_id' => $id, 'movement_type' => 'reserve', 'amount' => $data['amount'], 'idempotency_key' => 'reserve:'.hash('sha256', $data['idempotency_key']), 'actor_id' => $request->user()->id, 'request_id' => $request->attributes->get('request_id'), 'created_at' => now()]);
            $this->outbox->record('BudgetCommitmentReserved', 'budget_execution', 'budget_commitment', $id, ['allocation_id' => $allocation->id, 'amount' => $data['amount'], 'source_module' => $data['source_module'], 'source_reference' => $data['source_reference']], $request->attributes->get('request_id'));
            $this->audit->record($request, 'budget_execution', 'commitment.reserved', 'budget_commitment', $id);
            return $this->respond($request, 'Budget commitment reserved.', DB::table('budget_commitments')->find($id), 201);
        });
    }

    public function moveCommitment(Request $request, int $commitment): JsonResponse
    {
        $data = $request->validate(['movement_type' => ['required', 'in:liquidate,release'], 'amount' => ['required', 'regex:/^\\d{1,16}(\\.\\d{1,2})?$/'], 'reason' => ['required', 'string', 'max:500'], 'idempotency_key' => ['required', 'string', 'max:120']]);
        return DB::transaction(function () use ($request, $commitment, $data): JsonResponse {
            $record = DB::table('budget_commitments')->where('id', $commitment)->lockForUpdate()->firstOrFail();
            $duplicate = DB::table('budget_commitment_movements')->where('idempotency_key', $data['idempotency_key'])->first();
            if ($duplicate) {
                abort_unless($duplicate->budget_commitment_id == $commitment && $duplicate->movement_type === $data['movement_type'] && $duplicate->amount === $this->decimal($data['amount']), 409, 'Idempotency key was already used for a different movement.');
                return $this->respond($request, 'Existing commitment movement returned.', $duplicate);
            }
            abort_unless($record->status === 'active', 409, 'Only active commitments can be liquidated or released.');
            abort_if($this->cents($data['amount']) <= 0 || $this->cents($data['amount']) > $this->cents($record->remaining_amount), 422, 'Movement must be positive and cannot exceed the remaining commitment.');
            $remaining = $this->cents($record->remaining_amount) - $this->cents($data['amount']);
            DB::table('budget_commitments')->where('id', $commitment)->update(['remaining_amount' => $this->money($remaining), 'status' => $remaining === 0 ? ($data['movement_type'] === 'release' ? 'released' : 'liquidated') : 'active', 'updated_at' => now()]);
            DB::table('budget_commitment_movements')->insert(['budget_commitment_id' => $commitment, 'movement_type' => $data['movement_type'], 'amount' => $data['amount'], 'reason' => $data['reason'], 'idempotency_key' => $data['idempotency_key'], 'actor_id' => $request->user()->id, 'request_id' => $request->attributes->get('request_id'), 'created_at' => now()]);
            $this->audit->record($request, 'budget_execution', 'commitment.'.$data['movement_type'], 'budget_commitment', $commitment, ['amount' => $data['amount'], 'reason' => $data['reason']]);
            return $this->respond($request, 'Commitment movement recorded.', DB::table('budget_commitments')->find($commitment));
        });
    }

    public function journals(Request $request): JsonResponse
    {
        return $this->respond($request, 'Ledger journals retrieved.', DB::table('ledger_journals')->orderByDesc('id')->paginate($this->pageSize($request)));
    }

    public function createJournal(Request $request): JsonResponse
    {
        $data = $request->validate(['accounting_period_id' => ['required', 'integer', 'exists:accounting_periods,id'], 'idempotency_key' => ['required', 'string', 'max:120'], 'description' => ['required', 'string', 'max:4000'], 'source_module' => ['nullable', 'string', 'max:80'], 'source_reference' => ['nullable', 'string', 'max:120'], 'lines' => ['required', 'array', 'min:2', 'max:500'], 'lines.*.account_combination_id' => ['required', 'integer', 'exists:finance_account_combinations,id'], 'lines.*.debit' => ['required', 'regex:/^\d{1,16}(\.\d{1,2})?$/'], 'lines.*.credit' => ['required', 'regex:/^\d{1,16}(\.\d{1,2})?$/'], 'lines.*.memo' => ['nullable', 'string', 'max:500']]);
        $contentHash = hash('sha256', json_encode(['period' => $data['accounting_period_id'], 'description' => $data['description'], 'source_module' => $data['source_module'] ?? null, 'source_reference' => $data['source_reference'] ?? null, 'lines' => $data['lines']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $existing = DB::table('ledger_journals')->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            abort_unless(hash_equals($existing->content_hash, $contentHash), 409, 'Idempotency key was already used for a different journal.');
            return $this->respond($request, 'Existing idempotent journal returned.', $this->journalDetail($existing->id));
        }
        $id = DB::transaction(function () use ($request, $data, $contentHash): int {
            $period = DB::table('accounting_periods')->find($data['accounting_period_id']);
            abort_unless($period && $period->status === 'open', 409, 'Journal drafts require an open accounting period.');
            $number = 'JRN-'.Str::upper(Str::random(12));
            $id = DB::table('ledger_journals')->insertGetId(['accounting_period_id' => $data['accounting_period_id'], 'journal_number' => $number, 'source_module' => $data['source_module'] ?? null, 'source_reference' => $data['source_reference'] ?? null, 'idempotency_key' => $data['idempotency_key'], 'content_hash' => $contentHash, 'description' => $data['description'], 'status' => 'draft', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['lines'] as $line) {
                abort_unless(DB::table('finance_account_combinations as c')->join('finance_master_versions as v', 'v.id', '=', 'c.master_version_id')->where('c.id', $line['account_combination_id'])->where('c.status', 'active')->where('v.status', 'active')->exists(), 422, 'Journal lines require active finance account combinations.');
                abort_if($this->cents($line['debit']) > 0 && $this->cents($line['credit']) > 0, 422, 'A journal line cannot contain both debit and credit.');
                abort_if($this->cents($line['debit']) === 0 && $this->cents($line['credit']) === 0, 422, 'A journal line must have a debit or credit amount.');
                DB::table('ledger_journal_lines')->insert([...$line, 'ledger_journal_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
            return $id;
        });
        $this->audit->record($request, 'general_ledger', 'journal.draft_created', 'ledger_journal', $id);
        return $this->respond($request, 'Journal draft created.', $this->journalDetail($id), 201);
    }

    public function postJournal(Request $request, int $journal, WorkflowService $workflows, FinancialPeriodGuard $periodGuard): JsonResponse
    {
        return DB::transaction(function () use ($request, $journal, $workflows, $periodGuard): JsonResponse {
            $record = DB::table('ledger_journals')->where('id', $journal)->lockForUpdate()->firstOrFail();
            if ($record->status === 'draft') {
                $flow = $workflows->start('ledger.journal.post', 'ledger_journal', $journal, $request->user()->id, ['accounting_period_id' => $record->accounting_period_id], $request->attributes->get('request_id'));
                DB::table('ledger_journals')->where('id', $journal)->update(['status' => 'in_review', 'updated_at' => now()]);
                DB::table('ledger_journals')->where('id', $journal)->update(['approval_workflow_id' => (string) $flow->id]);
                return $this->respond($request, 'Journal submitted for independent approval before posting.', ['workflow_instance_id' => $flow->id, 'status' => 'in_review'], 202);
            }
            abort_unless($record->status === 'in_review' && $record->approval_workflow_id, 409, 'Journal is not awaiting approval.');
            $flow = WorkflowInstance::query()->findOrFail($record->approval_workflow_id);
            abort_unless($flow->status === 'approved' && $flow->requested_by !== $request->user()->id, 403, 'Independent workflow approval is required.');
            $periodGuard->assertOpen((int) $record->accounting_period_id);
            $lines = DB::table('ledger_journal_lines')->where('ledger_journal_id', $journal)->get();
            $debits = $lines->sum(fn ($line) => $this->cents($line->debit));
            $credits = $lines->sum(fn ($line) => $this->cents($line->credit));
            abort_if($debits <= 0 || $debits !== $credits, 422, 'Journal must contain equal, non-zero debit and credit totals.');
            DB::table('ledger_journals')->where('id', $journal)->update(['status' => 'posted', 'posted_by' => $request->user()->id, 'posted_at' => now(), 'updated_at' => now()]);
            $this->outbox->record('JournalPosted', 'general_ledger', 'ledger_journal', $journal, ['journal_number' => $record->journal_number, 'period_id' => $record->accounting_period_id, 'debit_total' => $this->money($debits)], $request->attributes->get('request_id'));
            $this->audit->record($request, 'general_ledger', 'journal.posted', 'ledger_journal', $journal, ['journal_number' => $record->journal_number]);
            return $this->respond($request, 'Journal posted and locked against editing.', $this->journalDetail($journal));
        });
    }

    public function reverseJournal(Request $request, int $journal): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:500'], 'idempotency_key' => ['required', 'string', 'max:120']]);
        return DB::transaction(function () use ($request, $journal, $reason): JsonResponse {
            $original = DB::table('ledger_journals')->where('id', $journal)->lockForUpdate()->firstOrFail();
            abort_unless($original->status === 'posted', 409, 'Only posted journals can be reversed.');
            $existing = DB::table('ledger_journals')->where('idempotency_key', $reason['idempotency_key'])->first();
            if ($existing) {
                abort_unless($existing->reversal_of_id == $journal, 409, 'Idempotency key was already used for another journal.');
                return $this->respond($request, 'Existing idempotent reversal returned.', $this->journalDetail($existing->id));
            }
            abort_if(DB::table('ledger_journals')->where('reversal_of_id', $journal)->whereIn('status', ['in_review', 'posted'])->exists(), 409, 'A reversal is already pending or posted.');
            $reversalLines = DB::table('ledger_journal_lines')->where('ledger_journal_id', $journal)->get();
            $reversalContentHash = hash('sha256', json_encode(['reversal_of' => $journal, 'reason' => $reason['reason']], JSON_THROW_ON_ERROR));
            $id = DB::table('ledger_journals')->insertGetId(['accounting_period_id' => $original->accounting_period_id, 'journal_number' => 'REV-'.Str::upper(Str::random(12)), 'source_module' => 'general_ledger', 'source_reference' => (string) $journal, 'idempotency_key' => $reason['idempotency_key'], 'content_hash' => $reversalContentHash, 'description' => 'Reversal of '.$original->journal_number.': '.$reason['reason'], 'status' => 'draft', 'reversal_of_id' => $journal, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($reversalLines as $line) DB::table('ledger_journal_lines')->insert(['ledger_journal_id' => $id, 'account_combination_id' => $line->account_combination_id, 'debit' => $line->credit, 'credit' => $line->debit, 'memo' => 'Reversal: '.$reason['reason'], 'created_at' => now(), 'updated_at' => now()]);
            $this->audit->record($request, 'general_ledger', 'journal.reversal_draft_created', 'ledger_journal', $id, ['reversal_of_id' => $journal, 'reason' => $reason['reason']]);
            return $this->respond($request, 'Balanced reversal draft created; independent approval and posting are still required.', $this->journalDetail($id), 201);
        });
    }

    public function trialBalance(Request $request): JsonResponse
    {
        $period = $request->validate(['accounting_period_id' => ['required', 'integer', 'exists:accounting_periods,id']])['accounting_period_id'];
        $rows = DB::table('ledger_journal_lines as l')->join('ledger_journals as j', 'j.id', '=', 'l.ledger_journal_id')->where('j.accounting_period_id', $period)->where('j.status', 'posted')->groupBy('l.account_combination_id')->select('l.account_combination_id', DB::raw('SUM(l.debit) as debit_total'), DB::raw('SUM(l.credit) as credit_total'))->orderBy('l.account_combination_id')->get();
        return $this->respond($request, 'Trial balance retrieved.', $rows);
    }

    private function estimateDetail(int $id): array
    {
        return ['estimate' => DB::table('budget_estimates')->find($id), 'lines' => DB::table('budget_estimate_lines')->where('budget_estimate_id', $id)->get()->all()];
    }

    private function journalDetail(int $id): array
    {
        return ['journal' => DB::table('ledger_journals')->find($id), 'lines' => DB::table('ledger_journal_lines')->where('ledger_journal_id', $id)->get()->all()];
    }

    private function pageSize(Request $request): int { return min(max($request->integer('per_page', 25), 1), 100); }
    private function decimal(string|int|float $value): string { $parts = explode('.', (string) $value, 2); $whole = ltrim($parts[0], '0'); return ($whole === '' ? '0' : $whole).'.'.str_pad(substr($parts[1] ?? '0', 0, 2), 2, '0'); }
    private function cents(string|int|float $value): int { $parts = explode('.', (string) $value, 2); $whole = ltrim($parts[0], '0'); abort_if(strlen($whole) > 14, 422, 'Amount exceeds the supported precision.'); return ((int) ($whole === '' ? '0' : $whole) * 100) + (int) str_pad(substr($parts[1] ?? '0', 0, 2), 2, '0'); }
    private function money(int $cents): string { return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT); }
    private function respond(Request $request, string $message, mixed $data, int $status = 200): JsonResponse { return response()->json(['success' => true, 'message' => $message, 'data' => $data, 'meta' => ['request_id' => $request->attributes->get('request_id')]], $status); }
}

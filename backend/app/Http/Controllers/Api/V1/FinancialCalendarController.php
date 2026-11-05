<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\OutboxPublisher;
use App\Domain\Workflow\WorkflowService;
use App\Http\Controllers\Controller;
use App\Models\WorkflowInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialCalendarController extends Controller
{
    public function calendars(Request $request): JsonResponse
    {
        return $this->respond($request, 'Financial calendars retrieved.', DB::table('financial_calendars')->orderBy('code')->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function createCalendar(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:financial_calendars,code'], 'name' => ['required', 'string', 'max:200']]);
        $id = DB::table('financial_calendars')->insertGetId([
            'code' => $data['code'], 'name' => $data['name'], 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->respond($request, 'Financial calendar created. Dates remain subject to approved year setup.', DB::table('financial_calendars')->where('id', $id)->first(), 201);
    }

    public function years(Request $request): JsonResponse
    {
        $years = DB::table('financial_years')->orderBy('starts_on')->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->respond($request, 'Financial years retrieved.', $years);
    }

    public function createYear(Request $request): JsonResponse
    {
        $data = $request->validate([
            'calendar_id' => ['required', 'integer', 'exists:financial_calendars,id'],
            'code' => ['required', 'string', 'max:100'],
            'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date', 'after:starts_on'],
            'periods' => ['required', 'array', 'min:1', 'max:100'],
            'periods.*.code' => ['required', 'string', 'max:100', 'distinct'],
            'periods.*.starts_on' => ['required', 'date'], 'periods.*.ends_on' => ['required', 'date'],
        ]);

        return DB::transaction(function () use ($request, $data): JsonResponse {
            DB::table('financial_calendars')->where('id', $data['calendar_id'])->lockForUpdate()->firstOrFail();
            $overlap = DB::table('financial_years')->where('calendar_id', $data['calendar_id'])
                ->where('starts_on', '<=', $data['ends_on'])->where('ends_on', '>=', $data['starts_on'])->exists();
            abort_if($overlap, 409, 'Financial year dates overlap an existing year in this calendar.');
            $periods = collect($data['periods'])->sortBy('starts_on')->values();
            $lastEnd = null;
            foreach ($periods as $period) {
                abort_if($period['ends_on'] < $period['starts_on'], 422, 'A financial period cannot end before it starts.');
                abort_if($period['starts_on'] < $data['starts_on'] || $period['ends_on'] > $data['ends_on'], 422, 'Periods must fall inside their financial year.');
                abort_if($lastEnd !== null && $period['starts_on'] <= $lastEnd, 422, 'Financial periods must not overlap.');
                if ($lastEnd !== null) {
                    abort_if($period['starts_on'] !== Carbon::parse($lastEnd)->addDay()->toDateString(), 422, 'Financial periods must cover the year without date gaps.');
                } else {
                    abort_if($period['starts_on'] !== $data['starts_on'], 422, 'The first period must begin on the financial year start date.');
                }
                $lastEnd = $period['ends_on'];
            }
            abort_if($lastEnd !== $data['ends_on'], 422, 'The final period must end on the financial year end date.');
            $yearId = DB::table('financial_years')->insertGetId([
                'calendar_id' => $data['calendar_id'], 'code' => $data['code'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'],
                'status' => 'draft', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($periods as $period) {
                DB::table('accounting_periods')->insert([
                    'financial_year_id' => $yearId, 'code' => $period['code'], 'starts_on' => $period['starts_on'], 'ends_on' => $period['ends_on'],
                    'status' => 'future', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $this->respond($request, 'Financial year saved as draft.', DB::table('financial_years')->where('id', $yearId)->first(), 201);
        });
    }

    public function activateYear(Request $request, int $year, WorkflowService $workflows, OutboxPublisher $outbox): JsonResponse
    {
        return DB::transaction(function () use ($request, $year, $workflows, $outbox): JsonResponse {
            $record = DB::table('financial_years')->where('id', $year)->lockForUpdate()->firstOrFail();
            if ($record->approval_workflow_id) {
                $previous = WorkflowInstance::query()->findOrFail($record->approval_workflow_id);
                if (in_array($previous->status, ['rejected', 'returned'], true)) {
                    DB::table('financial_years')->where('id', $year)->update(['approval_workflow_id' => null, 'updated_at' => now()]);
                    $record->approval_workflow_id = null;
                } elseif ($previous->status !== 'approved') {
                    abort(409, 'Year activation is still awaiting approval.');
                }
            }
            if (! $record->approval_workflow_id) {
                $flow = $workflows->start('finance.financial_year.activate', 'financial_year', $year, $request->user()->id, [], $request->attributes->get('request_id'));
                DB::table('financial_years')->where('id', $year)->update(['approval_workflow_id' => (string) $flow->id, 'updated_at' => now()]);

                return $this->respond($request, 'Year activation submitted for approval.', ['workflow_instance_id' => $flow->id, 'status' => 'in_review'], 202);
            }
            $flow = WorkflowInstance::query()->findOrFail($record->approval_workflow_id);
            abort_unless($flow->status === 'approved' && $flow->requested_by !== $request->user()->id, 403, 'Independent approved activation is required.');
            abort_if($record->status === 'active', 409);
            DB::table('financial_years')->where('id', $year)->update(['status' => 'active', 'version' => $record->version + 1, 'updated_at' => now()]);
            $outbox->record('FinancialYearActivated', 'financial_master_data', 'financial_year', $year, ['version' => $record->version + 1, 'actor_id' => $request->user()->id], $request->attributes->get('request_id'));

            return $this->respond($request, 'Financial year activated.', DB::table('financial_years')->where('id', $year)->first());
        });
    }

    public function periods(Request $request): JsonResponse
    {
        $periods = DB::table('accounting_periods')->when($request->integer('year_id'), fn ($query, $id) => $query->where('financial_year_id', $id))->orderBy('starts_on')->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->respond($request, 'Accounting periods retrieved.', $periods);
    }

    public function openPeriod(Request $request, int $period, WorkflowService $workflows, OutboxPublisher $outbox): JsonResponse
    {
        return $this->transitionPeriod($request, $period, 'open', 'finance.accounting_period.open', $workflows, $outbox);
    }

    public function closePeriod(Request $request, int $period): JsonResponse
    {
        abort(409, 'Period close is unavailable until the general-ledger readiness contract is implemented.');
    }

    public function reopenPeriod(Request $request, int $period, WorkflowService $workflows, OutboxPublisher $outbox): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:8', 'max:500']]);

        return $this->transitionPeriod($request, $period, 'reopen', 'finance.accounting_period.reopen', $workflows, $outbox);
    }

    private function transitionPeriod(Request $request, int $period, string $action, string $workflowCode, WorkflowService $workflows, OutboxPublisher $outbox): JsonResponse
    {
        return DB::transaction(function () use ($request, $period, $action, $workflowCode, $workflows, $outbox): JsonResponse {
            $record = DB::table('accounting_periods')->where('id', $period)->lockForUpdate()->firstOrFail();
            $target = 'open';
            if ($record->approval_workflow_id) {
                $previous = WorkflowInstance::query()->findOrFail($record->approval_workflow_id);
                if (in_array($previous->status, ['rejected', 'returned'], true)) {
                    DB::table('accounting_periods')->where('id', $period)->update(['approval_workflow_id' => null, 'updated_at' => now()]);
                    $record->approval_workflow_id = null;
                } elseif ($previous->status !== 'approved') {
                    abort(409, 'Period transition is still awaiting approval.');
                }
            }
            if (! $record->approval_workflow_id) {
                $flow = $workflows->start($workflowCode, 'accounting_period', $period, $request->user()->id, ['reason' => $request->input('reason')], $request->attributes->get('request_id'));
                DB::table('accounting_periods')->where('id', $period)->update(['approval_workflow_id' => (string) $flow->id, 'updated_at' => now()]);

                return $this->respond($request, 'Period status change submitted for approval.', ['workflow_instance_id' => $flow->id, 'status' => 'in_review'], 202);
            }
            $flow = WorkflowInstance::query()->findOrFail($record->approval_workflow_id);
            abort_unless($flow->status === 'approved' && $flow->requested_by !== $request->user()->id, 403, 'Independent approved period transition is required.');
            if ($action === 'open') {
                abort_unless($record->status === 'future', 409, 'Closed periods require the separate reopen workflow.');
                abort_unless(DB::table('financial_years')->where('id', $record->financial_year_id)->where('status', 'active')->exists(), 409, 'The financial year must be active before opening a period.');
                $event = 'AccountingPeriodOpened';
            } else {
                abort_unless($record->status === 'closed', 409);
                $event = 'FinancialPeriodReopened';
            }
            $reason = $request->input('reason', 'Approved period opening.');
            DB::table('accounting_periods')->where('id', $period)->update(['status' => $target, 'version' => $record->version + 1, 'approval_workflow_id' => null, 'updated_at' => now()]);
            DB::table('period_transition_history')->insert([
                'accounting_period_id' => $period, 'from_status' => $record->status, 'to_status' => $target,
                'actor_id' => $request->user()->id, 'reason' => $reason, 'request_id' => $request->attributes->get('request_id'),
                'transitioned_at' => now(),
            ]);
            $outbox->record($event, 'financial_master_data', 'accounting_period', $period, ['from' => $record->status, 'to' => $target, 'reason' => $reason, 'actor_id' => $request->user()->id], $request->attributes->get('request_id'));

            return $this->respond($request, 'Accounting period status changed.', DB::table('accounting_periods')->where('id', $period)->first());
        });
    }

    private function respond(Request $request, string $message, mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data, 'meta' => ['request_id' => $request->attributes->get('request_id')]], $status);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\OutboxPublisher;
use App\Domain\Workflow\WorkflowService;
use App\Http\Controllers\Controller;
use App\Models\WorkflowInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceMasterDataController extends Controller
{
    private const REFERENCE_TABLES = [
        'funds' => 'finance_funds',
        'funding-sources' => 'finance_funding_sources',
        'economic-classifications' => 'finance_economic_classifications',
        'dimension-types' => 'finance_dimension_types',
    ];

    public function currencies(Request $request): JsonResponse
    {
        return $this->respond($request, 'Currency metadata retrieved.', DB::table('finance_currencies')->where('status', 'active')->orderBy('code')->get(['code', 'name', 'decimal_places']));
    }

    public function versions(Request $request): JsonResponse
    {
        return $this->respond($request, 'Financial master data versions retrieved.', DB::table('finance_master_versions')->orderByDesc('created_at')->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function createVersion(Request $request): JsonResponse
    {
        $data = $request->validate(['domain' => ['required', 'in:finance_master']]);
        $version = (int) DB::table('finance_master_versions')->where('domain', $data['domain'])->max('version') + 1;
        $id = DB::table('finance_master_versions')->insertGetId([
            'domain' => $data['domain'], 'version' => $version, 'status' => 'draft', 'created_by' => $request->user()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->respond($request, 'Financial master data version created as draft.', DB::table('finance_master_versions')->where('id', $id)->first(), 201);
    }

    public function accounts(Request $request, int $version): JsonResponse
    {
        abort_unless(DB::table('finance_master_versions')->where('id', $version)->exists(), 404);
        return $this->respond($request, 'Accounts retrieved.', DB::table('finance_accounts')->where('master_version_id', $version)->orderBy('code')->paginate(min(max($request->integer('per_page', 50), 1), 100)));
    }

    public function createAccount(Request $request, int $version): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100'], 'name' => ['required', 'string', 'max:200'],
            'account_type' => ['required', 'string', 'max:80'], 'parent_id' => ['nullable', 'integer'],
            'effective_from' => ['nullable', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
        abort_unless($request->user()->hasPermission('finance.coa.edit'), 403);
        return DB::transaction(function () use ($request, $version, $data): JsonResponse {
            $master = DB::table('finance_master_versions')->where('id', $version)->lockForUpdate()->firstOrFail();
            abort_unless($master->status === 'draft' && $master->domain === 'finance_master', 409);
            if (! empty($data['parent_id'])) {
                $parent = DB::table('finance_accounts')->where('id', $data['parent_id'])->where('master_version_id', $version)->first();
                abort_unless($parent, 422, 'Parent account must belong to this draft version.');
            }
            $id = DB::table('finance_accounts')->insertGetId([
                'master_version_id' => $version, 'code' => $data['code'], 'name' => $data['name'], 'account_type' => $data['account_type'],
                'parent_id' => $data['parent_id'] ?? null, 'effective_from' => $data['effective_from'] ?? null, 'effective_to' => $data['effective_to'] ?? null,
                'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $this->respond($request, 'Account saved as draft.', DB::table('finance_accounts')->where('id', $id)->first(), 201);
        });
    }

    public function createReference(Request $request, int $version, string $kind): JsonResponse
    {
        abort_unless(isset(self::REFERENCE_TABLES[$kind]), 404);
        $data = $request->validate(['code' => ['required', 'string', 'max:100'], 'name' => ['required', 'string', 'max:200'], 'attributes' => ['sometimes', 'array']]);
        abort_unless($request->user()->hasPermission('finance.coa.edit'), 403);
        $table = self::REFERENCE_TABLES[$kind];
        abort_unless(DB::table('finance_master_versions')->where('id', $version)->where('status', 'draft')->exists(), 409);
        abort_unless(DB::table('finance_master_versions')->where('id', $version)->where('domain', 'finance_master')->exists(), 409);
        abort_unless(DB::table('finance_master_versions')->where('id', $version)->where('status', 'draft')->exists(), 409);
        $id = DB::table($table)->insertGetId([
            'master_version_id' => $version, 'code' => $data['code'], 'name' => $data['name'], 'status' => 'draft',
            'attributes' => json_encode($data['attributes'] ?? [], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->respond($request, 'Financial reference saved as draft.', DB::table($table)->where('id', $id)->first(), 201);
    }

    public function createDimension(Request $request, int $type): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100'], 'name' => ['required', 'string', 'max:200']]);
        abort_unless($request->user()->hasPermission('finance.coa.edit'), 403);
        abort_unless(DB::table('finance_dimension_types')->where('id', $type)->where('status', 'draft')->exists(), 409);
        $id = DB::table('finance_dimensions')->insertGetId([
            'dimension_type_id' => $type, 'code' => $data['code'], 'name' => $data['name'], 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->respond($request, 'Dimension saved as draft.', DB::table('finance_dimensions')->where('id', $id)->first(), 201);
    }

    public function createCombination(Request $request, int $version): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:finance_accounts,id'], 'fund_id' => ['nullable', 'integer', 'exists:finance_funds,id'],
            'funding_source_id' => ['nullable', 'integer', 'exists:finance_funding_sources,id'],
            'economic_classification_id' => ['nullable', 'integer', 'exists:finance_economic_classifications,id'],
            'dimension_ids' => ['sometimes', 'array'], 'dimension_ids.*' => ['integer', 'exists:finance_dimensions,id'],
        ]);
        abort_unless($request->user()->hasPermission('finance.coa.edit'), 403);
        $master = DB::table('finance_master_versions')->where('id', $version)->where('status', 'draft')->first();
        abort_unless($master && $master->domain === 'finance_master', 409);
        abort_unless(DB::table('finance_accounts')->where('id', $data['account_id'])->where('master_version_id', $version)->exists(), 422, 'Account must belong to this master version.');
        foreach (['fund_id' => 'finance_funds', 'funding_source_id' => 'finance_funding_sources', 'economic_classification_id' => 'finance_economic_classifications'] as $field => $table) {
            if (! empty($data[$field])) {
                abort_unless(DB::table($table)->where('id', $data[$field])->where('master_version_id', $version)->exists(), 422, 'Combination references must belong to this master version.');
            }
        }
        $id = DB::table('finance_account_combinations')->insertGetId([
            'master_version_id' => $version, 'account_id' => $data['account_id'], 'fund_id' => $data['fund_id'] ?? null,
            'funding_source_id' => $data['funding_source_id'] ?? null, 'economic_classification_id' => $data['economic_classification_id'] ?? null,
            'dimension_ids' => json_encode($data['dimension_ids'] ?? [], JSON_THROW_ON_ERROR), 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->respond($request, 'Account combination saved as draft.', DB::table('finance_account_combinations')->where('id', $id)->first(), 201);
    }

    public function createAccountingRule(Request $request, int $version): JsonResponse
    {
        $data = $request->validate(['rule_code' => ['required', 'string', 'max:100'], 'rule_definition' => ['required', 'array']]);
        abort_unless($request->user()->hasPermission('finance.coa.edit'), 403);
        abort_unless(DB::table('finance_master_versions')->where('id', $version)->where('domain', 'finance_master')->where('status', 'draft')->exists(), 409);
        $id = DB::table('finance_accounting_rules')->insertGetId([
            'master_version_id' => $version, 'rule_code' => $data['rule_code'],
            'rule_definition' => json_encode($data['rule_definition'], JSON_THROW_ON_ERROR), 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->respond($request, 'Accounting rule saved as draft.', DB::table('finance_accounting_rules')->where('id', $id)->first(), 201);
    }

    public function activateVersion(Request $request, int $version, WorkflowService $workflows, OutboxPublisher $outbox): JsonResponse
    {
        return DB::transaction(function () use ($request, $version, $workflows, $outbox): JsonResponse {
            $master = DB::table('finance_master_versions')->where('id', $version)->lockForUpdate()->firstOrFail();
            if (! $master->approval_workflow_id) {
                $flow = $workflows->start('finance.master_data.activate', 'finance_master_version', $version, $request->user()->id, [], $request->attributes->get('request_id'));
                DB::table('finance_master_versions')->where('id', $version)->update(['approval_workflow_id' => (string) $flow->id, 'updated_at' => now()]);

                return $this->respond($request, 'Master data activation submitted for independent review.', ['workflow_instance_id' => $flow->id, 'status' => 'in_review'], 202);
            }
            $flow = WorkflowInstance::query()->findOrFail($master->approval_workflow_id);
            abort_unless($master->status === 'draft' && $flow->status === 'approved' && $flow->requested_by !== $request->user()->id, 403);
            abort_unless(DB::table('finance_accounts')->where('master_version_id', $version)->exists(), 422, 'At least one account is required before activation.');
            $previousVersions = DB::table('finance_master_versions')->where('domain', $master->domain)->where('status', 'active')->lockForUpdate()->pluck('id');
            $hashContent = '';
            foreach (['finance_accounts', 'finance_funds', 'finance_funding_sources', 'finance_economic_classifications', 'finance_dimension_types', 'finance_accounting_rules', 'finance_account_combinations'] as $table) {
                $hashContent .= DB::table($table)->where('master_version_id', $version)->orderBy('id')->get()->toJson();
            }
            DB::table('finance_master_versions')->where('id', $version)->update([
                'status' => 'active', 'effective_from' => now(), 'content_hash' => hash('sha256', $hashContent), 'updated_at' => now(),
            ]);
            foreach (['finance_accounts', 'finance_funds', 'finance_funding_sources', 'finance_economic_classifications', 'finance_dimension_types', 'finance_accounting_rules', 'finance_account_combinations'] as $table) {
                DB::table($table)->where('master_version_id', $version)->where('status', 'draft')->update(['status' => 'active', 'updated_at' => now()]);
                if ($previousVersions->isNotEmpty()) {
                    DB::table($table)->whereIn('master_version_id', $previousVersions)->where('status', 'active')->update(['status' => 'superseded', 'updated_at' => now()]);
                }
            }
            DB::table('finance_dimensions')->whereIn('dimension_type_id', DB::table('finance_dimension_types')->where('master_version_id', $version)->select('id'))
                ->where('status', 'draft')->update(['status' => 'active', 'updated_at' => now()]);
            if ($previousVersions->isNotEmpty()) {
                DB::table('finance_dimensions')->whereIn('dimension_type_id', DB::table('finance_dimension_types')->whereIn('master_version_id', $previousVersions)->select('id'))
                    ->where('status', 'active')->update(['status' => 'superseded', 'updated_at' => now()]);
                DB::table('finance_master_versions')->whereIn('id', $previousVersions)->update(['status' => 'superseded', 'effective_to' => now(), 'updated_at' => now()]);
            }
            $outbox->record('MasterDataVersionActivated', 'financial_master_data', 'finance_master_version', $version, ['domain' => $master->domain, 'version' => $master->version, 'content_hash' => hash('sha256', $hashContent)], $request->attributes->get('request_id'));

            return $this->respond($request, 'Financial master data version activated.', DB::table('finance_master_versions')->where('id', $version)->first());
        });
    }

    public function validateCombination(Request $request): JsonResponse
    {
        $data = $request->validate(['account_id' => ['required', 'integer'], 'fund_id' => ['nullable', 'integer'], 'funding_source_id' => ['nullable', 'integer'], 'economic_classification_id' => ['nullable', 'integer'], 'dimension_ids' => ['sometimes', 'array'], 'dimension_ids.*' => ['integer']]);
        $account = DB::table('finance_accounts')->where('id', $data['account_id'])->where('status', 'active')->first();
        $valid = (bool) $account;
        foreach (['fund_id' => 'finance_funds', 'funding_source_id' => 'finance_funding_sources', 'economic_classification_id' => 'finance_economic_classifications'] as $field => $table) {
            if (! empty($data[$field])) {
                $valid = $valid && DB::table($table)->where('id', $data[$field])->where('status', 'active')->where('master_version_id', $account?->master_version_id)->exists();
            }
        }
        foreach ($data['dimension_ids'] ?? [] as $dimensionId) {
            $valid = $valid && DB::table('finance_dimensions')->where('id', $dimensionId)->where('status', 'active')
                ->whereIn('dimension_type_id', DB::table('finance_dimension_types')->where('master_version_id', $account?->master_version_id)->select('id'))->exists();
        }

        return $this->respond($request, $valid ? 'Combination references are active.' : 'Combination is invalid or references inactive master data.', ['valid' => $valid, 'master_version_id' => $account?->master_version_id]);
    }

    private function respond(Request $request, string $message, mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data, 'meta' => ['request_id' => $request->attributes->get('request_id')]], $status);
    }
}

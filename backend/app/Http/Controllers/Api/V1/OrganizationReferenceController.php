<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\OutboxPublisher;
use App\Domain\Workflow\WorkflowService;
use App\Http\Controllers\Controller;
use App\Models\WorkflowInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationReferenceController extends Controller
{
    private const RECORDS = [
        'cost-centres' => ['table' => 'cost_centres', 'workflow' => 'org.cost_centre.activate', 'event' => 'CostCentreActivated'],
        'programmes' => ['table' => 'programmes', 'workflow' => 'org.programme.activate', 'event' => 'ProgrammeActivated'],
        'project-refs' => ['table' => 'project_refs', 'workflow' => 'org.project_ref.activate', 'event' => 'ProjectReferenceActivated'],
    ];

    public function index(Request $request, string $kind): JsonResponse
    {
        abort_unless(isset(self::RECORDS[$kind]), 404);
        if ($kind !== 'cost-centres') {
            abort_unless($request->user()->hasPermission('org.unit.view'), 403);
        }
        $table = self::RECORDS[$kind]['table'];
        $query = DB::table($table)->orderBy('id');
        if ($kind === 'cost-centres' && ! $request->user()->hasPermission('org.cost_centre.manage')) {
            $scopes = $request->user()->roles()->wherePivot('status', 'active')->wherePivot('scope_type', 'org_unit')
                ->whereHas('permissions', fn ($permissions) => $permissions->where('code', 'org.cost_centre.manage'))
                ->get()->map(fn ($role) => (string) $role->pivot->scope_id)->all();
            abort_unless($scopes, 403);
            $query->whereIn('org_unit_id', $scopes);
        }

        return $this->respond($request, 'Organization references retrieved.', $query->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function store(Request $request, string $kind): JsonResponse
    {
        abort_unless(isset(self::RECORDS[$kind]), 404);
        $rules = [
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/'], 'name' => ['required', 'string', 'max:200'],
            'effective_from' => ['nullable', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'org_unit_id' => ['nullable', 'integer', 'exists:org_units,id'],
            'programme_id' => ['nullable', 'integer', 'exists:programmes,id'],
            'description' => ['nullable', 'string', 'max:4000'],
        ];
        if ($kind === 'cost-centres') {
            $rules['org_unit_id'] = ['required', 'integer', 'exists:org_units,id'];
        }
        $data = $request->validate($rules);
        $permission = $kind === 'cost-centres' ? 'org.cost_centre.manage' : 'org.unit.propose';
        $scopeId = (string) ($data['org_unit_id'] ?? 'global');
        $scopeType = $kind === 'cost-centres' || ($kind === 'project-refs' && ! empty($data['org_unit_id'])) ? 'org_unit' : 'global';
        abort_unless($request->user()->hasPermission($permission, $scopeType, $scopeId), 403);
        if ($kind === 'cost-centres') {
            abort_unless(DB::table('org_units')->where('id', $data['org_unit_id'])->where('status', 'active')->exists(), 422, 'Cost centres must reference an active organization unit.');
        }
        $values = [
            'code' => $data['code'], 'name' => $data['name'], 'status' => 'draft',
            'effective_from' => $data['effective_from'] ?? null, 'effective_to' => $data['effective_to'] ?? null,
            'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ];
        if ($kind === 'cost-centres') {
            $values['org_unit_id'] = $data['org_unit_id'];
        }
        if ($kind === 'programmes') {
            $values['description'] = $data['description'] ?? null;
        }
        if ($kind === 'project-refs') {
            if (! empty($data['programme_id'])) {
                abort_unless(DB::table('programmes')->where('id', $data['programme_id'])->where('status', 'active')->exists(), 422, 'Project references must point to an active programme.');
            }
            if (! empty($data['org_unit_id'])) {
                abort_unless(DB::table('org_units')->where('id', $data['org_unit_id'])->where('status', 'active')->exists(), 422, 'Project references must point to an active organization unit.');
            }
            $values['programme_id'] = $data['programme_id'] ?? null;
            $values['org_unit_id'] = $data['org_unit_id'] ?? null;
        }
        $id = DB::table(self::RECORDS[$kind]['table'])->insertGetId($values);

        return $this->respond($request, ucfirst(rtrim($kind, 's')).' saved as draft.', DB::table(self::RECORDS[$kind]['table'])->where('id', $id)->first(), 201);
    }

    public function submit(Request $request, string $kind, int $record, WorkflowService $workflows): JsonResponse
    {
        abort_unless(isset(self::RECORDS[$kind]), 404);
        $table = self::RECORDS[$kind]['table'];
        $flow = DB::transaction(function () use ($request, $kind, $record, $table, $workflows): WorkflowInstance {
            $item = DB::table($table)->where('id', $record)->lockForUpdate()->firstOrFail();
            if ($item->status === 'in_review' && $item->approval_workflow_id) {
                $previous = WorkflowInstance::query()->findOrFail($item->approval_workflow_id);
                if (in_array($previous->status, ['rejected', 'returned'], true)) {
                    DB::table($table)->where('id', $record)->update(['status' => 'draft', 'approval_workflow_id' => null, 'updated_at' => now()]);
                    $item->status = 'draft';
                }
            }
            abort_unless($item->status === 'draft', 409);
            $scopeType = isset($item->org_unit_id) && $item->org_unit_id ? 'org_unit' : 'global';
            $scopeId = $scopeType === 'global' ? 'global' : (string) $item->org_unit_id;
            $permission = $kind === 'cost-centres' ? 'org.cost_centre.manage' : 'org.restructure.submit';
            abort_unless($request->user()->hasPermission($permission, $scopeType, $scopeId), 403);
            $workflow = $workflows->start(self::RECORDS[$kind]['workflow'], $kind, $record, $request->user()->id, ['scope_type' => $scopeType, 'scope_id' => $scopeId], $request->attributes->get('request_id'));
            DB::table($table)->where('id', $record)->update(['status' => 'in_review', 'approval_workflow_id' => (string) $workflow->id, 'updated_at' => now()]);

            return $workflow;
        });

        return $this->respond($request, 'Organization reference submitted for approval.', ['id' => $record, 'workflow_instance_id' => $flow->id, 'status' => 'in_review'], 202);
    }

    public function activate(Request $request, string $kind, int $record, OutboxPublisher $outbox): JsonResponse
    {
        abort_unless(isset(self::RECORDS[$kind]), 404);
        $table = self::RECORDS[$kind]['table'];

        return DB::transaction(function () use ($request, $kind, $record, $table, $outbox): JsonResponse {
            $item = DB::table($table)->where('id', $record)->lockForUpdate()->firstOrFail();
            $scopeType = isset($item->org_unit_id) && $item->org_unit_id ? 'org_unit' : 'global';
            $scopeId = $scopeType === 'global' ? 'global' : (string) $item->org_unit_id;
            abort_unless($request->user()->hasPermission('org.restructure.approve', $scopeType, $scopeId), 403);
            $workflow = WorkflowInstance::query()->findOrFail($item->approval_workflow_id);
            abort_unless($item->status === 'in_review' && $workflow->status === 'approved' && $workflow->requested_by !== $request->user()->id, 403);
            DB::table($table)->where('id', $record)->update(['status' => 'active', 'updated_at' => now()]);
            $outbox->record(self::RECORDS[$kind]['event'], 'organization', rtrim($kind, 's'), $record, ['code' => $item->code, 'status' => 'active', 'actor_id' => $request->user()->id], $request->attributes->get('request_id'));

            return $this->respond($request, 'Organization reference activated.', DB::table($table)->where('id', $record)->first());
        });
    }

    private function respond(Request $request, string $message, mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data, 'meta' => ['request_id' => $request->attributes->get('request_id')]], $status);
    }
}

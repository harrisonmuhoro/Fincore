<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Organization\OrganizationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrganizationUnitRequest;
use App\Models\OrganizationUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    public function relationships(Request $request): JsonResponse
    {
        $actor = $request->user();
        $query = DB::table('org_relationships')->orderBy('id');
        if (! $actor->hasPermission('org.unit.view')) {
            $scopeIds = $actor->roles()->wherePivot('status', 'active')->wherePivot('scope_type', 'org_unit')
                ->whereHas('permissions', fn ($permissions) => $permissions->where('code', 'org.unit.view'))
                ->get()->map(fn ($role) => (string) $role->pivot->scope_id)->all();
            $query->whereIn('parent_unit_id', $scopeIds);
        }

        return response()->json(['success' => true, 'message' => 'Organization relationships retrieved.', 'data' => $query->paginate(min(max($request->integer('per_page', 25), 1), 100)), 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->hasPermission('org.unit.view') || $actor->roles()->wherePivot('status', 'active')
            ->whereHas('permissions', fn ($query) => $query->where('code', 'org.unit.view'))->exists(), 403);
        $unitsQuery = OrganizationUnit::query();
        if (! $actor->hasPermission('org.unit.view')) {
            $scopeIds = $actor->roles()->wherePivot('status', 'active')
                ->wherePivot('scope_type', 'org_unit')->whereHas('permissions', fn ($query) => $query->where('code', 'org.unit.view'))
                ->get()->map(fn ($role) => (string) $role->pivot->scope_id)->unique()->all();
            $unitsQuery->whereIn('id', $scopeIds);
        }
        $units = $unitsQuery->orderBy('code')->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json(['success' => true, 'message' => 'Organization units retrieved.', 'data' => $units, 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function store(StoreOrganizationUnitRequest $request, OrganizationService $service): JsonResponse
    {
        abort_unless($request->user()->hasPermission('org.unit.propose'), 403);
        $unit = $service->storeUnit($request->validated(), $request->user()->id);

        return response()->json(['success' => true, 'message' => 'Organization unit saved as draft.', 'data' => $unit, 'meta' => ['request_id' => $request->attributes->get('request_id')]], 201);
    }

    public function submit(Request $request, OrganizationUnit $unit, OrganizationService $service): JsonResponse
    {
        abort_unless($request->user()->hasPermission('org.restructure.submit', 'org_unit', (string) $unit->id), 403);
        $workflow = $service->submitUnit($unit, $request->user()->id, $request->attributes->get('request_id'));

        return response()->json(['success' => true, 'message' => 'Organization unit sent for approval.', 'data' => ['unit_id' => $unit->id, 'workflow_instance_id' => $workflow->id, 'status' => 'in_review'], 'meta' => ['request_id' => $request->attributes->get('request_id')]], 202);
    }

    public function activate(Request $request, OrganizationUnit $unit, OrganizationService $service): JsonResponse
    {
        abort_unless($request->user()->hasPermission('org.restructure.approve', 'org_unit', (string) $unit->id), 403);
        $active = $service->activateUnit($unit, $request->user()->id, $request->attributes->get('request_id'));

        return response()->json(['success' => true, 'message' => 'Organization unit activated.', 'data' => $active, 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function relate(Request $request, OrganizationService $service): JsonResponse
    {
        $data = $request->validate([
            'parent_unit_id' => ['required', 'integer', 'exists:org_units,id'],
            'child_unit_id' => ['required', 'integer', 'exists:org_units,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
        abort_unless($request->user()->hasPermission('org.restructure.submit', 'org_unit', (string) $data['parent_unit_id']), 403);
        $relationshipId = $service->addRelationship($data['parent_unit_id'], $data['child_unit_id'], $data['effective_from'], $request->user()->id, $data['effective_to'] ?? null);

        return response()->json(['success' => true, 'message' => 'Organization relationship saved as draft.', 'data' => ['id' => $relationshipId, 'status' => 'draft'], 'meta' => ['request_id' => $request->attributes->get('request_id')]], 201);
    }

    public function submitRelationship(Request $request, int $relationship, OrganizationService $service): JsonResponse
    {
        $record = DB::table('org_relationships')->where('id', $relationship)->firstOrFail();
        abort_unless($request->user()->hasPermission('org.restructure.submit', 'org_unit', (string) $record->parent_unit_id), 403);
        $workflow = $service->submitRelationship($relationship, $request->user()->id, $request->attributes->get('request_id'));

        return response()->json(['success' => true, 'message' => 'Organization relationship sent for approval.', 'data' => ['id' => $relationship, 'workflow_instance_id' => $workflow->id, 'status' => 'in_review'], 'meta' => ['request_id' => $request->attributes->get('request_id')]], 202);
    }

    public function activateRelationship(Request $request, int $relationship, OrganizationService $service): JsonResponse
    {
        $record = DB::table('org_relationships')->where('id', $relationship)->firstOrFail();
        abort_unless($request->user()->hasPermission('org.restructure.approve', 'org_unit', (string) $record->parent_unit_id), 403);
        $service->activateRelationship($relationship, $request->user()->id, $request->attributes->get('request_id'));

        return response()->json(['success' => true, 'message' => 'Organization relationship activated.', 'data' => ['id' => $relationship, 'status' => 'active'], 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }
}

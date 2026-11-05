<?php

namespace App\Domain\Organization;

use App\Domain\Shared\OutboxPublisher;
use App\Domain\Workflow\WorkflowService;
use App\Models\OrganizationUnit;
use App\Models\WorkflowInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationService
{
    public function __construct(private readonly WorkflowService $workflows, private readonly OutboxPublisher $outbox) {}

    public function storeUnit(array $data, int $actorId): OrganizationUnit
    {
        return DB::transaction(fn () => OrganizationUnit::query()->create([
            'code' => $data['code'], 'name' => $data['name'], 'unit_type' => $data['unit_type'],
            'status' => 'draft', 'effective_from' => $data['effective_from'] ?? null,
            'effective_to' => $data['effective_to'] ?? null, 'created_by' => $actorId,
        ]));
    }

    public function submitUnit(OrganizationUnit $unit, int $actorId, ?string $requestId): WorkflowInstance
    {
        return DB::transaction(function () use ($unit, $actorId, $requestId): WorkflowInstance {
            $locked = OrganizationUnit::query()->lockForUpdate()->findOrFail($unit->id);
            if ($locked->status !== 'draft') {
                abort(409, 'Only a draft organization unit can be submitted.');
            }
            $workflow = $this->workflows->start('org.unit.activate', 'org_unit', $locked->id, $actorId, ['unit_code' => $locked->code, 'scope_type' => 'org_unit', 'scope_id' => (string) $locked->id], $requestId);
            $locked->forceFill(['status' => 'in_review', 'approval_workflow_id' => (string) $workflow->id, 'version' => $locked->version + 1])->save();

            return $workflow;
        });
    }

    public function activateUnit(OrganizationUnit $unit, int $actorId, ?string $requestId): OrganizationUnit
    {
        return DB::transaction(function () use ($unit, $actorId, $requestId): OrganizationUnit {
            $locked = OrganizationUnit::query()->lockForUpdate()->findOrFail($unit->id);
            $workflow = WorkflowInstance::query()->findOrFail($locked->approval_workflow_id);
            abort_unless($workflow->status === 'approved' && $workflow->requested_by !== $actorId, 403, 'Independent approved workflow completion is required.');
            $locked->forceFill(['status' => 'active', 'version' => $locked->version + 1])->save();
            $this->outbox->record('OrganizationUnitActivated', 'organization', 'org_unit', $locked->id, ['code' => $locked->code, 'version' => $locked->version], $requestId);

            return $locked;
        });
    }

    public function addRelationship(int $parentId, int $childId, string $from, int $actorId, ?string $to = null): int
    {
        if ($to && $to < $from) {
            throw ValidationException::withMessages(['effective_to' => ['The end date must not precede the start date.']]);
        }

        return DB::transaction(function () use ($parentId, $childId, $from, $to, $actorId): int {
            OrganizationUnit::query()->orderBy('id')->lockForUpdate()->get(['id']);
            if ($parentId === $childId || $this->hasPath($childId, $parentId)) {
                throw ValidationException::withMessages(['child_unit_id' => ['This relationship would create a hierarchy cycle.']]);
            }

            return (int) DB::table('org_relationships')->insertGetId([
                'parent_unit_id' => $parentId, 'child_unit_id' => $childId, 'effective_from' => $from,
                'effective_to' => $to, 'status' => 'draft', 'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function submitRelationship(int $relationshipId, int $actorId, ?string $requestId): WorkflowInstance
    {
        return DB::transaction(function () use ($relationshipId, $actorId, $requestId): WorkflowInstance {
            $relationship = DB::table('org_relationships')->where('id', $relationshipId)->lockForUpdate()->firstOrFail();
            abort_unless($relationship->status === 'draft', 409);
            $workflow = $this->workflows->start('org.relationship.activate', 'org_relationship', $relationshipId, $actorId, ['scope_type' => 'org_unit', 'scope_id' => (string) $relationship->parent_unit_id], $requestId);
            DB::table('org_relationships')->where('id', $relationshipId)->update(['status' => 'in_review', 'approval_workflow_id' => (string) $workflow->id, 'updated_at' => now()]);

            return $workflow;
        });
    }

    public function activateRelationship(int $relationshipId, int $actorId, ?string $requestId): void
    {
        DB::transaction(function () use ($relationshipId, $actorId, $requestId): void {
            OrganizationUnit::query()->orderBy('id')->lockForUpdate()->get(['id']);
            $relationship = DB::table('org_relationships')->where('id', $relationshipId)->lockForUpdate()->firstOrFail();
            $workflow = WorkflowInstance::query()->findOrFail($relationship->approval_workflow_id);
            abort_unless($workflow->status === 'approved' && $workflow->requested_by !== $actorId, 403);
            abort_unless($relationship->status === 'in_review', 409);
            abort_if($relationship->parent_unit_id === $relationship->child_unit_id || $this->hasPath((int) $relationship->child_unit_id, (int) $relationship->parent_unit_id), 409, 'The approved relationship would create a hierarchy cycle.');
            DB::table('org_relationships')->where('id', $relationshipId)->update(['status' => 'active', 'updated_at' => now()]);
            $this->outbox->record('OrgHierarchyChanged', 'organization', 'org_relationship', $relationshipId, [
                'parent_unit_id' => $relationship->parent_unit_id, 'child_unit_id' => $relationship->child_unit_id,
                'effective_from' => $relationship->effective_from, 'effective_to' => $relationship->effective_to,
            ], $requestId);
        });
    }

    private function hasPath(int $start, int $target): bool
    {
        $frontier = [$start];
        $visited = [];
        while ($frontier !== []) {
            $current = array_pop($frontier);
            if ($current === $target) {
                return true;
            }
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            $frontier = [...$frontier, ...DB::table('org_relationships')->whereIn('status', ['draft', 'in_review', 'active'])->where('parent_unit_id', $current)->pluck('child_unit_id')->map(fn ($id) => (int) $id)->all()];
        }

        return false;
    }
}

<?php

namespace App\Domain\Workflow;

use App\Domain\Shared\OutboxPublisher;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowDecision;
use App\Models\WorkflowInstance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    public function __construct(private readonly OutboxPublisher $outbox) {}

    public function start(string $code, string $subjectType, string|int $subjectId, int $requesterId, array $context = [], ?string $requestId = null): WorkflowInstance
    {
        return DB::transaction(function () use ($code, $subjectType, $subjectId, $requesterId, $context, $requestId): WorkflowInstance {
            $definition = WorkflowDefinition::query()->where('code', $code)->where('status', 'published')->orderByDesc('version')->first();
            if (! $definition || $definition->steps()->count() === 0) {
                throw ValidationException::withMessages(['workflow' => ['No published workflow with configured approval steps is available.']]);
            }

            return WorkflowInstance::query()->create([
                'definition_id' => $definition->id,
                'definition_version' => $definition->version,
                'subject_type' => $subjectType,
                'subject_id' => (string) $subjectId,
                'status' => 'pending',
                'current_step' => 1,
                'requested_by' => $requesterId,
                'context' => ['request_id' => $requestId, ...$context],
            ]);
        });
    }

    public function decide(WorkflowInstance $instance, int $actorId, string $decision, ?string $reason, callable $canApprove, ?string $requestId = null): WorkflowInstance
    {
        if (! in_array($decision, ['approve', 'return', 'reject'], true)) {
            throw ValidationException::withMessages(['decision' => ['Decision must be approve, return or reject.']]);
        }
        if ($decision !== 'approve' && blank($reason)) {
            throw ValidationException::withMessages(['reason' => ['A reason is required for return or rejection.']]);
        }

        return DB::transaction(function () use ($instance, $actorId, $decision, $reason, $canApprove, $requestId): WorkflowInstance {
            $locked = WorkflowInstance::query()->with('definition')->lockForUpdate()->findOrFail($instance->id);
            if ($locked->status !== 'pending') {
                abort(409, 'This workflow is no longer awaiting a decision.');
            }
            if ($locked->requested_by === $actorId) {
                abort(403, 'The requester cannot approve their own submission.');
            }
            $step = $locked->definition->steps()->where('step_order', $locked->current_step)->firstOrFail();
            $permission = $step->approver_reference;
            $scopeType = $locked->context['scope_type'] ?? 'global';
            $scopeId = $locked->context['scope_id'] ?? 'global';
            if (! $permission || ! $canApprove($permission, $scopeType, $scopeId, $decision)) {
                abort(403, 'The actor is not assigned to the current approval step.');
            }

            WorkflowDecision::query()->create([
                'instance_id' => $locked->id,
                'step_order' => $locked->current_step,
                'actor_id' => $actorId,
                'decision' => $decision,
                'reason' => $reason,
                'decided_at' => now(),
                'request_id' => $requestId,
            ]);

            if ($decision === 'approve') {
                $next = $locked->current_step + 1;
                $locked->status = $locked->definition->steps()->where('step_order', $next)->exists() ? 'pending' : 'approved';
                if ($locked->status === 'pending') {
                    $locked->current_step = $next;
                }
            } else {
                $locked->status = $decision === 'return' ? 'returned' : 'rejected';
            }
            $locked->save();

            if (in_array($locked->status, ['approved', 'rejected', 'returned'], true)) {
                $this->outbox->record('ApprovalCompleted', 'workflow', 'workflow_instance', $locked->id, [
                    'workflow_instance_id' => $locked->id,
                    'subject_type' => $locked->subject_type,
                    'subject_id' => $locked->subject_id,
                    'outcome' => $locked->status,
                ], $requestId);
            }

            return $locked;
        });
    }

    public function delegate(int $delegatorId, int $delegateId, string $permission, string $scope, string $reason, string $from, string $to): int
    {
        abort_if($delegatorId === $delegateId, 422, 'A user cannot delegate to themselves.');
        abort_if($to <= $from, 422, 'Delegation end must be later than its start.');
        $delegator = User::query()->findOrFail($delegatorId);
        abort_unless($delegator->hasPermission($permission, ...$this->scopeParts($scope)), 403, 'Delegator does not hold the delegated permission in scope.');

        return (int) DB::table('workflow_delegations')->insertGetId([
            'delegator_id' => $delegatorId, 'delegate_id' => $delegateId, 'scope' => $permission.'|'.$scope,
            'effective_from' => $from, 'effective_to' => $to, 'status' => 'active', 'reason' => $reason,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function mayApproveAsDelegate(int $actorId, string $permission, string $scopeType, string $scopeId): bool
    {
        $scope = $scopeType.':'.$scopeId;
        $delegations = DB::table('workflow_delegations')->where('delegate_id', $actorId)->where('scope', $permission.'|'.$scope)
            ->where('status', 'active')->where('effective_from', '<=', now())->where('effective_to', '>', now())->get();
        foreach ($delegations as $delegation) {
            if (User::query()->find($delegation->delegator_id)?->hasPermission($permission, $scopeType, $scopeId)) {
                return true;
            }
        }

        return false;
    }

    public function escalate(WorkflowInstance $instance, int $actorId, string $reason, ?string $requestId = null): WorkflowInstance
    {
        return $this->terminalAction($instance, $actorId, 'escalate', 'escalated', $reason, $requestId);
    }

    public function cancel(WorkflowInstance $instance, int $actorId, string $reason, ?string $requestId = null): WorkflowInstance
    {
        abort_unless($instance->requested_by === $actorId, 403, 'Only the workflow requester may request cancellation.');

        return $this->terminalAction($instance, $actorId, 'cancel', 'cancelled', $reason, $requestId);
    }

    private function terminalAction(WorkflowInstance $instance, int $actorId, string $decision, string $status, string $reason, ?string $requestId): WorkflowInstance
    {
        return DB::transaction(function () use ($instance, $actorId, $decision, $status, $reason, $requestId): WorkflowInstance {
            $locked = WorkflowInstance::query()->lockForUpdate()->findOrFail($instance->id);
            abort_unless($locked->status === 'pending', 409);
            WorkflowDecision::query()->create([
                'instance_id' => $locked->id, 'step_order' => $locked->current_step, 'actor_id' => $actorId,
                'decision' => $decision, 'reason' => $reason, 'decided_at' => now(), 'request_id' => $requestId,
            ]);
            $locked->forceFill(['status' => $status])->save();
            $event = $decision === 'cancel' ? 'WorkflowCancelled' : 'WorkflowEscalated';
            $this->outbox->record($event, 'workflow', 'workflow_instance', $locked->id, ['status' => $status, 'reason' => $reason], $requestId);

            return $locked;
        });
    }

    private function scopeParts(string $scope): array
    {
        [$type, $id] = array_pad(explode(':', $scope, 2), 2, 'global');

        return [$type, $id];
    }
}

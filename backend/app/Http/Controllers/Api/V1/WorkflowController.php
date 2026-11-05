<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\OutboxPublisher;
use App\Domain\Workflow\WorkflowService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkflowDefinitionRequest;
use App\Http\Requests\Api\V1\WorkflowDecisionRequest;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    public function definitions(Request $request): JsonResponse
    {
        $definitions = WorkflowDefinition::query()->with('steps')->orderBy('code')->orderByDesc('version')->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return response()->json(['success' => true, 'message' => 'Workflow definitions retrieved.', 'data' => $definitions, 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function index(Request $request, WorkflowService $service): JsonResponse
    {
        $actor = $request->user();
        $instances = WorkflowInstance::query()->with(['definition.steps', 'decisions'])->whereIn('status', ['pending', 'escalated'])->latest()->limit(250)->get();
        $visible = $instances->filter(function (WorkflowInstance $instance) use ($actor, $service): bool {
            if ($instance->status === 'escalated') {
                return $actor->hasPermission('workflow.instance.escalate');
            }
            $step = $instance->definition->steps->firstWhere('step_order', $instance->current_step);
            if (! $step || ! $step->approver_reference) {
                return false;
            }
            $scopeType = $instance->context['scope_type'] ?? 'global';
            $scopeId = $instance->context['scope_id'] ?? 'global';

            return $actor->hasPermission($step->approver_reference, $scopeType, $scopeId)
                || $service->mayApproveAsDelegate($actor->id, $step->approver_reference, $scopeType, $scopeId);
        })->map(fn (WorkflowInstance $instance) => [
            'id' => $instance->id, 'definition_code' => $instance->definition->code, 'definition_version' => $instance->definition_version,
            'subject_type' => $instance->subject_type, 'subject_id' => $instance->subject_id, 'status' => $instance->status,
            'current_step' => $instance->current_step, 'requested_by' => $instance->requested_by, 'created_at' => $instance->created_at,
            'decisions' => $instance->decisions->map(fn ($decision) => ['decision' => $decision->decision, 'reason' => $decision->reason, 'actor_id' => $decision->actor_id, 'decided_at' => $decision->decided_at]),
        ])->values();

        return response()->json(['success' => true, 'message' => 'Approval queue retrieved.', 'data' => ['items' => $visible, 'bounded' => true], 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function storeDefinition(StoreWorkflowDefinitionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $definition = DB::transaction(function () use ($data, $request): WorkflowDefinition {
            $version = (int) WorkflowDefinition::query()->where('code', $data['code'])->max('version') + 1;
            $definition = WorkflowDefinition::query()->create([
                'code' => $data['code'], 'name' => $data['name'], 'version' => $version,
                'status' => 'draft', 'configuration' => [], 'created_by' => $request->user()->id,
            ]);
            foreach ($data['steps'] as $index => $step) {
                $definition->steps()->create([
                    'step_order' => $index + 1, 'name' => $step['name'],
                    'approver_type' => 'permission', 'approver_reference' => $step['permission'],
                ]);
            }

            return $definition;
        });

        return response()->json(['success' => true, 'message' => 'Workflow definition saved as draft.', 'data' => $definition->load('steps'), 'meta' => ['request_id' => $request->attributes->get('request_id')]], 201);
    }

    public function publish(Request $request, WorkflowDefinition $definition, OutboxPublisher $outbox): JsonResponse
    {
        abort_if($definition->created_by === $request->user()->id, 403, 'A workflow author cannot publish their own definition.');
        abort_unless($definition->status === 'draft' && $definition->steps()->exists(), 409);
        DB::transaction(function () use ($definition, $request, $outbox): void {
            $definition->forceFill(['status' => 'published'])->save();
            $outbox->record('WorkflowDefinitionPublished', 'workflow', 'workflow_definition', $definition->id, ['code' => $definition->code, 'version' => $definition->version], $request->attributes->get('request_id'));
        });

        return response()->json(['success' => true, 'message' => 'Workflow definition published.', 'data' => $definition->fresh()->load('steps'), 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function start(Request $request, WorkflowService $service): JsonResponse
    {
        $data = $request->validate(['workflow_code' => ['required', 'string', 'max:100'], 'subject_type' => ['required', 'string', 'max:100'], 'subject_id' => ['required', 'string', 'max:120'], 'context' => ['sometimes', 'array']]);
        $instance = $service->start($data['workflow_code'], $data['subject_type'], $data['subject_id'], $request->user()->id, $data['context'] ?? [], $request->attributes->get('request_id'));

        return response()->json(['success' => true, 'message' => 'Approval workflow started.', 'data' => $instance, 'meta' => ['request_id' => $request->attributes->get('request_id')]], 201);
    }

    public function decide(WorkflowDecisionRequest $request, WorkflowInstance $instance, WorkflowService $service): JsonResponse
    {
        $decision = $request->string('decision')->toString();
        $workflow = $service->decide($instance, $request->user()->id, $decision, $request->input('reason'), function (string $permission, string $scopeType, string $scopeId, string $decision) use ($request, $service): bool {
            $actor = $request->user();
            $mayApprove = $actor->hasPermission($permission, $scopeType, $scopeId)
                || $service->mayApproveAsDelegate($actor->id, $permission, $scopeType, $scopeId);
            $actionPermission = match ($decision) {
                'return' => 'workflow.instance.return', 'reject' => 'workflow.instance.reject', default => null,
            };
            if ($actionPermission) {
                $mayApprove = $mayApprove && ($actor->hasPermission($actionPermission, $scopeType, $scopeId)
                    || $service->mayApproveAsDelegate($actor->id, $actionPermission, $scopeType, $scopeId));
            }

            return $mayApprove;
        }, $request->attributes->get('request_id'));

        return response()->json(['success' => true, 'message' => 'Workflow decision recorded.', 'data' => $workflow, 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function delegate(Request $request, WorkflowService $service): JsonResponse
    {
        abort_unless($request->user()->hasPermission('workflow.instance.delegate'), 403);
        $data = $request->validate([
            'delegate_id' => ['required', 'integer', 'exists:users,id'],
            'permission' => ['required', 'string', 'exists:iam_permissions,code'], 'scope_type' => ['required', 'string', 'max:40'],
            'scope_id' => ['required', 'string', 'max:100'], 'effective_from' => ['required', 'date'],
            'effective_to' => ['required', 'date', 'after:effective_from'], 'reason' => ['required', 'string', 'min:8', 'max:500'],
        ]);
        $id = $service->delegate($request->user()->id, $data['delegate_id'], $data['permission'], $data['scope_type'].':'.$data['scope_id'], $data['reason'], $data['effective_from'], $data['effective_to']);

        return response()->json(['success' => true, 'message' => 'Time-bounded delegation created.', 'data' => ['id' => $id], 'meta' => ['request_id' => $request->attributes->get('request_id')]], 201);
    }

    public function escalate(Request $request, WorkflowInstance $instance, WorkflowService $service): JsonResponse
    {
        abort_unless($request->user()->hasPermission('workflow.instance.escalate'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:2000']]);
        $result = $service->escalate($instance, $request->user()->id, $data['reason'], $request->attributes->get('request_id'));

        return response()->json(['success' => true, 'message' => 'Workflow escalated.', 'data' => $result, 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }

    public function cancel(Request $request, WorkflowInstance $instance, WorkflowService $service): JsonResponse
    {
        abort_unless($request->user()->hasPermission('workflow.instance.cancel'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:2000']]);
        $result = $service->cancel($instance, $request->user()->id, $data['reason'], $request->attributes->get('request_id'));

        return response()->json(['success' => true, 'message' => 'Workflow cancelled.', 'data' => $result, 'meta' => ['request_id' => $request->attributes->get('request_id')]]);
    }
}

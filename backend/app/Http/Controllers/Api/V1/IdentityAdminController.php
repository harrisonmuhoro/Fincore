<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workflow\WorkflowService;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Notifications\ActivateAccountNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class IdentityAdminController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $users = User::query()->select(['id', 'name', 'email', 'status', 'mfa_confirmed_at', 'created_at'])->orderBy('name')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));
        $users->getCollection()->transform(fn (User $user) => [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'status' => $user->status,
            'mfa_enabled' => (bool) $user->mfa_confirmed_at, 'created_at' => $user->created_at,
        ]);

        return $this->respond($request, 'Users retrieved.', $users);
    }

    public function roles(Request $request): JsonResponse
    {
        return $this->respond($request, 'Roles retrieved.', Role::query()->with('permissions')->orderBy('name')->paginate(min(max($request->integer('per_page', 25), 1), 100)));
    }

    public function roleAssignments(Request $request): JsonResponse
    {
        $records = DB::table('iam_user_role_assignments')->join('iam_roles', 'iam_roles.id', '=', 'iam_user_role_assignments.role_id')
            ->leftJoin('workflow_instances', 'workflow_instances.id', '=', 'iam_user_role_assignments.approval_workflow_id')
            ->join('users', 'users.id', '=', 'iam_user_role_assignments.user_id')
            ->where('iam_user_role_assignments.status', 'pending')->orderByDesc('iam_user_role_assignments.id')->limit(250)
            ->get(['iam_user_role_assignments.id', 'iam_user_role_assignments.user_id', 'users.name as user_name', 'iam_roles.code as role_code', 'iam_user_role_assignments.scope_type', 'iam_user_role_assignments.scope_id', 'iam_user_role_assignments.effective_from', 'iam_user_role_assignments.reason', 'iam_user_role_assignments.assigned_by', 'workflow_instances.status as workflow_status'])
            ->filter(fn ($record) => $request->user()->hasPermission('iam.role.assign', $record->scope_type, $record->scope_id))
            ->values();

        return $this->respond($request, 'Pending role assignments retrieved.', ['items' => $records]);
    }

    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'reason' => ['required', 'string', 'min:8', 'max:500']]);
        $user = User::query()->create([
            'name' => $data['name'], 'email' => strtolower($data['email']), 'password' => bin2hex(random_bytes(32)),
            'status' => 'invited',
        ]);
        $token = Password::broker()->createToken($user);
        $user->notify(new ActivateAccountNotification($token));
        $this->audit($request, $user, 'user.invite', $data['reason']);

        return $this->respond($request, 'Invitation sent.', ['id' => $user->id, 'email' => $user->email, 'status' => $user->status], 201);
    }

    public function activate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'], 'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        $status = Password::broker()->reset($data, function (User $user, string $password): void {
            abort_unless($user->status === 'invited', 409, 'This account is not awaiting activation.');
            $user->forceFill(['password' => $password, 'email_verified_at' => now(), 'status' => 'verified'])->save();
        });
        abort_unless($status === Password::PASSWORD_RESET, 422, 'The activation link is invalid or expired.');
        $user = User::query()->where('email', strtolower($data['email']))->first();
        if ($user) {
            DB::table('iam_access_audits')->insert([
                'subject_user_id' => $user->id, 'action' => 'user.account.activate', 'outcome' => 'success',
                'reason' => 'Invitation token verified and password established.', 'ip_address' => $request->ip(),
                'request_id' => $request->attributes->get('request_id'), 'occurred_at' => now(),
            ]);
        }

        return $this->respond($request, 'Account activated. Sign in to enroll an authenticator before accessing the system.', ['status' => 'verified', 'mfa_required' => true]);
    }

    public function createRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:iam_roles,code'],
            'name' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
            'permissions' => ['required', 'array', 'min:1'], 'permissions.*' => ['required', 'distinct', 'exists:iam_permissions,code'],
        ]);
        $role = DB::transaction(function () use ($data, $request): Role {
            $role = Role::query()->create(['code' => $data['code'], 'name' => $data['name'], 'description' => $data['description'] ?? null, 'status' => 'active', 'version' => 1]);
            $permissionIds = Permission::query()->whereIn('code', $data['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);
            DB::table('iam_access_audits')->insert([
                'actor_id' => $request->user()->id, 'action' => 'role.create', 'outcome' => 'success', 'reason' => $data['reason'],
                'ip_address' => $request->ip(), 'request_id' => $request->attributes->get('request_id'), 'occurred_at' => now(),
                'details' => json_encode(['role_id' => $role->id, 'permission_count' => $permissionIds->count()], JSON_THROW_ON_ERROR),
            ]);

            return $role->load('permissions');
        });

        return $this->respond($request, 'Role created.', $role, 201);
    }

    public function assignRole(Request $request, User $user, WorkflowService $workflows): JsonResponse
    {
        abort_if($request->user()->id === $user->id, 403, 'Users cannot request privileged role assignment to themselves.');
        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:iam_roles,id'],
            'scope_type' => ['required', 'string', 'max:40'], 'scope_id' => ['required', 'string', 'max:100'],
            'effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ]);
        abort_unless($request->user()->hasPermission('iam.role.assign', $data['scope_type'], $data['scope_id']), 403);
        $assignment = DB::transaction(function () use ($request, $user, $data, $workflows): object {
            $role = Role::query()->whereKey($data['role_id'])->where('status', 'active')->firstOrFail();
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $overlap = DB::table('iam_user_role_assignments')->where('user_id', $user->id)->where('role_id', $role->id)
                ->where('scope_type', $data['scope_type'])->where('scope_id', $data['scope_id'])->whereIn('status', ['active', 'pending'])
                ->where('effective_from', '<', $data['effective_to'] ?? '9999-12-31 23:59:59')
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $data['effective_from']))
                ->exists();
            abort_if($overlap, 409, 'An overlapping role assignment already exists for this user and scope.');
            $id = DB::table('iam_user_role_assignments')->insertGetId([
                'user_id' => $user->id, 'role_id' => $data['role_id'], 'scope_type' => $data['scope_type'], 'scope_id' => $data['scope_id'],
                'effective_from' => $data['effective_from'], 'effective_to' => $data['effective_to'] ?? null,
                'assigned_by' => $request->user()->id, 'reason' => $data['reason'], 'status' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $workflow = $workflows->start('iam.role.assign', 'iam_role_assignment', $id, $request->user()->id, ['user_id' => $user->id, 'role_id' => $data['role_id'], 'reason' => $data['reason'], 'scope_type' => $data['scope_type'], 'scope_id' => $data['scope_id']], $request->attributes->get('request_id'));
            DB::table('iam_user_role_assignments')->where('id', $id)->update(['approval_workflow_id' => (string) $workflow->id]);
            DB::table('iam_access_audits')->insert([
                'actor_id' => $request->user()->id, 'subject_user_id' => $user->id, 'action' => 'role.assignment.requested',
                'outcome' => 'pending', 'reason' => $data['reason'], 'ip_address' => $request->ip(),
                'request_id' => $request->attributes->get('request_id'), 'occurred_at' => now(),
                'details' => json_encode(['role_id' => $data['role_id'], 'scope_type' => $data['scope_type'], 'scope_id' => $data['scope_id']], JSON_THROW_ON_ERROR),
            ]);

            return (object) ['id' => $id, 'workflow_instance_id' => $workflow->id, 'status' => 'pending'];
        });

        return $this->respond($request, 'Role assignment submitted for independent approval.', $assignment, 202);
    }

    public function activateRoleAssignment(Request $request, int $assignment): JsonResponse
    {
        DB::transaction(function () use ($request, $assignment): void {
            $record = DB::table('iam_user_role_assignments')->where('id', $assignment)->lockForUpdate()->firstOrFail();
            abort_unless($request->user()->hasPermission('iam.role.assign', $record->scope_type, $record->scope_id), 403);
            abort_unless($record->assigned_by !== $request->user()->id && $record->user_id !== $request->user()->id, 403, 'The assigning actor or beneficiary cannot activate this role assignment.');
            $workflow = WorkflowInstance::query()->findOrFail($record->approval_workflow_id);
            abort_unless($workflow->status === 'approved' && $workflow->requested_by !== $request->user()->id, 403);
            abort_if($record->status !== 'pending', 409);
            DB::table('iam_user_role_assignments')->where('id', $assignment)->update(['status' => 'active', 'updated_at' => now()]);
            $this->audit($request, User::query()->findOrFail($record->user_id), 'role.assignment.activated', 'Independent role assignment approval completed.');
        });

        return $this->respond($request, 'Role assignment activated.', ['id' => $assignment, 'status' => 'active']);
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->id === $user->id, 403, 'A user cannot suspend their own account.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:500']]);
        abort_unless($user->status === 'active', 409);
        DB::transaction(function () use ($request, $user, $data): void {
            $user->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();
            $user->tokens()->delete();
            $this->audit($request, $user, 'user.suspend', $data['reason']);
        });

        return $this->respond($request, 'User suspended and active API tokens revoked.', ['id' => $user->id, 'status' => 'suspended']);
    }

    public function reinstate(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->id === $user->id, 403, 'A user cannot reinstate their own account.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:500']]);
        abort_unless($user->status === 'suspended' && $user->mfa_confirmed_at, 409, 'A suspended account with confirmed MFA is required.');
        DB::transaction(function () use ($request, $user, $data): void {
            $user->forceFill(['status' => 'active', 'suspended_at' => null, 'failed_login_count' => 0, 'locked_until' => null])->save();
            $this->audit($request, $user, 'user.reinstate', $data['reason']);
        });

        return $this->respond($request, 'User reinstated.', ['id' => $user->id, 'status' => 'active']);
    }

    public function revokeSession(Request $request, User $user, int $session): JsonResponse
    {
        $token = $user->tokens()->whereKey($session)->firstOrFail();
        $token->delete();
        $this->audit($request, $user, 'session.revoke', 'Session token '.$session.' revoked.');

        return $this->respond($request, 'Session revoked.', ['id' => $session, 'status' => 'revoked']);
    }

    private function respond(Request $request, string $message, mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data, 'meta' => ['request_id' => $request->attributes->get('request_id')]], $status);
    }

    private function audit(Request $request, User $subject, string $action, string $reason): void
    {
        DB::table('iam_access_audits')->insert([
            'actor_id' => $request->user()->id, 'subject_user_id' => $subject->id, 'action' => $action,
            'outcome' => 'success', 'reason' => $reason, 'ip_address' => $request->ip(),
            'request_id' => $request->attributes->get('request_id'), 'occurred_at' => now(),
        ]);
        DB::table('security_events')->insert([
            'event_key' => (string) Str::uuid(), 'category' => 'privileged_action', 'severity' => 'info',
            'actor_id' => $request->user()->id, 'subject_user_id' => $subject->id,
            'request_id' => $request->attributes->get('request_id'), 'ip_address' => $request->ip(),
            'details' => json_encode(['action' => $action], JSON_THROW_ON_ERROR), 'occurred_at' => now(),
        ]);
    }
}

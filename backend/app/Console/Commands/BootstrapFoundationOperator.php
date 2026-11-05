<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BootstrapFoundationOperator extends Command
{
    protected $signature = 'iam:bootstrap-operator {email?}';

    protected $description = 'Create or grant least-privilege foundation operator access during deployment setup.';

    public function handle(): int
    {
        $email = strtolower((string) ($this->argument('email') ?: $this->ask('Operator email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid operator email address.');

            return self::FAILURE;
        }
        $name = (string) $this->ask('Operator name');
        $password = (string) $this->secret('Initial password (12+ characters)');
        if (strlen($password) < 12) {
            $this->error('The initial password must be at least 12 characters.');

            return self::FAILURE;
        }

        $permissions = [
            'system.settings.view', 'system.settings.update', 'system.configuration.audit.view',
            'iam.user.invite', 'iam.user.view', 'iam.role.assign', 'iam.permission.manage',
            'org.unit.view', 'org.unit.propose', 'org.unit.edit_draft', 'org.restructure.submit', 'org.cost_centre.manage',
            'workflow.definition.create', 'workflow.workflow_instance.view', 'workflow.instance.assign',
            'finance.coa.view', 'finance.coa.edit', 'finance.financial_year.view', 'finance.financial_year.create', 'finance.accounting_period.view',
        ];

        DB::transaction(function () use ($email, $name, $password, $permissions): void {
            $role = Role::query()->firstOrCreate(
                ['code' => 'foundation-operator'],
                ['name' => 'Foundation operator', 'description' => 'Platform setup and access administration; no financial approval authority.', 'status' => 'active', 'version' => 1],
            );
            $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('code', $permissions)->pluck('id'));

            $user = User::query()->firstOrNew(['email' => $email]);
            if ($user->exists && ! in_array($user->status, ['verified', 'active'], true)) {
                throw new \RuntimeException('Existing account is not eligible for bootstrap access.');
            }
            if (! $user->exists) {
                $user->forceFill(['name' => $name, 'password' => $password, 'status' => 'verified', 'email_verified_at' => now()])->save();
            }
            $existing = DB::table('iam_user_role_assignments')->where('user_id', $user->id)->where('role_id', $role->id)->where('status', 'active')->exists();
            if (! $existing) {
                DB::table('iam_user_role_assignments')->insert([
                    'user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_id' => 'global',
                    'effective_from' => now(), 'assigned_by' => null, 'reason' => 'Initial platform setup by deployment operator.',
                    'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        $this->info('Foundation operator configured. First sign-in requires authenticator enrollment.');
        $this->warn('This role has no business approval or financial posting permissions.');

        return self::SUCCESS;
    }
}

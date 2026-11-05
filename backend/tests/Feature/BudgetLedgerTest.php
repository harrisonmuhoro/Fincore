<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FoundationWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BudgetLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_posts_only_after_independent_workflow_approval_and_when_balanced(): void
    {
        $requester = $this->userWithPermissions(['ledger.journal.create', 'ledger.journal.post']);
        $approver = $this->userWithPermissions(['ledger.journal.post']);
        $this->seed(FoundationWorkflowSeeder::class);
        $periodId = $this->openPeriodWithActiveCombinations();

        $this->actingAs($requester, 'sanctum')->postJson('/api/v1/ledger/journals', [
            'accounting_period_id' => $periodId,
            'idempotency_key' => 'phase3-balanced-journal-001',
            'description' => 'Test balanced journal',
            'lines' => [
                ['account_combination_id' => 1, 'debit' => '25.00', 'credit' => '0.00'],
                ['account_combination_id' => 2, 'debit' => '0.00', 'credit' => '25.00'],
            ],
        ])->assertCreated()->assertJsonPath('data.journal.status', 'draft');

        $journalId = (int) DB::table('ledger_journals')->value('id');
        $submitted = $this->postJson('/api/v1/ledger/journals/'.$journalId.'/post')->assertStatus(202);
        $workflowId = $submitted->json('data.workflow_instance_id');
        DB::table('workflow_instances')->where('id', $workflowId)->update(['status' => 'approved']);

        $this->actingAs($approver, 'sanctum')->postJson('/api/v1/ledger/journals/'.$journalId.'/post')
            ->assertOk()->assertJsonPath('data.journal.status', 'posted');

        $this->assertDatabaseHas('ledger_journals', ['id' => $journalId, 'status' => 'posted', 'posted_by' => $approver->id]);
        $this->getJson('/api/v1/ledger/trial-balance?accounting_period_id='.$periodId)->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_journal_creation_rejects_a_line_with_both_debit_and_credit(): void
    {
        $user = $this->userWithPermissions(['ledger.journal.create']);
        $periodId = $this->openPeriodWithActiveCombinations();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/ledger/journals', [
            'accounting_period_id' => $periodId,
            'idempotency_key' => 'phase3-invalid-line-001',
            'description' => 'Invalid line',
            'lines' => [
                ['account_combination_id' => 1, 'debit' => '5.00', 'credit' => '1.00'],
                ['account_combination_id' => 2, 'debit' => '0.00', 'credit' => '4.00'],
            ],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('ledger_journals', 0);
    }

    private function openPeriodWithActiveCombinations(): int
    {
        $calendar = DB::table('financial_calendars')->insertGetId(['code' => 'TEST-CAL', 'name' => 'Test calendar', 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $year = DB::table('financial_years')->insertGetId(['calendar_id' => $calendar, 'code' => 'TEST-YEAR', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $period = DB::table('accounting_periods')->insertGetId(['financial_year_id' => $year, 'code' => 'TEST-PERIOD', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $version = DB::table('finance_master_versions')->insertGetId(['domain' => 'finance_master', 'version' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([1, 2] as $number) {
            $account = DB::table('finance_accounts')->insertGetId(['master_version_id' => $version, 'code' => 'TEST-'.$number, 'name' => 'Test account '.$number, 'account_type' => 'expense', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('finance_account_combinations')->insert(['master_version_id' => $version, 'account_id' => $account, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        }

        return $period;
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::query()->create(['code' => 'test-'.fake()->unique()->slug(2), 'name' => 'Test role', 'status' => 'active', 'version' => 1]);
        foreach ($permissions as $code) {
            $permission = Permission::query()->firstOrCreate(['code' => $code]);
            $role->permissions()->attach($permission->id);
        }
        $user = User::factory()->create(['status' => 'active', 'mfa_confirmed_at' => now()]);
        DB::table('iam_user_role_assignments')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_id' => 'global', 'effective_from' => now()->subMinute(), 'reason' => 'Test fixture', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }
}

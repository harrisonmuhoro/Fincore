<?php

namespace Tests\Feature;

use App\Domain\Finance\FinancialPeriodGuard;
use App\Domain\Organization\OrganizationService;
use App\Domain\Workflow\WorkflowService;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkflowFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_is_pinned_and_decisions_are_independent_and_immutable(): void
    {
        $permission = Permission::query()->create(['code' => 'finance.financial_year.activate']);
        $role = Role::query()->create(['code' => 'calendar-approver', 'name' => 'Calendar approver', 'status' => 'active', 'version' => 1]);
        $role->permissions()->attach($permission->id);
        $requester = User::factory()->create(['status' => 'active']);
        $approver = User::factory()->create(['status' => 'active']);
        $approver->roles()->attach($role->id, ['scope_type' => 'global', 'scope_id' => 'global', 'effective_from' => now()->subMinute(), 'status' => 'active', 'reason' => 'Test fixture']);
        $definition = WorkflowDefinition::query()->create(['code' => 'test.calendar', 'name' => 'Calendar test', 'version' => 1, 'status' => 'published', 'configuration' => []]);
        $definition->steps()->create(['step_order' => 1, 'name' => 'Independent approval', 'approver_type' => 'permission', 'approver_reference' => $permission->code]);

        $service = app(WorkflowService::class);
        $instance = $service->start('test.calendar', 'financial_year', 55, $requester->id);
        $this->assertSame(1, $instance->definition_version);

        try {
            $service->decide($instance, $requester->id, 'approve', null, fn () => true);
            $this->fail('The requester must not approve their own workflow.');
        } catch (HttpException) {
            $this->assertDatabaseCount('workflow_decisions', 0);
        }

        $approved = $service->decide($instance, $approver->id, 'approve', null, fn ($code, $scopeType, $scopeId) => $approver->hasPermission($code, $scopeType, $scopeId));
        $this->assertSame('approved', $approved->status);
        $this->assertDatabaseHas('domain_outbox_events', ['event_type' => 'ApprovalCompleted', 'entity_id' => (string) $instance->id]);
        $this->assertDatabaseCount('workflow_decisions', 1);
    }

    public function test_period_guard_rejects_closed_and_missing_periods(): void
    {
        $calendar = DB::table('financial_calendars')->insertGetId(['code' => 'TEST', 'name' => 'Test calendar', 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $year = DB::table('financial_years')->insertGetId(['calendar_id' => $calendar, 'code' => 'TEST-YEAR', 'starts_on' => '2031-01-01', 'ends_on' => '2031-12-31', 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $closed = DB::table('accounting_periods')->insertGetId(['financial_year_id' => $year, 'code' => 'P1', 'starts_on' => '2031-01-01', 'ends_on' => '2031-12-31', 'status' => 'closed', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $guard = new FinancialPeriodGuard;
        $this->expectException(HttpException::class);
        $guard->assertOpen($closed);
    }

    public function test_organization_relationship_creation_rejects_hierarchy_cycles(): void
    {
        $first = DB::table('org_units')->insertGetId(['code' => 'A', 'name' => 'Unit A', 'unit_type' => 'test', 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $second = DB::table('org_units')->insertGetId(['code' => 'B', 'name' => 'Unit B', 'unit_type' => 'test', 'status' => 'active', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('org_relationships')->insert(['parent_unit_id' => $first, 'child_unit_id' => $second, 'effective_from' => '2030-01-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(ValidationException::class);
        app(OrganizationService::class)->addRelationship($second, $first, '2030-01-01', 999);
    }
}

<?php

namespace Database\Seeders;

use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

class FoundationWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $workflows = [
            'iam.role.assign' => ['Role assignment approval', 'iam.role.assign'],
            'org.unit.activate' => ['Organization unit activation', 'org.restructure.approve'],
            'org.relationship.activate' => ['Organization relationship activation', 'org.restructure.approve'],
            'org.cost_centre.activate' => ['Cost centre activation', 'org.restructure.approve'],
            'org.programme.activate' => ['Programme activation', 'org.restructure.approve'],
            'org.project_ref.activate' => ['Project reference activation', 'org.restructure.approve'],
            'finance.financial_year.activate' => ['Financial year activation', 'finance.financial_year.activate'],
            'finance.master_data.activate' => ['Financial master data activation', 'finance.master_data.activate'],
            'finance.accounting_period.open' => ['Accounting period opening', 'finance.accounting_period.open'],
            'finance.accounting_period.reopen' => ['Accounting period reopening', 'finance.accounting_period.reopen'],
        ];

        foreach ($workflows as $code => [$name, $permission]) {
            $definition = WorkflowDefinition::query()->firstOrCreate(
                ['code' => $code, 'version' => 1],
                ['name' => $name, 'status' => 'published', 'configuration' => ['managed' => true], 'created_by' => null],
            );
            $definition->steps()->firstOrCreate(
                ['step_order' => 1],
                ['name' => 'Independent authorization', 'approver_type' => 'permission', 'approver_reference' => $permission],
            );
            if ($definition->status !== 'published') {
                $definition->forceFill(['status' => 'published'])->save();
            }
        }
    }
}

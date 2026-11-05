<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FoundationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'system.settings.view', 'system.settings.update', 'system.sequence.configure', 'system.health.view', 'system.configuration.audit.view',
            'iam.user.invite', 'iam.user.activate', 'iam.user.suspend', 'iam.user.reinstate', 'iam.session.revoke', 'iam.role.assign', 'iam.permission.manage', 'iam.access_review.complete', 'iam.user.view', 'iam.user.update_draft',
            'org.unit.propose', 'org.unit.edit_draft', 'org.restructure.submit', 'org.restructure.approve', 'org.leadership.assign', 'org.cost_centre.manage', 'org.history.export', 'org.unit.view', 'org.unit.update_draft',
            'workflow.definition.create', 'workflow.definition.publish', 'workflow.instance.assign', 'workflow.instance.delegate', 'workflow.instance.approve', 'workflow.instance.return', 'workflow.instance.reject', 'workflow.instance.cancel', 'workflow.instance.escalate', 'workflow.workflow_instance.view', 'workflow.workflow_instance.update_draft',
            'finance.coa.view', 'finance.coa.edit', 'finance.account_combination.validate', 'finance.account_combination.activate', 'finance.master_data.activate', 'finance.financial_year.view', 'finance.accounting_period.view', 'finance.financial_year.create', 'finance.financial_year.activate', 'finance.accounting_period.open', 'finance.accounting_period.close', 'finance.accounting_period.reopen', 'finance.master_data.audit.view',
            'records.document.upload', 'records.document.view', 'records.document.view_all', 'records.document.classify', 'records.document.scan_result', 'records.document.hold', 'records.document.download',
            'notification.notification.view', 'notification.preference.manage', 'notification.delivery.view', 'notification.delivery.retry', 'notification.announcement.create', 'notification.announcement.publish',
            'security.event.view', 'security.incident.view', 'security.alert.triage', 'security.incident.assign', 'security.incident.investigate', 'security.incident.contain', 'security.incident.resolve', 'security.incident.close', 'security.monitoring.view', 'security.monitoring.record',
        ];

        foreach ($permissions as $code) {
            Permission::query()->firstOrCreate(['code' => $code], ['description' => 'Defined by the foundation module contract.']);
        }

        DB::table('finance_currencies')->updateOrInsert(['code' => 'KES'], [
            'name' => 'Kenyan shilling', 'decimal_places' => 2, 'status' => 'active', 'updated_at' => now(), 'created_at' => now(),
        ]);
    }
}

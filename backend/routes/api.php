<?php

use App\Http\Controllers\Api\V1\FinanceMasterDataController;
use App\Http\Controllers\Api\V1\FinancialCalendarController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\IdentityAdminController;
use App\Http\Controllers\Api\V1\IdentityController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OrganizationReferenceController;
use App\Http\Controllers\Api\V1\SystemSettingsController;
use App\Http\Controllers\Api\V1\WorkflowController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health/live', [HealthController::class, 'live'])->name('api.health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('api.health.ready');

Route::post('/auth/login', [IdentityController::class, 'login'])->middleware('throttle:login')->name('api.auth.login');
Route::post('/auth/activate', [IdentityAdminController::class, 'activate'])->middleware('throttle:login')->name('api.auth.activate');

Route::middleware(['auth:sanctum', 'mfa'])->group(function (): void {
    Route::post('/auth/logout', [IdentityController::class, 'logout'])->name('api.auth.logout');
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Authenticated user retrieved.',
            'data' => $request->user(),
            'meta' => ['request_id' => $request->attributes->get('request_id')],
        ]);
    })->name('api.user');

    Route::get('/system/settings', [SystemSettingsController::class, 'index'])->name('api.system.settings.index');
    Route::post('/system/settings', [SystemSettingsController::class, 'store'])->name('api.system.settings.store');
    Route::patch('/system/settings/{key}', [SystemSettingsController::class, 'update'])->name('api.system.settings.update');
    Route::get('/organization/units', [OrganizationController::class, 'index'])->name('api.organization.units.index');
    Route::get('/organization/relationships', [OrganizationController::class, 'relationships'])->name('api.organization.relationships.index');
    Route::post('/organization/units', [OrganizationController::class, 'store'])->middleware('permission:org.unit.propose')->name('api.organization.units.store');
    Route::post('/organization/units/{unit}/submit', [OrganizationController::class, 'submit'])->name('api.organization.units.submit');
    Route::post('/organization/units/{unit}/activate', [OrganizationController::class, 'activate'])->name('api.organization.units.activate');
    Route::post('/organization/relationships', [OrganizationController::class, 'relate'])->name('api.organization.relationships.store');
    Route::post('/organization/relationships/{relationship}/submit', [OrganizationController::class, 'submitRelationship'])->name('api.organization.relationships.submit');
    Route::post('/organization/relationships/{relationship}/activate', [OrganizationController::class, 'activateRelationship'])->name('api.organization.relationships.activate');
    Route::get('/organization/references/{kind}', [OrganizationReferenceController::class, 'index'])->name('api.organization.references.index');
    Route::post('/organization/references/{kind}', [OrganizationReferenceController::class, 'store'])->name('api.organization.references.store');
    Route::post('/organization/references/{kind}/{record}/submit', [OrganizationReferenceController::class, 'submit'])->name('api.organization.references.submit');
    Route::post('/organization/references/{kind}/{record}/activate', [OrganizationReferenceController::class, 'activate'])->name('api.organization.references.activate');
    Route::post('/workflow-definitions', [WorkflowController::class, 'storeDefinition'])->middleware('permission:workflow.definition.create')->name('api.workflow.definitions.store');
    Route::get('/workflow-definitions', [WorkflowController::class, 'definitions'])->middleware('permission:workflow.definition.create')->name('api.workflow.definitions.index');
    Route::post('/workflow-definitions/{definition}/publish', [WorkflowController::class, 'publish'])->middleware('permission:workflow.definition.publish')->name('api.workflow.definitions.publish');
    Route::get('/workflows', [WorkflowController::class, 'index'])->name('api.workflows.index');
    Route::post('/workflows', [WorkflowController::class, 'start'])->middleware('permission:workflow.instance.assign')->name('api.workflows.start');
    Route::post('/workflows/{instance}/decisions', [WorkflowController::class, 'decide'])->name('api.workflows.decide');
    Route::post('/workflow-delegations', [WorkflowController::class, 'delegate'])->name('api.workflow-delegations.store');
    Route::post('/workflows/{instance}/escalate', [WorkflowController::class, 'escalate'])->name('api.workflows.escalate');
    Route::post('/workflows/{instance}/cancel', [WorkflowController::class, 'cancel'])->name('api.workflows.cancel');
    Route::post('/users', [IdentityAdminController::class, 'invite'])->middleware('permission:iam.user.invite')->name('api.users.invite');
    Route::get('/users', [IdentityAdminController::class, 'users'])->middleware('permission:iam.user.view')->name('api.users.index');
    Route::get('/roles', [IdentityAdminController::class, 'roles'])->middleware('permission:iam.permission.manage')->name('api.roles.index');
    Route::get('/role-assignments', [IdentityAdminController::class, 'roleAssignments'])->name('api.role-assignments.index');
    Route::post('/users/{user}/suspend', [IdentityAdminController::class, 'suspend'])->middleware('permission:iam.user.suspend')->name('api.users.suspend');
    Route::post('/users/{user}/reinstate', [IdentityAdminController::class, 'reinstate'])->middleware('permission:iam.user.reinstate')->name('api.users.reinstate');
    Route::post('/users/{user}/sessions/{session}/revoke', [IdentityAdminController::class, 'revokeSession'])->middleware('permission:iam.session.revoke')->name('api.users.sessions.revoke');
    Route::post('/roles', [IdentityAdminController::class, 'createRole'])->middleware('permission:iam.permission.manage')->name('api.roles.store');
    Route::post('/users/{user}/roles', [IdentityAdminController::class, 'assignRole'])->name('api.users.roles.assign');
    Route::post('/role-assignments/{assignment}/activate', [IdentityAdminController::class, 'activateRoleAssignment'])->name('api.users.roles.activate');
    Route::get('/finance/financial-years', [FinancialCalendarController::class, 'years'])->middleware('permission:finance.financial_year.view')->name('api.finance.years.index');
    Route::post('/finance/financial-years', [FinancialCalendarController::class, 'createYear'])->middleware('permission:finance.financial_year.create')->name('api.finance.years.store');
    Route::get('/finance/calendars', [FinancialCalendarController::class, 'calendars'])->middleware('permission:finance.financial_year.view')->name('api.finance.calendars.index');
    Route::post('/finance/calendars', [FinancialCalendarController::class, 'createCalendar'])->middleware('permission:finance.financial_year.create')->name('api.finance.calendars.store');
    Route::post('/finance/financial-years/{year}/activate', [FinancialCalendarController::class, 'activateYear'])->middleware('permission:finance.financial_year.activate')->name('api.finance.years.activate');
    Route::get('/finance/accounting-periods', [FinancialCalendarController::class, 'periods'])->middleware('permission:finance.accounting_period.view')->name('api.finance.periods.index');
    Route::post('/finance/accounting-periods/{period}/open', [FinancialCalendarController::class, 'openPeriod'])->middleware('permission:finance.accounting_period.open')->name('api.finance.periods.open');
    Route::post('/finance/accounting-periods/{period}/close', [FinancialCalendarController::class, 'closePeriod'])->middleware('permission:finance.accounting_period.close')->name('api.finance.periods.close');
    Route::post('/finance/accounting-periods/{period}/reopen', [FinancialCalendarController::class, 'reopenPeriod'])->middleware('permission:finance.accounting_period.reopen')->name('api.finance.periods.reopen');
    Route::get('/finance/currencies', [FinanceMasterDataController::class, 'currencies'])->middleware('permission:finance.coa.view')->name('api.finance.currencies.index');
    Route::get('/finance/master-data/versions', [FinanceMasterDataController::class, 'versions'])->middleware('permission:finance.coa.view')->name('api.finance.master-data.versions');
    Route::post('/finance/master-data/versions', [FinanceMasterDataController::class, 'createVersion'])->middleware('permission:finance.coa.edit')->name('api.finance.master-data.versions.store');
    Route::get('/finance/master-data/versions/{version}/accounts', [FinanceMasterDataController::class, 'accounts'])->middleware('permission:finance.coa.view')->name('api.finance.accounts.index');
    Route::post('/finance/master-data/versions/{version}/accounts', [FinanceMasterDataController::class, 'createAccount'])->middleware('permission:finance.coa.edit')->name('api.finance.accounts.store');
    Route::post('/finance/master-data/versions/{version}/references/{kind}', [FinanceMasterDataController::class, 'createReference'])->middleware('permission:finance.coa.edit')->name('api.finance.references.store');
    Route::post('/finance/dimension-types/{type}/dimensions', [FinanceMasterDataController::class, 'createDimension'])->middleware('permission:finance.coa.edit')->name('api.finance.dimensions.store');
    Route::post('/finance/master-data/versions/{version}/combinations', [FinanceMasterDataController::class, 'createCombination'])->middleware('permission:finance.coa.edit')->name('api.finance.combinations.store');
    Route::post('/finance/master-data/versions/{version}/accounting-rules', [FinanceMasterDataController::class, 'createAccountingRule'])->middleware('permission:finance.coa.edit')->name('api.finance.accounting-rules.store');
    Route::post('/finance/master-data/versions/{version}/activate', [FinanceMasterDataController::class, 'activateVersion'])->middleware('permission:finance.master_data.activate')->name('api.finance.master-data.activate');
    Route::post('/finance/account-combinations/validate', [FinanceMasterDataController::class, 'validateCombination'])->middleware('permission:finance.account_combination.validate')->name('api.finance.combinations.validate');
});

Route::middleware(['auth:sanctum', 'abilities:mfa-enrollment'])->group(function (): void {
    Route::post('/auth/mfa/setup', [IdentityController::class, 'startMfa'])->middleware('throttle:login')->name('api.auth.mfa.setup');
    Route::post('/auth/mfa/confirm', [IdentityController::class, 'confirmMfa'])->middleware('throttle:login')->name('api.auth.mfa.confirm');
});

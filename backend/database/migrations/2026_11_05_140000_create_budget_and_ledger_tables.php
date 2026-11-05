<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->string('code', 100)->unique();
            $table->string('name', 200);
            $table->date('submission_deadline')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->index(['financial_year_id', 'status'], 'budget_calls_year_status_idx');
        });
        Schema::create('budget_ceilings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_call_id')->constrained('budget_calls')->restrictOnDelete();
            $table->unsignedBigInteger('organization_unit_id');
            $table->string('budget_type', 40);
            $table->decimal('amount', 20, 2);
            $table->string('currency_code', 3)->default('KES');
            $table->timestamps();
            $table->unique(['budget_call_id', 'organization_unit_id', 'budget_type'], 'budget_ceiling_scope_uq');
        });
        Schema::create('budget_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_call_id')->constrained('budget_calls')->restrictOnDelete();
            $table->unsignedBigInteger('organization_unit_id');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['budget_call_id', 'organization_unit_id'], 'budget_estimate_scope_uq');
        });
        Schema::create('budget_estimate_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_estimate_id')->constrained('budget_estimates')->restrictOnDelete();
            $table->unsignedBigInteger('account_combination_id');
            $table->string('budget_type', 40);
            $table->decimal('amount', 20, 2);
            $table->text('rationale')->nullable();
            $table->timestamps();
            $table->index(['budget_estimate_id', 'budget_type'], 'budget_lines_type_idx');
        });
        Schema::create('budget_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_call_id')->constrained('budget_calls')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft');
            $table->string('content_hash', 64)->nullable();
            $table->string('approval_workflow_id', 120)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['budget_call_id', 'version'], 'budget_version_call_uq');
        });
        Schema::create('budget_version_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_version_id')->constrained('budget_versions')->restrictOnDelete();
            $table->unsignedBigInteger('organization_unit_id');
            $table->unsignedBigInteger('account_combination_id');
            $table->string('budget_type', 40);
            $table->decimal('amount', 20, 2);
            $table->timestamps();
            $table->index(['budget_version_id', 'organization_unit_id'], 'budget_vline_org_idx');
        });
        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_version_line_id')->constrained('budget_version_lines')->restrictOnDelete();
            $table->decimal('amount', 20, 2);
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->index(['budget_version_line_id', 'status'], 'budget_alloc_line_status_idx');
        });
        Schema::create('budget_commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_allocation_id')->constrained('budget_allocations')->restrictOnDelete();
            $table->string('source_module', 80);
            $table->string('source_reference', 120);
            $table->string('idempotency_key', 120)->unique();
            $table->decimal('amount', 20, 2);
            $table->decimal('remaining_amount', 20, 2);
            $table->string('status', 24)->default('active');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->index(['source_module', 'source_reference'], 'budget_commit_source_idx');
        });
        Schema::create('budget_commitment_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_commitment_id')->constrained('budget_commitments')->restrictOnDelete();
            $table->string('movement_type', 24);
            $table->decimal('amount', 20, 2);
            $table->string('idempotency_key', 120)->unique();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('actor_id');
            $table->string('request_id', 100)->nullable();
            $table->timestamp('created_at');
            $table->index(['budget_commitment_id', 'created_at'], 'budget_move_commit_time_idx');
        });
        Schema::create('ledger_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->restrictOnDelete();
            $table->string('journal_number', 100)->unique();
            $table->string('source_module', 80)->nullable();
            $table->string('source_reference', 120)->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->string('content_hash', 64);
            $table->text('description');
            $table->string('status', 20)->default('draft');
            $table->string('approval_workflow_id', 120)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('ledger_journals')->restrictOnDelete();
            $table->timestamps();
            $table->index(['accounting_period_id', 'status'], 'ledger_period_status_idx');
        });
        Schema::create('ledger_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_journal_id')->constrained('ledger_journals')->restrictOnDelete();
            $table->unsignedBigInteger('account_combination_id');
            $table->decimal('debit', 20, 2)->default(0);
            $table->decimal('credit', 20, 2)->default(0);
            $table->string('memo', 500)->nullable();
            $table->timestamps();
            $table->index(['account_combination_id', 'ledger_journal_id'], 'ledger_line_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_journal_lines');
        Schema::dropIfExists('ledger_journals');
        Schema::dropIfExists('budget_commitment_movements');
        Schema::dropIfExists('budget_commitments');
        Schema::dropIfExists('budget_allocations');
        Schema::dropIfExists('budget_version_lines');
        Schema::dropIfExists('budget_versions');
        Schema::dropIfExists('budget_estimate_lines');
        Schema::dropIfExists('budget_estimates');
        Schema::dropIfExists('budget_ceilings');
        Schema::dropIfExists('budget_calls');
    }
};

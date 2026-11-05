<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_master_versions', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 80);
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['domain', 'version']);
        });

        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_version_id')->constrained('finance_master_versions')->restrictOnDelete();
            $table->string('code', 100);
            $table->string('name', 200);
            $table->string('account_type', 80);
            $table->foreignId('parent_id')->nullable()->constrained('finance_accounts')->restrictOnDelete();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->unique(['master_version_id', 'code']);
        });

        foreach (['finance_funds', 'finance_funding_sources', 'finance_economic_classifications', 'finance_dimension_types'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->foreignId('master_version_id')->constrained('finance_master_versions')->restrictOnDelete();
                $table->string('code', 100);
                $table->string('name', 200);
                $table->string('status', 20)->default('draft');
                $table->json('attributes')->nullable();
                $table->timestamps();
                $table->unique(['master_version_id', 'code']);
            });
        }

        Schema::create('finance_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dimension_type_id')->constrained('finance_dimension_types')->restrictOnDelete();
            $table->string('code', 100);
            $table->string('name', 200);
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->unique(['dimension_type_id', 'code']);
        });

        Schema::create('finance_account_combinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_version_id')->constrained('finance_master_versions')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('finance_accounts')->restrictOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained('finance_funds')->restrictOnDelete();
            $table->foreignId('funding_source_id')->nullable()->constrained('finance_funding_sources')->restrictOnDelete();
            $table->foreignId('economic_classification_id')->nullable()->constrained('finance_economic_classifications')->restrictOnDelete();
            $table->json('dimension_ids')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->index(['master_version_id', 'status']);
        });

        Schema::create('finance_accounting_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_version_id')->constrained('finance_master_versions')->restrictOnDelete();
            $table->string('rule_code', 100);
            $table->json('rule_definition');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->unique(['master_version_id', 'rule_code']);
        });

        Schema::create('finance_currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name', 100);
            $table->unsignedTinyInteger('decimal_places');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('financial_calendars', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 200);
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained('financial_calendars')->restrictOnDelete();
            $table->string('code', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->string('approval_workflow_id', 120)->nullable();
            $table->timestamps();
            $table->unique(['calendar_id', 'code']);
            $table->index(['calendar_id', 'starts_on', 'ends_on']);
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_year_id')->constrained('financial_years')->restrictOnDelete();
            $table->string('code', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('future');
            $table->unsignedInteger('version')->default(1);
            $table->string('approval_workflow_id', 120)->nullable();
            $table->timestamps();
            $table->unique(['financial_year_id', 'code']);
            $table->index(['status', 'starts_on', 'ends_on']);
        });

        Schema::create('period_transition_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->restrictOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->unsignedBigInteger('actor_id');
            $table->text('reason');
            $table->string('evidence_reference', 255)->nullable();
            $table->string('request_id', 100)->nullable();
            $table->timestamp('transitioned_at');
            $table->index(['accounting_period_id', 'transitioned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_transition_history');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('financial_years');
        Schema::dropIfExists('financial_calendars');
        Schema::dropIfExists('finance_currencies');
        Schema::dropIfExists('finance_accounting_rules');
        Schema::dropIfExists('finance_account_combinations');
        Schema::dropIfExists('finance_dimensions');
        Schema::dropIfExists('finance_dimension_types');
        Schema::dropIfExists('finance_economic_classifications');
        Schema::dropIfExists('finance_funding_sources');
        Schema::dropIfExists('finance_funds');
        Schema::dropIfExists('finance_accounts');
        Schema::dropIfExists('finance_master_versions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100);
            $table->string('name', 200);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft');
            $table->json('configuration');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['code', 'version']);
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('workflow_definitions')->restrictOnDelete();
            $table->unsignedInteger('step_order');
            $table->string('name', 160);
            $table->string('approver_type', 40);
            $table->string('approver_reference', 160)->nullable();
            $table->json('conditions')->nullable();
            $table->timestamps();
            $table->unique(['definition_id', 'step_order']);
        });

        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('workflow_definitions')->restrictOnDelete();
            $table->unsignedInteger('definition_version');
            $table->string('subject_type', 100);
            $table->string('subject_id', 120);
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('current_step')->default(1);
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['status', 'current_step']);
        });

        Schema::create('workflow_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('workflow_instances')->restrictOnDelete();
            $table->unsignedInteger('step_order');
            $table->unsignedBigInteger('actor_id');
            $table->string('decision', 30);
            $table->text('reason')->nullable();
            $table->timestamp('decided_at');
            $table->string('request_id', 100)->nullable();
            $table->index(['instance_id', 'decided_at']);
        });

        Schema::create('workflow_delegations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delegator_id');
            $table->unsignedBigInteger('delegate_id');
            $table->string('scope', 160);
            $table->timestamp('effective_from');
            $table->timestamp('effective_to');
            $table->string('status', 20)->default('active');
            $table->text('reason');
            $table->timestamps();
            $table->index(['delegator_id', 'status', 'effective_from', 'effective_to'], 'workflow_delegations_actor_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_delegations');
        Schema::dropIfExists('workflow_decisions');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflow_definitions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100);
            $table->string('name', 200);
            $table->string('unit_type', 80);
            $table->string('status', 20)->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('approval_workflow_id', 120)->nullable();
            $table->timestamps();
            $table->unique(['code', 'version']);
        });

        Schema::create('org_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_unit_id')->constrained('org_units')->restrictOnDelete();
            $table->foreignId('child_unit_id')->constrained('org_units')->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('approval_workflow_id', 120)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['child_unit_id', 'status', 'effective_from']);
        });

        Schema::create('cost_centres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_unit_id')->constrained('org_units')->restrictOnDelete();
            $table->string('code', 100)->unique();
            $table->string('name', 200);
            $table->string('status', 20)->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });

        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });

        Schema::create('project_refs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 200);
            $table->foreignId('programme_id')->nullable()->constrained('programmes')->restrictOnDelete();
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_refs');
        Schema::dropIfExists('programmes');
        Schema::dropIfExists('cost_centres');
        Schema::dropIfExists('org_relationships');
        Schema::dropIfExists('org_units');
    }
};

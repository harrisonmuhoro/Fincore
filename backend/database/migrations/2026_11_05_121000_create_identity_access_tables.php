<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'status')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('status', 20)->default('invited')->index());
        }
        if (! Schema::hasColumn('users', 'mfa_secret')) {
            Schema::table('users', fn (Blueprint $table) => $table->text('mfa_secret')->nullable());
        }
        if (! Schema::hasColumn('users', 'mfa_confirmed_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('mfa_confirmed_at')->nullable());
        }
        if (! Schema::hasColumn('users', 'suspended_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('suspended_at')->nullable());
        }
        if (! Schema::hasColumn('users', 'failed_login_count')) {
            Schema::table('users', fn (Blueprint $table) => $table->unsignedInteger('failed_login_count')->default(0));
        }
        if (! Schema::hasColumn('users', 'locked_until')) {
            Schema::table('users', fn (Blueprint $table) => $table->timestamp('locked_until')->nullable());
        }

        if (! Schema::hasTable('iam_roles')) {
            Schema::create('iam_roles', function (Blueprint $table) {
                $table->id();
                $table->string('code', 100)->unique();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('iam_permissions')) {
            Schema::create('iam_permissions', function (Blueprint $table) {
                $table->id();
                $table->string('code', 120)->unique();
                $table->string('description', 250)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('iam_role_permissions')) {
            Schema::create('iam_role_permissions', function (Blueprint $table) {
                $table->foreignId('role_id')->constrained('iam_roles')->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained('iam_permissions')->cascadeOnDelete();
                $table->primary(['role_id', 'permission_id']);
            });
        }

        if (! Schema::hasTable('iam_user_role_assignments')) {
            Schema::create('iam_user_role_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('role_id')->constrained('iam_roles')->restrictOnDelete();
                $table->string('scope_type', 40)->default('global');
                $table->string('scope_id', 100)->default('global');
                $table->timestamp('effective_from');
                $table->timestamp('effective_to')->nullable();
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->string('reason', 500);
                $table->string('status', 20)->default('pending');
                $table->string('approval_workflow_id', 120)->nullable();
                $table->timestamps();
                $table->index(['user_id', 'effective_from', 'effective_to'], 'iam_role_assignments_user_effective_idx');
            });
        }
        if (! Schema::hasIndex('iam_user_role_assignments', ['user_id', 'effective_from', 'effective_to'])) {
            Schema::table('iam_user_role_assignments', fn (Blueprint $table) => $table->index(['user_id', 'effective_from', 'effective_to'], 'iam_role_assignments_user_effective_idx'));
        }

        if (! Schema::hasTable('iam_access_audits')) {
            Schema::create('iam_access_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->unsignedBigInteger('subject_user_id')->nullable();
                $table->string('action', 100);
                $table->string('outcome', 30);
                $table->string('reason', 500)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('request_id', 100)->nullable();
                $table->json('details')->nullable();
                $table->timestamp('occurred_at');
                $table->index(['subject_user_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iam_access_audits');
        Schema::dropIfExists('iam_user_role_assignments');
        Schema::dropIfExists('iam_role_permissions');
        Schema::dropIfExists('iam_permissions');
        Schema::dropIfExists('iam_roles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'mfa_secret', 'mfa_confirmed_at', 'suspended_at', 'failed_login_count', 'locked_until']);
        });
    }
};

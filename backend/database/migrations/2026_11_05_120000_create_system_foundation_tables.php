<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 120);
            $table->string('scope_type', 40)->default('global');
            $table->string('scope_id', 120)->default('global');
            $table->json('value_json');
            $table->string('value_type', 24);
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->string('value_hash', 64);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['setting_key', 'scope_type', 'scope_id', 'status', 'effective_from'], 'system_settings_lookup');
            $table->unique(['setting_key', 'scope_type', 'scope_id', 'version'], 'system_settings_version_unique');
        });

        Schema::create('reference_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('namespace', 100);
            $table->string('period_key', 100)->default('global');
            $table->unsignedBigInteger('next_value')->default(1);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['namespace', 'period_key']);
        });

        Schema::create('system_configuration_audits', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 120);
            $table->string('scope_type', 40);
            $table->string('scope_id', 120);
            $table->string('before_hash', 64)->nullable();
            $table->string('after_hash', 64);
            $table->unsignedInteger('version');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('reason', 500);
            $table->string('request_id', 100)->nullable();
            $table->timestamp('changed_at');
            $table->index(['setting_key', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_configuration_audits');
        Schema::dropIfExists('reference_sequences');
        Schema::dropIfExists('system_settings');
    }
};

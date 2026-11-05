<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_outbox_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('event_type', 160);
            $table->unsignedSmallInteger('event_version')->default(1);
            $table->string('producer', 80);
            $table->string('entity_type', 100);
            $table->string('entity_id', 120);
            $table->uuid('correlation_id')->nullable();
            $table->uuid('causation_id')->nullable();
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->index(['published_at', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_outbox_events');
    }
};

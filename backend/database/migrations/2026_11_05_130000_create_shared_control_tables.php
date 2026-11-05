<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('records_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('classification', 80)->default('unclassified');
            $table->string('sensitivity', 30)->default('internal');
            $table->string('status', 24)->default('quarantined')->index();
            $table->boolean('legal_hold')->default(false)->index();
            $table->date('retention_until')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['owner_user_id', 'status', 'created_at']);
        });

        Schema::create('records_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('records_documents')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('storage_path', 500)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('scan_status', 24)->default('pending');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['document_id', 'version']);
        });

        Schema::create('records_document_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('records_documents')->restrictOnDelete();
            $table->string('owner_module', 80);
            $table->string('owner_record_id', 120);
            $table->foreignId('linked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['document_id', 'owner_module', 'owner_record_id'], 'records_doc_link_unique');
        });

        Schema::create('records_document_access_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('records_documents')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('request_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at');
            $table->index(['document_id', 'occurred_at'], 'records_doc_access_time_idx');
        });

        Schema::create('user_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('event_id', 120);
            $table->string('category', 80)->index();
            $table->string('title', 200);
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['event_id', 'user_id']);
            $table->index(['user_id', 'read_at', 'created_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('category', 80);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'category']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('event_id', 120);
            $table->string('channel', 20);
            $table->string('status', 24)->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->unique(['event_id', 'user_id', 'channel']);
        });

        Schema::create('notification_event_receipts', function (Blueprint $table): void {
            $table->string('event_id', 120)->primary();
            $table->timestamp('processed_at');
        });

        Schema::create('notification_announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200);
            $table->text('body');
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('security_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 160)->unique();
            $table->string('category', 80)->index();
            $table->string('severity', 20)->default('info')->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->unsignedBigInteger('subject_user_id')->nullable()->index();
            $table->string('request_id', 100)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('occurred_at')->index();
        });

        Schema::create('security_incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200);
            $table->text('description');
            $table->string('severity', 20)->index();
            $table->string('status', 24)->default('open')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('resolution_reason', 500)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('security_incident_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained('security_incidents')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->string('reason', 500);
            $table->string('request_id', 100)->nullable();
            $table->timestamp('occurred_at');
        });

        Schema::create('module_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->string('module', 80);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 120);
            $table->string('entity_type', 100)->nullable();
            $table->string('entity_id', 120)->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->char('previous_hash', 64)->nullable();
            $table->char('event_hash', 64);
            $table->index(['module', 'entity_type', 'entity_id']);
        });

        Schema::create('module_audit_chain_heads', function (Blueprint $table): void {
            $table->string('module', 80)->primary();
            $table->unsignedBigInteger('last_event_id')->nullable();
            $table->char('last_hash', 64)->nullable();
            $table->timestamp('updated_at');
        });

        Schema::create('security_monitoring_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('check_type', 40)->index();
            $table->string('status', 20)->index();
            $table->string('source', 100);
            $table->string('summary', 500);
            $table->json('details')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->index();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_audit_events');
        Schema::dropIfExists('module_audit_chain_heads');
        Schema::dropIfExists('security_monitoring_snapshots');
        Schema::dropIfExists('security_incident_history');
        Schema::dropIfExists('security_incidents');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('notification_event_receipts');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_announcements');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('records_document_access_events');
        Schema::dropIfExists('records_document_links');
        Schema::dropIfExists('records_document_versions');
        Schema::dropIfExists('records_documents');
    }
};

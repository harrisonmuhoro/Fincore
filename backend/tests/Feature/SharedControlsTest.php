<?php

namespace Tests\Feature;

use App\Domain\Notifications\NotificationService;
use App\Jobs\DeliverAnnouncement;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SharedControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_upload_is_private_and_download_is_blocked_until_clean_scan(): void
    {
        Storage::fake('private');
        $user = $this->userWithPermissions(['records.document.upload', 'records.document.view', 'records.document.download', 'records.document.scan_result']);
        $this->actingAs($user, 'sanctum');
        $response = $this->postJson('/api/v1/documents', ['title' => 'Evidence file', 'file' => UploadedFile::fake()->create('evidence.pdf', 10, 'application/pdf')]);
        $response->assertCreated()->assertJsonPath('data.status', 'quarantined')->assertJsonPath('data.versions.0.scan_status', 'pending');
        Storage::disk('private')->assertExists($response->json('data.versions.0.storage_path'));
        $this->getJson('/api/v1/documents/'.$response->json('data.id').'/download')->assertStatus(409);
        $this->postJson('/api/v1/documents/'.$response->json('data.id').'/scan-result', ['result' => 'clean', 'scanner' => 'approved-test-scanner', 'evidence' => 'Test scan record.'])->assertOk();
        $this->get('/api/v1/documents/'.$response->json('data.id').'/download')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $newVersion = $this->postJson('/api/v1/documents/'.$response->json('data.id').'/versions', ['file' => UploadedFile::fake()->create('evidence-v2.pdf', 12, 'application/pdf')]);
        $newVersion->assertCreated()->assertJsonPath('data.status', 'quarantined')->assertJsonPath('data.versions.0.version', 2)->assertJsonPath('data.versions.0.scan_status', 'pending');
        $this->getJson('/api/v1/documents/'.$response->json('data.id').'/download')->assertStatus(409);
        $this->assertDatabaseHas('records_document_access_events', ['action' => 'upload']);
    }

    public function test_notification_delivery_is_idempotent_and_private_to_recipient(): void
    {
        $recipient = $this->userWithPermissions(['notification.notification.view']);
        $other = $this->userWithPermissions(['notification.notification.view']);
        $service = app(NotificationService::class);
        $this->assertTrue($service->deliver($recipient->id, 'event-123', 'workflow', 'Approval update', 'A workflow changed state.'));
        $this->assertFalse($service->deliver($recipient->id, 'event-123', 'workflow', 'Approval update', 'A workflow changed state.'));
        $this->actingAs($other, 'sanctum')->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data.data');
        $this->actingAs($recipient, 'sanctum')->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data.data');
        $notificationId = (int) DB::table('user_notifications')->where('user_id', $recipient->id)->value('id');
        $this->postJson('/api/v1/notifications/'.$notificationId.'/read')->assertOk();
        $this->assertSame(0, Artisan::call('security:verify-audit-chains'));
        DB::table('module_audit_events')->where('module', 'notifications')->update(['action' => 'tampered']);
        $this->assertSame(1, Artisan::call('security:verify-audit-chains'));
    }

    public function test_notification_preferences_require_a_channel_and_use_expected_versions(): void
    {
        $user = $this->userWithPermissions(['notification.preference.manage']);
        $this->actingAs($user, 'sanctum');
        $created = $this->putJson('/api/v1/notification-preferences', ['category' => 'workflow', 'in_app_enabled' => true, 'email_enabled' => false])->assertOk();
        $this->putJson('/api/v1/notification-preferences', ['category' => 'workflow', 'in_app_enabled' => false, 'email_enabled' => false])->assertStatus(422);
        $this->putJson('/api/v1/notification-preferences', ['category' => 'workflow', 'in_app_enabled' => false, 'email_enabled' => true, 'expected_version' => 1])->assertOk()->assertJsonPath('data.version', 2);
        $this->putJson('/api/v1/notification-preferences', ['category' => 'workflow', 'in_app_enabled' => true, 'email_enabled' => false, 'expected_version' => 1])->assertStatus(409);
        $this->assertSame(1, $created->json('data.version'));
    }

    public function test_security_incident_transitions_require_current_version_and_preserve_history(): void
    {
        $user = $this->userWithPermissions(['security.alert.triage', 'security.incident.assign', 'security.incident.investigate', 'security.incident.view']);
        $this->actingAs($user, 'sanctum');
        $created = $this->postJson('/api/v1/security/incidents', ['title' => 'Suspicious access', 'description' => 'Repeated access failures require investigation.', 'severity' => 'high'])->assertCreated();
        $id = $created->json('data.id');
        $this->postJson('/api/v1/security/incidents/'.$id.'/transition', ['action' => 'assign', 'reason' => 'Assign to the on-call analyst.', 'expected_version' => 1])->assertOk()->assertJsonPath('data.status', 'assigned');
        $this->postJson('/api/v1/security/incidents/'.$id.'/transition', ['action' => 'investigate', 'reason' => 'Begin incident investigation.', 'expected_version' => 1])->assertStatus(409);
        $this->assertDatabaseCount('security_incident_history', 2);
        $this->assertDatabaseCount('module_audit_events', 2);
    }

    public function test_announcement_requires_a_different_publisher_and_fans_out_idempotently(): void
    {
        $author = $this->userWithPermissions(['notification.announcement.create', 'notification.announcement.publish', 'notification.notification.view']);
        $publisher = $this->userWithPermissions(['notification.announcement.publish', 'notification.notification.view']);
        $created = $this->actingAs($author, 'sanctum')->postJson('/api/v1/announcements', ['title' => 'Service notice', 'body' => 'A fictional notice for the county workspace.'])->assertCreated();
        $announcementId = $created->json('data.id');
        $this->postJson('/api/v1/announcements/'.$announcementId.'/publish')->assertForbidden();
        $this->actingAs($publisher, 'sanctum')->postJson('/api/v1/announcements/'.$announcementId.'/publish')->assertStatus(202);
        (new DeliverAnnouncement((int) $announcementId))->handle(app(NotificationService::class));
        $this->assertDatabaseHas('user_notifications', ['user_id' => $author->id, 'event_id' => 'announcement:'.$announcementId]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $publisher->id, 'event_id' => 'announcement:'.$announcementId]);
    }

    public function test_monitoring_snapshots_reject_secret_fields_and_are_audited(): void
    {
        $user = $this->userWithPermissions(['security.monitoring.record', 'security.monitoring.view']);
        $this->actingAs($user, 'sanctum');
        $base = ['check_type' => 'backup', 'status' => 'warning', 'source' => 'fictional backup monitor', 'summary' => 'Latest backup is older than the configured target.', 'checked_at' => now()->toIso8601String()];
        $this->postJson('/api/v1/security/monitoring-snapshots', [...$base, 'details' => ['backup_token' => 'must-not-be-stored']])->assertStatus(422);
        $this->postJson('/api/v1/security/monitoring-snapshots', $base)->assertCreated();
        $this->getJson('/api/v1/security/monitoring-snapshots')->assertOk()->assertJsonPath('data.data.0.check_type', 'backup');
        $this->assertDatabaseHas('security_events', ['category' => 'monitoring', 'severity' => 'warning']);
        $this->assertSame(0, Artisan::call('security:verify-audit-chains'));
    }

    public function test_identity_migration_can_resume_after_its_role_assignment_index_failed(): void
    {
        Schema::table('iam_user_role_assignments', fn ($table) => $table->dropIndex('iam_role_assignments_user_effective_idx'));
        $migration = require database_path('migrations/2026_11_05_121000_create_identity_access_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasIndex('iam_user_role_assignments', ['user_id', 'effective_from', 'effective_to']));
        $this->assertTrue(Schema::hasTable('iam_access_audits'));
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::query()->create(['code' => 'test-'.fake()->unique()->slug(2), 'name' => 'Test role', 'status' => 'active', 'version' => 1]);
        foreach ($permissions as $code) {
            $permission = Permission::query()->firstOrCreate(['code' => $code]);
            $role->permissions()->attach($permission->id);
        }
        $user = User::factory()->create(['status' => 'active', 'mfa_confirmed_at' => now()]);
        DB::table('iam_user_role_assignments')->insert(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => 'global', 'scope_id' => 'global', 'effective_from' => now()->subMinute(), 'reason' => 'Test fixture', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }
}

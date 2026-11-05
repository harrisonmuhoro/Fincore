<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverAnnouncement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SharedControlsController extends Controller
{
    public function documents(Request $request): JsonResponse
    {
        $query = DB::table('records_documents')->orderByDesc('created_at');
        if (! $request->user()->hasPermission('records.document.view_all')) {
            $query->where('owner_user_id', $request->user()->id);
        }

        return $this->respond($request, 'Documents retrieved.', $query->paginate($this->pageSize($request)));
    }

    public function uploadDocument(Request $request, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,png,jpg,jpeg,txt,csv,docx,xlsx'],
            'title' => ['required', 'string', 'max:200'],
            'classification' => ['nullable', 'string', 'max:80'],
            'sensitivity' => ['nullable', 'in:internal,restricted,confidential'],
            'owner_module' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'],
            'owner_record_id' => ['nullable', 'string', 'max:120'],
        ]);
        abort_unless(empty($data['owner_module']) === empty($data['owner_record_id']), 422, 'Both owner_module and owner_record_id must be supplied together.');
        $file = $data['file'];
        $path = 'documents/'.now()->format('Y/m').'/'.Str::uuid();
        $stored = Storage::disk('private')->putFileAs(dirname($path), $file, basename($path));
        abort_unless($stored, 500, 'Private document storage failed.');

        try {
            $id = DB::transaction(function () use ($request, $data, $file, $stored, $audit): int {
                $id = DB::table('records_documents')->insertGetId([
                    'owner_user_id' => $request->user()->id,
                    'title' => $data['title'],
                    'classification' => $data['classification'] ?? 'unclassified',
                    'sensitivity' => $data['sensitivity'] ?? 'internal',
                    'status' => 'quarantined',
                    'version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('records_document_versions')->insert([
                    'document_id' => $id,
                    'version' => 1,
                    'storage_path' => $stored,
                    'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                    'mime_type' => (string) $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'scan_status' => 'pending',
                    'uploaded_by' => $request->user()->id,
                    'created_at' => now(),
                ]);
                if (! empty($data['owner_module'])) {
                    DB::table('records_document_links')->insert([
                        'document_id' => $id, 'owner_module' => $data['owner_module'], 'owner_record_id' => $data['owner_record_id'],
                        'linked_by' => $request->user()->id, 'created_at' => now(),
                    ]);
                }
                $audit->record($request, 'documents', 'document.uploaded', 'records_document', $id, ['version' => 1, 'scan_status' => 'pending']);
                $this->documentAccess($request, $id, 'upload');

                return $id;
            });
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete($stored);
            throw $exception;
        }

        return $this->respond($request, 'Document stored privately and quarantined pending an authorized scan result.', $this->documentPayload($id), 201);
    }

    public function document(Request $request, int $document, AuditRecorder $audit): JsonResponse
    {
        $record = $this->authorizeDocument($request, $document);
        $this->documentAccess($request, $document, 'view');
        $audit->record($request, 'documents', 'document.viewed', 'records_document', $document);

        return $this->respond($request, 'Document retrieved.', $this->documentPayload($record->id));
    }

    public function uploadDocumentVersion(Request $request, int $document, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:pdf,png,jpg,jpeg,txt,csv,docx,xlsx']]);
        $record = DB::table('records_documents')->where('id', $document)->firstOrFail();
        abort_unless((int) $record->owner_user_id === (int) $request->user()->id, 403);
        abort_unless($record->status !== 'disposed', 409);
        $file = $data['file'];
        $path = 'documents/'.now()->format('Y/m').'/'.Str::uuid();
        $stored = Storage::disk('private')->putFileAs(dirname($path), $file, basename($path));
        abort_unless($stored, 500, 'Private document storage failed.');
        try {
            DB::transaction(function () use ($request, $document, $file, $stored, $audit): void {
                $record = DB::table('records_documents')->where('id', $document)->lockForUpdate()->firstOrFail();
                abort_unless((int) $record->owner_user_id === (int) $request->user()->id && $record->status !== 'disposed', 409);
                $version = (int) DB::table('records_document_versions')->where('document_id', $document)->max('version') + 1;
                DB::table('records_document_versions')->insert([
                    'document_id' => $document, 'version' => $version, 'storage_path' => $stored,
                    'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'mime_type' => (string) $file->getMimeType(),
                    'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'scan_status' => 'pending',
                    'uploaded_by' => $request->user()->id, 'created_at' => now(),
                ]);
                DB::table('records_documents')->where('id', $document)->update(['version' => $record->version + 1, 'status' => 'quarantined', 'updated_at' => now()]);
                $this->documentAccess($request, $document, 'version_upload');
                $audit->record($request, 'documents', 'document.version_uploaded', 'records_document', $document, ['file_version' => $version, 'scan_status' => 'pending']);
            });
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete($stored);
            throw $exception;
        }

        return $this->respond($request, 'New document version stored privately and quarantined for scanning.', $this->documentPayload($document), 201);
    }

    public function classifyDocument(Request $request, int $document, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'classification' => ['sometimes', 'required', 'string', 'max:80'],
            'sensitivity' => ['sometimes', 'required', 'in:internal,restricted,confidential'],
            'retention_until' => ['sometimes', 'nullable', 'date'],
            'expected_version' => ['required', 'integer'],
        ]);
        DB::transaction(function () use ($request, $document, $data, $audit): void {
            $record = DB::table('records_documents')->where('id', $document)->lockForUpdate()->firstOrFail();
            abort_unless((int) $record->owner_user_id === (int) $request->user()->id, 403);
            abort_unless($record->status !== 'disposed', 409);
            abort_unless((int) $record->version === (int) $data['expected_version'], 409, 'Document version is stale.');
            $updates = array_intersect_key($data, array_flip(['title', 'classification', 'sensitivity', 'retention_until']));
            $updates['version'] = $record->version + 1;
            $updates['updated_at'] = now();
            DB::table('records_documents')->where('id', $document)->update($updates);
            $audit->record($request, 'documents', 'document.classified', 'records_document', $document, ['version' => $updates['version']]);
        });

        return $this->respond($request, 'Document metadata updated.', $this->documentPayload($document));
    }

    public function scanResult(Request $request, int $document, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate(['result' => ['required', 'in:clean,infected'], 'scanner' => ['required', 'string', 'max:100'], 'evidence' => ['nullable', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $document, $data, $audit): void {
            DB::table('records_documents')->where('id', $document)->lockForUpdate()->firstOrFail();
            $version = DB::table('records_document_versions')->where('document_id', $document)->orderByDesc('version')->lockForUpdate()->firstOrFail();
            abort_unless($version->scan_status === 'pending', 409);
            DB::table('records_document_versions')->where('id', $version->id)->update(['scan_status' => $data['result']]);
            DB::table('records_documents')->where('id', $document)->update(['status' => $data['result'] === 'clean' ? 'active' : 'quarantined', 'updated_at' => now()]);
            $audit->record($request, 'documents', 'document.scan_result', 'records_document', $document, ['result' => $data['result'], 'scanner' => $data['scanner'], 'evidence' => $data['evidence'] ?? null]);
        });

        return $this->respond($request, 'Document scan result recorded.', $this->documentPayload($document));
    }

    public function downloadDocument(Request $request, int $document, AuditRecorder $audit)
    {
        $record = $this->authorizeDocument($request, $document);
        abort_unless($record->status === 'active', 409, 'Only scanned clean documents may be downloaded.');
        $version = DB::table('records_document_versions')->where('document_id', $document)->orderByDesc('version')->firstOrFail();
        abort_unless($version->scan_status === 'clean', 409);
        abort_unless(Storage::disk('private')->exists($version->storage_path), 404);
        $this->documentAccess($request, $document, 'download');
        $audit->record($request, 'documents', 'document.downloaded', 'records_document', $document, ['version' => $version->version]);

        return Storage::disk('private')->download($version->storage_path, $version->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function setDocumentHold(Request $request, int $document, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate(['legal_hold' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:8', 'max:500']]);
        DB::transaction(function () use ($request, $document, $data, $audit): void {
            $record = DB::table('records_documents')->where('id', $document)->lockForUpdate()->firstOrFail();
            DB::table('records_documents')->where('id', $document)->update(['legal_hold' => $data['legal_hold'], 'version' => $record->version + 1, 'updated_at' => now()]);
            $audit->record($request, 'documents', $data['legal_hold'] ? 'document.hold_set' : 'document.hold_released', 'records_document', $document, ['reason' => $data['reason']]);
        });

        return $this->respond($request, 'Document hold updated.', $this->documentPayload($document));
    }

    public function notifications(Request $request): JsonResponse
    {
        $query = DB::table('user_notifications')->where('user_id', $request->user()->id)->orderByDesc('created_at');
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return $this->respond($request, 'Notifications retrieved.', $query->paginate($this->pageSize($request)));
    }

    public function markNotificationRead(Request $request, int $notification, AuditRecorder $audit): JsonResponse
    {
        $updated = DB::table('user_notifications')->where('id', $notification)->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        abort_unless($updated || DB::table('user_notifications')->where('id', $notification)->where('user_id', $request->user()->id)->exists(), 404);
        $audit->record($request, 'notifications', 'notification.read', 'user_notification', $notification);

        return $this->respond($request, 'Notification marked as read.', DB::table('user_notifications')->where('id', $notification)->first());
    }

    public function preferences(Request $request): JsonResponse
    {
        return $this->respond($request, 'Notification preferences retrieved.', DB::table('notification_preferences')->where('user_id', $request->user()->id)->orderBy('category')->get());
    }

    public function updatePreference(Request $request, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate(['category' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_.-]*$/'], 'in_app_enabled' => ['required', 'boolean'], 'email_enabled' => ['required', 'boolean'], 'expected_version' => ['nullable', 'integer']]);
        abort_unless($data['in_app_enabled'] || $data['email_enabled'], 422, 'At least one notification channel must remain enabled.');
        DB::transaction(function () use ($request, $data, $audit): void {
            $row = DB::table('notification_preferences')->where('user_id', $request->user()->id)->where('category', $data['category'])->lockForUpdate()->first();
            if ($row) {
                abort_unless((int) $row->version === (int) ($data['expected_version'] ?? 0), 409, 'Preference version is stale.');
                DB::table('notification_preferences')->where('id', $row->id)->update(['in_app_enabled' => $data['in_app_enabled'], 'email_enabled' => $data['email_enabled'], 'version' => $row->version + 1, 'updated_at' => now()]);
            } else {
                abort_unless(! isset($data['expected_version']), 409);
                DB::table('notification_preferences')->insert(['user_id' => $request->user()->id, 'category' => $data['category'], 'in_app_enabled' => $data['in_app_enabled'], 'email_enabled' => $data['email_enabled'], 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
            $audit->record($request, 'notifications', 'preference.updated', 'notification_preference', $data['category']);
        });

        return $this->respond($request, 'Notification preference saved.', DB::table('notification_preferences')->where('user_id', $request->user()->id)->where('category', $data['category'])->first());
    }

    public function announcements(Request $request): JsonResponse
    {
        $query = DB::table('notification_announcements')->orderByDesc('created_at');
        if (! $request->user()->hasPermission('notification.announcement.create')) {
            $query->where('status', 'published');
        }

        return $this->respond($request, 'Announcements retrieved.', $query->paginate($this->pageSize($request)));
    }

    public function createAnnouncement(Request $request, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'body' => ['required', 'string', 'max:10000']]);
        $id = DB::table('notification_announcements')->insertGetId(['title' => $data['title'], 'body' => $data['body'], 'status' => 'draft', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        $audit->record($request, 'notifications', 'announcement.created', 'notification_announcement', $id);

        return $this->respond($request, 'Announcement saved as a draft.', DB::table('notification_announcements')->where('id', $id)->first(), 201);
    }

    public function publishAnnouncement(Request $request, int $announcement, AuditRecorder $audit): JsonResponse
    {
        $record = DB::transaction(function () use ($request, $announcement, $audit) {
            $record = DB::table('notification_announcements')->where('id', $announcement)->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'draft', 409);
            abort_unless((int) $record->created_by !== (int) $request->user()->id, 403, 'The announcement author cannot publish it.');
            DB::table('notification_announcements')->where('id', $announcement)->update(['status' => 'published', 'published_by' => $request->user()->id, 'published_at' => now(), 'updated_at' => now()]);
            $audit->record($request, 'notifications', 'announcement.published', 'notification_announcement', $announcement);
            DeliverAnnouncement::dispatch($announcement)->afterCommit();

            return DB::table('notification_announcements')->where('id', $announcement)->first();
        });

        return $this->respond($request, 'Announcement published; recipient delivery is queued.', $record, 202);
    }

    public function securityEvents(Request $request): JsonResponse
    {
        $query = DB::table('security_events')->orderByDesc('occurred_at');
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        return $this->respond($request, 'Security events retrieved.', $query->paginate($this->pageSize($request)));
    }

    public function monitoringSnapshots(Request $request): JsonResponse
    {
        return $this->respond($request, 'Operational monitoring snapshots retrieved.', DB::table('security_monitoring_snapshots')->orderByDesc('checked_at')->paginate($this->pageSize($request)));
    }

    public function recordMonitoringSnapshot(Request $request, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate([
            'check_type' => ['required', 'in:backup,application,queue,storage'],
            'status' => ['required', 'in:ok,warning,failed'],
            'source' => ['required', 'string', 'max:100'],
            'summary' => ['required', 'string', 'max:500'],
            'details' => ['sometimes', 'array', 'max:30'],
            'checked_at' => ['required', 'date'],
        ]);
        $this->assertSafeMonitoringDetails($data['details'] ?? []);
        $id = DB::transaction(function () use ($request, $data, $audit): int {
            $id = DB::table('security_monitoring_snapshots')->insertGetId([
                'check_type' => $data['check_type'], 'status' => $data['status'], 'source' => $data['source'],
                'summary' => $data['summary'], 'details' => isset($data['details']) ? json_encode($data['details'], JSON_THROW_ON_ERROR) : null,
                'recorded_by' => $request->user()->id, 'checked_at' => $data['checked_at'], 'created_at' => now(),
            ]);
            DB::table('security_events')->insert([
                'event_key' => (string) Str::uuid(), 'category' => 'monitoring', 'severity' => $data['status'] === 'failed' ? 'error' : ($data['status'] === 'warning' ? 'warning' : 'info'),
                'actor_id' => $request->user()->id, 'request_id' => $request->attributes->get('request_id'), 'ip_address' => $request->ip(),
                'details' => json_encode(['check_type' => $data['check_type'], 'status' => $data['status'], 'snapshot_id' => $id], JSON_THROW_ON_ERROR), 'occurred_at' => now(),
            ]);
            $audit->record($request, 'security', 'monitoring.snapshot_recorded', 'security_monitoring_snapshot', $id, ['check_type' => $data['check_type'], 'status' => $data['status']]);

            return $id;
        });

        return $this->respond($request, 'Monitoring snapshot recorded.', DB::table('security_monitoring_snapshots')->where('id', $id)->first(), 201);
    }

    public function securityIncidents(Request $request): JsonResponse
    {
        return $this->respond($request, 'Security incidents retrieved.', DB::table('security_incidents')->orderByDesc('updated_at')->paginate($this->pageSize($request)));
    }

    public function createSecurityIncident(Request $request, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['required', 'string', 'max:5000'], 'severity' => ['required', 'in:low,medium,high,critical'], 'event_id' => ['nullable', 'integer', 'exists:security_events,id']]);
        $id = DB::transaction(function () use ($request, $data, $audit): int {
            $id = DB::table('security_incidents')->insertGetId(['title' => $data['title'], 'description' => $data['description'], 'severity' => $data['severity'], 'status' => 'open', 'created_by' => $request->user()->id, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('security_incident_history')->insert(['incident_id' => $id, 'actor_id' => $request->user()->id, 'action' => 'created', 'to_status' => 'open', 'reason' => 'Incident opened.', 'request_id' => $request->attributes->get('request_id'), 'occurred_at' => now()]);
            $audit->record($request, 'security', 'incident.created', 'security_incident', $id, ['severity' => $data['severity'], 'event_id' => $data['event_id'] ?? null]);

            return $id;
        });

        return $this->respond($request, 'Security incident opened.', DB::table('security_incidents')->where('id', $id)->first(), 201);
    }

    public function transitionSecurityIncident(Request $request, int $incident, AuditRecorder $audit): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:assign,investigate,contain,resolve,close'], 'reason' => ['required', 'string', 'min:8', 'max:500'], 'assigned_to' => ['nullable', 'integer', 'exists:users,id'], 'expected_version' => ['required', 'integer']]);
        $record = DB::transaction(function () use ($request, $incident, $data, $audit) {
            $row = DB::table('security_incidents')->where('id', $incident)->lockForUpdate()->firstOrFail();
            $requiredPermission = ['assign' => 'security.incident.assign', 'investigate' => 'security.incident.investigate', 'contain' => 'security.incident.contain', 'resolve' => 'security.incident.resolve', 'close' => 'security.incident.close'][$data['action']];
            abort_unless($request->user()->hasPermission($requiredPermission), 403);
            abort_unless((int) $row->version === (int) $data['expected_version'], 409, 'Incident version is stale.');
            $map = ['assign' => ['open', 'assigned'], 'investigate' => ['assigned', 'investigating'], 'contain' => ['investigating', 'contained'], 'resolve' => ['contained', 'resolved'], 'close' => ['resolved', 'closed']];
            [$from, $to] = $map[$data['action']];
            abort_unless($row->status === $from, 409, 'Incident cannot make this transition from its current status.');
            if (in_array($data['action'], ['contain', 'resolve', 'close'], true)) {
                abort_unless((int) $row->created_by !== (int) $request->user()->id, 403, 'The incident reporter cannot perform this independent control action.');
            }
            $fields = ['status' => $to, 'version' => $row->version + 1, 'updated_at' => now()];
            if ($data['action'] === 'assign') {
                $fields['assigned_to'] = $data['assigned_to'] ?? $request->user()->id;
            }
            if ($data['action'] === 'resolve') {
                $fields['resolution_reason'] = $data['reason'];
                $fields['resolved_at'] = now();
            }
            DB::table('security_incidents')->where('id', $incident)->update($fields);
            DB::table('security_incident_history')->insert(['incident_id' => $incident, 'actor_id' => $request->user()->id, 'action' => $data['action'], 'from_status' => $from, 'to_status' => $to, 'reason' => $data['reason'], 'request_id' => $request->attributes->get('request_id'), 'occurred_at' => now()]);
            $audit->record($request, 'security', 'incident.'.$data['action'], 'security_incident', $incident, ['from' => $from, 'to' => $to, 'reason' => $data['reason']]);

            return DB::table('security_incidents')->where('id', $incident)->first();
        });

        return $this->respond($request, 'Security incident updated.', $record);
    }

    private function authorizeDocument(Request $request, int $id): object
    {
        $record = DB::table('records_documents')->where('id', $id)->firstOrFail();
        abort_unless((int) $record->owner_user_id === (int) $request->user()->id || $request->user()->hasPermission('records.document.view_all'), 403);

        return $record;
    }

    private function documentAccess(Request $request, int $id, string $action): void
    {
        DB::table('records_document_access_events')->insert(['document_id' => $id, 'actor_id' => $request->user()?->id, 'action' => $action, 'request_id' => $request->attributes->get('request_id'), 'ip_address' => $request->ip(), 'occurred_at' => now()]);
    }

    private function documentPayload(int $id): array
    {
        $document = DB::table('records_documents')->where('id', $id)->first();
        $document->versions = DB::table('records_document_versions')->where('document_id', $id)->orderByDesc('version')->get(['version', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'scan_status', 'uploaded_by', 'created_at']);
        $document->links = DB::table('records_document_links')->where('document_id', $id)->get(['owner_module', 'owner_record_id', 'linked_by', 'created_at']);

        return (array) $document;
    }

    private function pageSize(Request $request): int
    {
        return min(max($request->integer('per_page', 25), 1), 100);
    }

    private function assertSafeMonitoringDetails(array $details): void
    {
        foreach ($details as $key => $value) {
            abort_unless(! preg_match('/password|secret|token|credential|authorization|private.?key/i', (string) $key), 422, 'Monitoring details cannot contain secret-bearing fields.');
            if (is_array($value)) {
                $this->assertSafeMonitoringDetails($value);
            }
        }
    }

    private function respond(Request $request, string $message, mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data, 'meta' => ['request_id' => $request->attributes->get('request_id')]], $status);
    }
}

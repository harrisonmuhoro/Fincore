<?php

namespace App\Console\Commands;

use App\Domain\Notifications\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConsumeNotificationEvents extends Command
{
    protected $signature = 'notifications:consume-domain-events {--limit=100}';

    protected $description = 'Create recipient notifications for supported domain outbox events with idempotent receipts.';

    public function handle(NotificationService $notifications): int
    {
        $limit = min(max((int) $this->option('limit'), 1), 1000);
        $events = DB::table('domain_outbox_events as outbox')
            ->leftJoin('notification_event_receipts as receipts', 'receipts.event_id', '=', 'outbox.event_id')
            ->whereNull('receipts.event_id')
            ->whereIn('outbox.event_type', ['ApprovalCompleted'])
            ->orderBy('outbox.occurred_at')
            ->limit($limit)
            ->get(['outbox.event_id', 'outbox.event_type', 'outbox.entity_type', 'outbox.entity_id', 'outbox.payload']);
        $processed = 0;
        foreach ($events as $event) {
            $payload = is_array($event->payload) ? $event->payload : json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR);
            if ($event->entity_type === 'workflow_instance') {
                $recipient = DB::table('workflow_instances')->where('id', $event->entity_id)->value('requested_by');
                if ($recipient) {
                    $outcome = ucfirst((string) ($payload['outcome'] ?? 'updated'));
                    $notifications->deliver((int) $recipient, $event->event_id, 'workflow', 'Approval '.$outcome, 'A workflow item you submitted has been '.$outcome.'.', ['workflow_instance_id' => (int) $event->entity_id, 'outcome' => $payload['outcome'] ?? null]);
                }
            }
            DB::table('notification_event_receipts')->insertOrIgnore(['event_id' => $event->event_id, 'processed_at' => now()]);
            $processed++;
        }
        $this->info("Processed {$processed} notification events.");

        return self::SUCCESS;
    }
}

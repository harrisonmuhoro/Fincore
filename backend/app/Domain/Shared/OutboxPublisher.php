<?php

namespace App\Domain\Shared;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OutboxPublisher
{
    public function record(string $type, string $producer, string $entityType, string|int $entityId, array $payload, ?string $correlationId = null, ?string $causationId = null): string
    {
        $eventId = (string) Str::uuid();
        $correlationId = $correlationId && Str::isUuid($correlationId) ? $correlationId : (string) Str::uuid();
        $causationId = $causationId && Str::isUuid($causationId) ? $causationId : null;
        DB::table('domain_outbox_events')->insert([
            'event_id' => $eventId,
            'event_type' => $type,
            'event_version' => 1,
            'producer' => $producer,
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'correlation_id' => $correlationId,
            'causation_id' => $causationId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
            'attempts' => 0,
        ]);

        return $eventId;
    }
}

<?php

namespace App\Domain\Shared;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OutboxPublisher
{
    public function record(string $type, string $producer, string $entityType, string|int $entityId, array $payload, ?string $correlationId = null, ?string $causationId = null): string
    {
        $eventId = (string) Str::uuid();
        $correlationId = $correlationId ? $this->toUuid($correlationId) : (string) Str::uuid();
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

    private function toUuid(string $value): string
    {
        if (Str::isUuid($value)) {
            return $value;
        }
        $hex = substr(hash('sha256', $value), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x03) | 0x08);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }
}

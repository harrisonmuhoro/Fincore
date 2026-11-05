<?php

namespace App\Domain\Shared;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditRecorder
{
    public function record(Request $request, string $module, string $action, ?string $entityType = null, string|int|null $entityId = null, array $details = []): void
    {
        DB::transaction(function () use ($request, $module, $action, $entityType, $entityId, $details): void {
            DB::table('module_audit_chain_heads')->insertOrIgnore(['module' => $module, 'updated_at' => now()]);
            $head = DB::table('module_audit_chain_heads')->where('module', $module)->lockForUpdate()->first();
            $canonicalDetails = $this->normalize($details);
            $fields = [
                'module' => $module,
                'actor_id' => $request->user()?->id,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId === null ? null : (string) $entityId,
                'request_id' => $request->attributes->get('request_id'),
                'ip_address' => $request->ip(),
                'details' => $canonicalDetails ? json_encode($canonicalDetails, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
                'occurred_at' => now()->startOfSecond(),
                'previous_hash' => $head->last_hash,
            ];
            $fields['event_hash'] = $this->hash($fields);
            $id = DB::table('module_audit_events')->insertGetId($fields);
            DB::table('module_audit_chain_heads')->where('module', $module)->update(['last_event_id' => $id, 'last_hash' => $fields['event_hash'], 'updated_at' => now()]);
        });
    }

    public function hash(array $fields): string
    {
        $details = $fields['details'] ?? null;
        if (is_string($details)) {
            $details = json_decode($details, true, 512, JSON_THROW_ON_ERROR);
        }
        $details = $this->normalize($details);
        $canonical = [
            'module' => (string) $fields['module'],
            'actor_id' => $fields['actor_id'] === null ? null : (int) $fields['actor_id'],
            'action' => (string) $fields['action'],
            'entity_type' => $fields['entity_type'] === null ? null : (string) $fields['entity_type'],
            'entity_id' => $fields['entity_id'] === null ? null : (string) $fields['entity_id'],
            'request_id' => $fields['request_id'] === null ? null : (string) $fields['request_id'],
            'ip_address' => $fields['ip_address'] === null ? null : (string) $fields['ip_address'],
            'details' => $details,
            'occurred_at' => is_string($fields['occurred_at']) ? $fields['occurred_at'] : $fields['occurred_at']->format('Y-m-d H:i:s'),
            'previous_hash' => $fields['previous_hash'] === null ? null : (string) $fields['previous_hash'],
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }
}

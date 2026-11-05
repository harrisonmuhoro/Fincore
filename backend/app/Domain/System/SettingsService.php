<?php

namespace App\Domain\System;

use App\Models\SystemConfigurationAudit;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class SettingsService
{
    public function update(string $key, string $scopeType, string $scopeId, mixed $value, string $type, string $reason, int $expectedVersion, ?int $actorId, ?string $requestId): SystemSetting
    {
        if (str_contains(strtolower($key), 'secret') || str_contains(strtolower($key), 'password') || str_contains(strtolower($key), 'token')) {
            throw ValidationException::withMessages(['key' => ['Secret values must be managed through environment or secret-management configuration.']]);
        }
        if (! $this->matchesType($value, $type)) {
            throw ValidationException::withMessages(['value' => ['Value does not match the declared type.']]);
        }

        try {
            return DB::transaction(function () use ($key, $scopeType, $scopeId, $value, $type, $reason, $expectedVersion, $actorId, $requestId): SystemSetting {
            $current = SystemSetting::query()->where('setting_key', $key)->where('scope_type', $scopeType)->where('scope_id', $scopeId)
                ->where('status', 'active')->whereNull('effective_to')->lockForUpdate()->first();
            if (($current?->version ?? 0) !== $expectedVersion) {
                abort(409, 'The setting changed since it was read.');
            }

            $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $afterHash = hash('sha256', $encoded);
            $now = now();
            if ($current) {
                $current->forceFill(['status' => 'superseded', 'effective_to' => $now])->save();
            }
            $setting = SystemSetting::query()->create([
                'setting_key' => $key,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'value_json' => $value,
                'value_type' => $type,
                'effective_from' => $now,
                'status' => 'active',
                'version' => $expectedVersion + 1,
                'value_hash' => $afterHash,
                'created_by' => $actorId,
            ]);
            SystemConfigurationAudit::query()->create([
                'setting_key' => $key,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'before_hash' => $current?->value_hash,
                'after_hash' => $afterHash,
                'version' => $setting->version,
                'actor_id' => $actorId,
                'reason' => $reason,
                'request_id' => $requestId,
                'changed_at' => $now,
            ]);

            return $setting;
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                abort(409, 'A concurrent setting version was created. Reload and try again.');
            }
            throw $exception;
        }
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'number' => is_int($value) || is_float($value),
            'object' => is_array($value) && ! array_is_list($value),
            'array' => is_array($value),
            default => false,
        };
    }
}

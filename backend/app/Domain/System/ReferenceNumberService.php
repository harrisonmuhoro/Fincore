<?php

namespace App\Domain\System;

use App\Models\ReferenceSequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReferenceNumberService
{
    public function issue(string $namespace, string $periodKey = 'global', int $padding = 6): string
    {
        if (! preg_match('/^[a-zA-Z0-9._-]{1,100}$/', $namespace) || ! preg_match('/^[a-zA-Z0-9._-]{1,100}$/', $periodKey)) {
            throw new InvalidArgumentException('Sequence namespace and period key must use letters, numbers, dots, underscores or hyphens.');
        }

        if ($padding < 1 || $padding > 18) {
            throw new InvalidArgumentException('Sequence padding must be between 1 and 18.');
        }

        return DB::transaction(function () use ($namespace, $periodKey, $padding): string {
            ReferenceSequence::query()->insertOrIgnore([
                'namespace' => $namespace,
                'period_key' => $periodKey,
                'next_value' => 1,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = ReferenceSequence::query()
                ->where('namespace', $namespace)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->firstOrFail();

            $issued = $sequence->next_value;
            $sequence->increment('next_value');
            $sequence->increment('version');

            return str_pad((string) $issued, $padding, '0', STR_PAD_LEFT);
        }, attempts: 5);
    }
}

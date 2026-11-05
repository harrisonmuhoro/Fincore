<?php

namespace App\Domain\Finance;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialPeriodGuard
{
    public function assertOpen(int $periodId): void
    {
        $period = DB::table('accounting_periods')->where('id', $periodId)->first();
        if (! $period) {
            throw ValidationException::withMessages(['accounting_period_id' => ['The accounting period does not exist.']]);
        }
        if ($period->status !== 'open') {
            abort(409, 'Posting is blocked because the accounting period is not open.');
        }
    }
}

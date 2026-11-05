<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController
{
    public function live(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Application is responsive.',
            'data' => ['status' => 'ok'],
            'meta' => ['request_id' => request()->attributes->get('request_id')],
        ]);
    }

    public function ready(): JsonResponse
    {
        $checks = ['database' => false];
        try {
            DB::select('select 1');
            $checks['database'] = true;
        } catch (Throwable) {
            // Readiness reports only the dependency name and state.
        }

        if (config('fincore.redis_required')) {
            $checks['redis'] = false;
            try {
                Redis::connection()->ping();
                $checks['redis'] = true;
            } catch (Throwable) {
                // Never expose connection details or credentials in health output.
            }
        }

        $ready = ! in_array(false, $checks, true);

        return response()->json([
            'success' => $ready,
            'message' => $ready ? 'Required dependencies are available.' : 'One or more required dependencies are unavailable.',
            'data' => ['status' => $ready ? 'ready' : 'not_ready', 'dependencies' => $checks],
            'meta' => ['request_id' => request()->attributes->get('request_id')],
        ], $ready ? 200 : 503);
    }
}

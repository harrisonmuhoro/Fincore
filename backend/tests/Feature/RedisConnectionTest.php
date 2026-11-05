<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisConnectionTest extends TestCase
{
    public function test_redis_connection_is_available_when_integration_check_is_enabled(): void
    {
        if (! filter_var(env('RUN_REDIS_INTEGRATION', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Redis is verified by CI and can be enabled locally with RUN_REDIS_INTEGRATION=true.');
        }

        $response = Redis::connection()->ping();

        $this->assertContains(strtoupper((string) $response), ['PONG', '+PONG', '1']);
    }
}

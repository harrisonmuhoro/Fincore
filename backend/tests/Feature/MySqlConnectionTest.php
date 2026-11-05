<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlConnectionTest extends TestCase
{
    public function test_mysql_connection_is_available_when_integration_check_is_enabled(): void
    {
        if (! filter_var(env('RUN_MYSQL_INTEGRATION', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('MySQL connectivity is verified by CI and can be enabled locally with RUN_MYSQL_INTEGRATION=true.');
        }

        $database = env('MYSQL_INTEGRATION_DATABASE');
        $this->assertNotEmpty($database, 'Set MYSQL_INTEGRATION_DATABASE to an isolated test database.');
        config(['database.connections.mysql.database' => $database]);
        DB::purge('mysql');

        $result = DB::connection('mysql')->select('select 1 as connection_ready');

        $this->assertSame(1, (int) $result[0]->connection_ready);
    }
}

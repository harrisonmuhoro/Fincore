<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApplicationBootTest extends TestCase
{
    public function test_laravel_application_boots_and_serves_the_web_shell(): void
    {
        $this->get('/')->assertOk()->assertSee('FinCore County');
    }

    public function test_sanctum_protects_the_foundation_user_endpoint(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }
}

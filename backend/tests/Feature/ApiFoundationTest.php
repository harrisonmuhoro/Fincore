<?php

namespace Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    public function test_liveness_returns_the_versioned_success_envelope_and_request_id(): void
    {
        $response = $this->withHeader('X-Request-ID', 'phase0-check-01')->getJson('/api/v1/health/live');

        $response->assertOk()
            ->assertHeader('X-Request-ID', 'phase0-check-01')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('meta.request_id', 'phase0-check-01');
    }

    public function test_invalid_request_id_is_replaced_with_a_safe_identifier(): void
    {
        $response = $this->withHeader('X-Request-ID', "bad\r\nheader")->getJson('/api/v1/health/live');

        $response->assertOk();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->headers->get('X-Request-ID'));
    }

    public function test_readiness_checks_database_without_disclosing_connection_details(): void
    {
        $this->getJson('/api/v1/health/ready')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.dependencies.database', true);
    }

    public function test_api_validation_errors_use_the_documented_error_envelope(): void
    {
        Route::prefix('api/v1')->middleware('api')->post('/_test-validation', function () {
            throw ValidationException::withMessages(['name' => ['The name field is required.']]);
        });

        $this->postJson('/api/v1/_test-validation')
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonPath('errors.name.0', 'The name field is required.')
            ->assertJsonStructure(['meta' => ['request_id']]);
    }

    public function test_api_not_found_errors_do_not_expose_internal_details(): void
    {
        $this->getJson('/api/v1/not-a-route')
            ->assertNotFound()
            ->assertHeader('X-Request-ID')
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The requested resource was not found.')
            ->assertJsonStructure(['meta' => ['request_id']])
            ->assertJsonMissingPath('exception');
    }

    public function test_authorization_errors_use_forbidden_status_and_safe_message(): void
    {
        Route::prefix('api/v1')->middleware('api')->get('/_test-forbidden', function () {
            throw new AuthorizationException;
        });

        $this->getJson('/api/v1/_test-forbidden')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_cors_preflight_accepts_only_the_configured_frontend_origin(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/health/live')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}

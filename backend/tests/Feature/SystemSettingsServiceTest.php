<?php

namespace Tests\Feature;

use App\Domain\System\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SystemSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_versioned_and_audited_by_hash(): void
    {
        $service = app(SettingsService::class);
        $first = $service->update('ui.locale', 'global', 'global', 'en', 'string', 'Initial platform configuration', 0, null, null);
        $second = $service->update('ui.locale', 'global', 'global', 'sw', 'string', 'Approved locale change', 1, null, null);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertDatabaseHas('system_configuration_audits', ['setting_key' => 'ui.locale', 'version' => 2, 'before_hash' => $first->value_hash]);
    }

    public function test_settings_reject_type_mismatch_and_secret_names(): void
    {
        $service = app(SettingsService::class);
        try {
            $service->update('feature.enabled', 'global', 'global', 'yes', 'boolean', 'Bad type should fail', 0, null, null);
            $this->fail('Expected value type validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('value', $exception->errors());
        }
        $this->expectException(ValidationException::class);
        $service->update('mailer.password', 'global', 'global', 'value', 'string', 'Secret should fail', 0, null, null);
    }
}

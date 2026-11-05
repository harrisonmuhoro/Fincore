<?php

namespace Tests\Feature;

use App\Domain\Identity\TotpService;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_rfc6238_sha1_code_is_accepted_with_a_small_clock_window(): void
    {
        $service = new TotpService;

        $this->assertTrue($service->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082', 59));
        $this->assertFalse($service->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '000000', 59));
    }

    public function test_generated_secret_has_valid_base32_characters(): void
    {
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', (new TotpService)->generateSecret());
    }
}

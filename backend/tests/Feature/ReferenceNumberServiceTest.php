<?php

namespace Tests\Feature;

use App\Domain\System\ReferenceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ReferenceNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sequence_numbers_increase_and_are_scoped_to_namespace_and_period(): void
    {
        $service = app(ReferenceNumberService::class);

        $this->assertSame('000001', $service->issue('purchase-order', 'FY-demo'));
        $this->assertSame('000002', $service->issue('purchase-order', 'FY-demo'));
        $this->assertSame('000001', $service->issue('purchase-order', 'FY-next'));
        $this->assertDatabaseHas('reference_sequences', ['namespace' => 'purchase-order', 'period_key' => 'FY-demo', 'next_value' => 3]);
    }

    public function test_sequence_namespace_and_padding_are_validated(): void
    {
        $service = app(ReferenceNumberService::class);

        $this->expectException(InvalidArgumentException::class);
        $service->issue('../unsafe');
    }
}

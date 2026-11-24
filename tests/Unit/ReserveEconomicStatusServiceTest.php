<?php

namespace Tests\Unit;

use App\Services\ReserveEconomicStatusService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReserveEconomicStatusServiceTest extends TestCase
{
    private ReserveEconomicStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReserveEconomicStatusService;
    }

    #[Test]
    public function meeting_case_sets_12000_historical_3000_yields_net_sales_9000_and_effective_1000(): void
    {
        $result = $this->service->computeFromAggregates([
            'reserve_amount' => 10000,
            'sets_issued' => 12000,
            'returns_historical' => 3000,
        ]);

        $this->assertSame(12000.0, $result['sets_issued_amount']);
        $this->assertSame(3000.0, $result['returns_historical_amount']);
        $this->assertSame(1000.0, $result['returns_effective_amount']);
        $this->assertSame(9000.0, $result['net_sales_amount']);
        $this->assertSame(1000.0, $result['available_for_new_sets']);
    }

    #[Test]
    public function after_first_return_available_allows_reissue_of_2000(): void
    {
        $result = $this->service->computeFromAggregates([
            'reserve_amount' => 10000,
            'sets_issued' => 10000,
            'returns_historical' => 2000,
        ]);

        $this->assertSame(2000.0, $result['returns_effective_amount']);
        $this->assertSame(8000.0, $result['net_sales_amount']);
        $this->assertSame(2000.0, $result['available_for_new_sets']);
    }

    #[Test]
    public function after_reissue_before_second_return_available_is_zero(): void
    {
        $result = $this->service->computeFromAggregates([
            'reserve_amount' => 10000,
            'sets_issued' => 12000,
            'returns_historical' => 2000,
        ]);

        $this->assertSame(0.0, $result['returns_effective_amount']);
        $this->assertSame(10000.0, $result['net_sales_amount']);
        $this->assertSame(0.0, $result['available_for_new_sets']);
    }

    #[Test]
    public function naive_reserve_minus_historical_is_not_used_for_net_sales(): void
    {
        $result = $this->service->computeFromAggregates([
            'reserve_amount' => 10000,
            'sets_issued' => 12000,
            'returns_historical' => 3000,
        ]);

        $this->assertNotSame(7000.0, $result['net_sales_amount']);
        $this->assertSame(9000.0, $result['net_sales_amount']);
    }
}

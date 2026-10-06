<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\CreatesApplication;
use Tests\Support\Concerns\InteractsWithPrizePaymentApi;

abstract class PrizePaymentTestCase extends BaseTestCase
{
    use CreatesApplication;
    use InteractsWithPrizePaymentApi;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->databaseAvailable()) {
            $this->markTestSkipped('Se requiere MySQL (XAMPP) para pruebas de cobro de premios.');
        }
    }
}

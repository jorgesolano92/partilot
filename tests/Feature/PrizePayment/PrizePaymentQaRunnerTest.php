<?php

namespace Tests\Feature\PrizePayment;

use App\Services\Qa\PrizePaymentQaRunner;
use Tests\Support\PrizePaymentTestCase;

class PrizePaymentQaRunnerTest extends PrizePaymentTestCase
{
    public function test_qa_runner_bootstrap_passes_core_checks(): void
    {
        $runner = app(PrizePaymentQaRunner::class);
        $results = $runner->run(['bootstrap' => true]);

        $this->assertNotEmpty($results, 'El runner debe devolver resultados');

        $failed = array_filter($results, fn ($r) => ! $r['passed']);
        $failedSummary = collect($failed)
            ->map(fn ($r) => "{$r['id']}: {$r['name']} — {$r['message']}")
            ->implode("\n");

        $this->assertSame(
            0,
            count($failed),
            "Checks fallidos:\n".$failedSummary
        );
    }

    public function test_schema_checks_always_run(): void
    {
        $runner = app(PrizePaymentQaRunner::class);
        $results = $runner->run(['bootstrap' => false]);

        $schema = collect($results)->firstWhere('id', 'A.1');
        $this->assertNotNull($schema);
        $this->assertTrue($schema['passed'], $schema['message'] ?? 'Tabla settings');
    }
}

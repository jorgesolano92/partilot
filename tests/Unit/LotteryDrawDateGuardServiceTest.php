<?php

namespace Tests\Unit;

use App\Models\Lottery;
use App\Services\LotteryDrawDateGuardService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LotteryDrawDateGuardServiceTest extends TestCase
{
    #[Test]
    public function blocks_mutation_when_draw_date_has_passed(): void
    {
        config(['lottery.enforce_draw_date_rules' => true]);

        $lottery = new Lottery([
            'draw_date' => Carbon::yesterday(),
        ]);

        $service = new LotteryDrawDateGuardService;

        $this->assertNotNull($service->mutationDeniedMessage($lottery));
    }

    #[Test]
    public function allows_mutation_when_draw_date_is_today_or_future(): void
    {
        config(['lottery.enforce_draw_date_rules' => true]);

        $service = new LotteryDrawDateGuardService;

        $today = new Lottery(['draw_date' => Carbon::today()]);
        $future = new Lottery(['draw_date' => Carbon::tomorrow()]);

        $this->assertNull($service->mutationDeniedMessage($today));
        $this->assertNull($service->mutationDeniedMessage($future));
    }

    #[Test]
    public function blocks_set_mutation_when_scrutiny_is_completed(): void
    {
        config(['lottery.enforce_draw_date_rules' => true]);

        $service = new LotteryDrawDateGuardService;

        $this->assertTrue(
            $service->hasCompletedScrutiny(
                new Lottery(['id' => 99]),
                null
            ) === false
        );

        $this->assertSame(
            'No se puede modificar: el escrutinio de este sorteo ya está completado.',
            $service->scrutinyBlockedMessage(null)
        );
    }
}

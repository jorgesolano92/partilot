<?php

namespace Tests\Unit;

use App\Models\Lottery;
use App\Models\Participation;
use App\Models\Reserve;
use App\Models\Set;
use App\Services\ParticipationGiftService;
use Carbon\Carbon;
use Mockery;
use Tests\TestCase;

class ParticipationGiftBeforeDrawTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cannot_gift_after_draw_date(): void
    {
        $lottery = new Lottery(['draw_date' => Carbon::yesterday()->toDateString()]);
        $reserve = new Reserve();
        $reserve->setRelation('lottery', $lottery);
        $set = new Set();
        $set->setRelation('reserve', $reserve);

        $participation = Mockery::mock(Participation::class)->makePartial();
        $participation->shouldReceive('loadMissing')->andReturnSelf();
        $participation->setRelation('set', $set);

        $inbox = Mockery::mock(\App\Services\AppInboxNotificationService::class);
        $mail = Mockery::mock(\App\Services\CommunicationEmailService::class);
        $service = new ParticipationGiftService($inbox, $mail);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No se pueden regalar participaciones de un sorteo ya celebrado.');
        $service->assertGiftableBeforeDraw($participation);
    }

    public function test_can_gift_before_draw_date(): void
    {
        $lottery = new Lottery(['draw_date' => Carbon::tomorrow()->toDateString()]);
        $reserve = new Reserve();
        $reserve->setRelation('lottery', $lottery);
        $set = new Set();
        $set->setRelation('reserve', $reserve);

        $participation = Mockery::mock(Participation::class)->makePartial();
        $participation->shouldReceive('loadMissing')->andReturnSelf();
        $participation->setRelation('set', $set);

        $inbox = Mockery::mock(\App\Services\AppInboxNotificationService::class);
        $mail = Mockery::mock(\App\Services\CommunicationEmailService::class);
        $service = new ParticipationGiftService($inbox, $mail);

        $this->assertTrue($service->isGiftableBeforeDraw($participation));
    }
}

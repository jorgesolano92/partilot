<?php

namespace Tests\Unit;

use App\Models\EntityLotteryPrizeSetting;
use App\Models\Participation;
use App\Services\EntityLotteryPrizePaymentService;
use Mockery;
use Tests\TestCase;

class EntityLotteryPrizePaymentServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_online_collection_blocked_when_no_settings(): void
    {
        $participation = Mockery::mock(Participation::class)->makePartial();
        $participation->id = 1;
        $participation->entity_id = 10;
        $participation->collected_at = null;
        $participation->donated_at = null;
        $participation->status = 'vendida';
        $participation->participation_code = '1D/ABC';

        $service = Mockery::mock(EntityLotteryPrizePaymentService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveLotteryId')->andReturn(5);
        $service->shouldReceive('getSettings')->with(10, 5)->andReturn(null);
        $service->shouldReceive('isCollectedOrReservedOnline')->andReturn(false);

        $result = $service->evaluateOnlineCollection($participation, 25.0);

        $this->assertFalse($result['cobrable']);
        $this->assertSame('no_settings', $result['block_reason']);
    }

    public function test_online_collection_allowed_when_mode_online_and_enabled(): void
    {
        $setting = new EntityLotteryPrizeSetting([
            'prize_payment_mode' => EntityLotteryPrizeSetting::MODE_ONLINE,
            'online_payments_enabled' => true,
            'funds_status' => EntityLotteryPrizeSetting::FUNDS_CONFIRMED,
            'contract_status' => EntityLotteryPrizeSetting::CONTRACT_SIGNED,
            'blocked_user_message' => EntityLotteryPrizeSetting::DEFAULT_BLOCKED_MESSAGE,
            'unlocked_user_message' => EntityLotteryPrizeSetting::DEFAULT_UNLOCKED_MESSAGE,
        ]);

        $participation = Mockery::mock(Participation::class)->makePartial();
        $participation->id = 2;
        $participation->entity_id = 20;
        $participation->collected_at = null;
        $participation->donated_at = null;
        $participation->status = 'vendida';
        $participation->participation_code = '1D/XYZ';

        $service = Mockery::mock(EntityLotteryPrizePaymentService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveLotteryId')->andReturn(5);
        $service->shouldReceive('getSettings')->with(20, 5)->andReturn($setting);
        $service->shouldReceive('isCollectedOrReservedOnline')->andReturn(false);

        $result = $service->evaluateOnlineCollection($participation, 40.0);

        $this->assertTrue($result['cobrable']);
        $this->assertFalse($result['payment_blocked']);
        $this->assertStringContainsString('40,00', (string) $result['user_message']);
    }

    public function test_presencial_blocked_for_native_digital(): void
    {
        $participation = Mockery::mock(Participation::class)->makePartial();
        $participation->participation_code = '1D/DIGITAL';
        $participation->shouldReceive('loadMissing')->andReturnSelf();
        $participation->setRelation('set', null);

        $service = new EntityLotteryPrizePaymentService();
        $result = $service->evaluatePresencialPayment($participation, 10.0);

        $this->assertFalse($result['allowed']);
        $this->assertSame('native_digital', $result['reason']);
    }

    public function test_digital_under_presencial_mode_blocked_until_activated(): void
    {
        $setting = new EntityLotteryPrizeSetting([
            'prize_payment_mode' => EntityLotteryPrizeSetting::MODE_PRESENCIAL,
            'has_sold_digital_participations' => true,
            'online_payments_enabled' => false,
            'funds_status' => EntityLotteryPrizeSetting::FUNDS_CONFIRMED,
            'contract_status' => EntityLotteryPrizeSetting::CONTRACT_SIGNED,
            'blocked_user_message' => EntityLotteryPrizeSetting::DEFAULT_BLOCKED_MESSAGE,
            'unlocked_user_message' => EntityLotteryPrizeSetting::DEFAULT_UNLOCKED_MESSAGE,
        ]);

        $participation = Mockery::mock(Participation::class)->makePartial();
        $participation->id = 3;
        $participation->entity_id = 30;
        $participation->participation_code = '1D/DIG';
        $participation->collected_at = null;
        $participation->donated_at = null;
        $participation->status = 'vendida';

        $service = Mockery::mock(EntityLotteryPrizePaymentService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveLotteryId')->andReturn(5);
        $service->shouldReceive('getSettings')->with(30, 5)->andReturn($setting);
        $service->shouldReceive('isCollectedOrReservedOnline')->andReturn(false);
        $service->shouldReceive('isNativeDigitalParticipation')->andReturn(true);

        $result = $service->evaluateOnlineCollection($participation, 15.0);

        $this->assertFalse($result['cobrable']);
        $this->assertSame('not_activated', $result['block_reason']);
    }

    public function test_online_entity_payer_allowed_without_partilot_gates(): void
    {
        $setting = new EntityLotteryPrizeSetting([
            'prize_payment_mode' => EntityLotteryPrizeSetting::MODE_ONLINE,
            'online_payer' => EntityLotteryPrizeSetting::PAYER_ENTITY,
            'online_payments_enabled' => true,
            'funds_status' => EntityLotteryPrizeSetting::FUNDS_NOT_REQUIRED,
            'contract_status' => EntityLotteryPrizeSetting::CONTRACT_NOT_REQUIRED,
            'unlocked_user_message' => EntityLotteryPrizeSetting::DEFAULT_UNLOCKED_MESSAGE,
        ]);

        $participation = Mockery::mock(Participation::class)->makePartial();
        $participation->id = 4;
        $participation->entity_id = 40;
        $participation->collected_at = null;
        $participation->donated_at = null;
        $participation->status = 'vendida';
        $participation->participation_code = '2D/PHYS';

        $service = Mockery::mock(EntityLotteryPrizePaymentService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveLotteryId')->andReturn(5);
        $service->shouldReceive('getSettings')->with(40, 5)->andReturn($setting);
        $service->shouldReceive('isCollectedOrReservedOnline')->andReturn(false);

        $result = $service->evaluateOnlineCollection($participation, 50.0);

        $this->assertTrue($result['cobrable']);
        $this->assertFalse($result['payment_blocked']);
    }
}

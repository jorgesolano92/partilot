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
        $service->shouldReceive('isCollectedOrReservedOnline')->andReturn(false);
        $service->shouldReceive('exceedsOnlineDecimoThreshold')->andReturn(false);
        $service->shouldReceive('getSettings')->with(10, 5)->andReturn(null);

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
        $service->shouldReceive('isCollectedOrReservedOnline')->andReturn(false);
        $service->shouldReceive('exceedsOnlineDecimoThreshold')->andReturn(false);
        $service->shouldReceive('getSettings')->with(20, 5)->andReturn($setting);

        $result = $service->evaluateOnlineCollection($participation, 40.0);

        $this->assertTrue($result['cobrable']);
        $this->assertFalse($result['payment_blocked']);
        $this->assertStringContainsString('40,00', (string) $result['user_message']);
    }

    public function test_presencial_blocked_for_native_digital(): void
    {
        $participation = Mockery::mock(Participation::class)->makePartial();
        $participation->participation_code = '1D/DIGITAL';
        $participation->wallet_mode = null;
        $participation->shouldReceive('loadMissing')->andReturnSelf();
        $participation->setRelation('set', null);

        $service = new EntityLotteryPrizePaymentService();
        $result = $service->evaluatePresencialPayment($participation, 10.0);

        $this->assertFalse($result['allowed']);
        $this->assertSame('online_only', $result['reason']);
    }

    public function test_presencial_blocked_for_digitalized_physical(): void
    {
        $set = new \App\Models\Set(['physical_participations' => 5, 'digital_participations' => 0]);
        $participation = Mockery::mock(Participation::class)->makePartial();
        $participation->participation_code = '1/00001';
        $participation->wallet_mode = Participation::WALLET_MODE_DIGITAL;
        $participation->shouldReceive('loadMissing')->andReturnSelf();
        $participation->setRelation('set', $set);

        $service = new EntityLotteryPrizePaymentService();
        $result = $service->evaluatePresencialPayment($participation, 10.0);

        $this->assertFalse($result['allowed']);
        $this->assertSame('online_only', $result['reason']);
    }

    public function test_digitalized_physical_collectible_online_under_presencial_mode(): void
    {
        $setting = new EntityLotteryPrizeSetting([
            'prize_payment_mode' => EntityLotteryPrizeSetting::MODE_PRESENCIAL,
            'has_sold_digital_participations' => true,
            'online_payments_enabled' => true,
            'presencial_payments_enabled' => true,
            'funds_status' => EntityLotteryPrizeSetting::FUNDS_CONFIRMED,
            'contract_status' => EntityLotteryPrizeSetting::CONTRACT_SIGNED,
            'blocked_user_message' => EntityLotteryPrizeSetting::DEFAULT_BLOCKED_MESSAGE,
            'unlocked_user_message' => EntityLotteryPrizeSetting::DEFAULT_UNLOCKED_MESSAGE,
        ]);

        $set = new \App\Models\Set(['physical_participations' => 5, 'digital_participations' => 0]);
        $participation = Mockery::mock(Participation::class)->makePartial();
        $participation->id = 4;
        $participation->entity_id = 30;
        $participation->participation_code = '1/00003';
        $participation->wallet_mode = Participation::WALLET_MODE_DIGITAL;
        $participation->collected_at = null;
        $participation->donated_at = null;
        $participation->status = 'vendida';
        $participation->shouldReceive('loadMissing')->andReturnSelf();
        $participation->setRelation('set', $set);

        $service = Mockery::mock(EntityLotteryPrizePaymentService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveLotteryId')->andReturn(5);
        $service->shouldReceive('isCollectedOrReservedOnline')->andReturn(false);
        $service->shouldReceive('exceedsOnlineDecimoThreshold')->andReturn(false);
        $service->shouldReceive('getSettings')->with(30, 5)->andReturn($setting);

        $result = $service->evaluateOnlineCollection($participation, 292.0);

        $this->assertTrue($result['cobrable']);
        $this->assertFalse($result['payment_blocked']);
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
        $service->shouldReceive('isCollectedOrReservedOnline')->andReturn(false);
        $service->shouldReceive('exceedsOnlineDecimoThreshold')->andReturn(false);
        $service->shouldReceive('getSettings')->with(30, 5)->andReturn($setting);
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
        $service->shouldReceive('isCollectedOrReservedOnline')->andReturn(false);
        $service->shouldReceive('exceedsOnlineDecimoThreshold')->andReturn(false);
        $service->shouldReceive('getSettings')->with(40, 5)->andReturn($setting);

        $result = $service->evaluateOnlineCollection($participation, 50.0);

        $this->assertTrue($result['cobrable']);
        $this->assertFalse($result['payment_blocked']);
    }

    public function test_online_blocked_when_decimo_prize_exceeds_threshold(): void
    {
        config(['prize_wallet.max_online_prize_per_decimo' => 2000]);

        $participation = Mockery::mock(Participation::class)->makePartial();
        $participation->id = 50;
        $participation->entity_id = 20;
        $participation->collected_at = null;
        $participation->donated_at = null;
        $participation->status = 'vendida';
        $participation->participation_code = '1/00050';

        $service = Mockery::mock(EntityLotteryPrizePaymentService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveLotteryId')->andReturn(5);
        $service->shouldReceive('isCollectedOrReservedOnline')->andReturn(false);
        $service->shouldReceive('exceedsOnlineDecimoThreshold')->andReturn(true);

        $result = $service->evaluateOnlineCollection($participation, 250.0);

        $this->assertFalse($result['cobrable']);
        $this->assertTrue($result['payment_blocked']);
        $this->assertSame('high_decimo_prize', $result['block_reason']);
        $this->assertStringContainsString('250,00', (string) $result['user_message']);
        $this->assertStringContainsString('Ponte en contacto con la entidad', (string) $result['user_message']);
    }

    public function test_presencial_allowed_for_high_decimo_even_if_mode_online(): void
    {
        $setting = new EntityLotteryPrizeSetting([
            'prize_payment_mode' => EntityLotteryPrizeSetting::MODE_ONLINE,
            'online_payments_enabled' => true,
            'presencial_payments_enabled' => false,
            'funds_status' => EntityLotteryPrizeSetting::FUNDS_CONFIRMED,
            'contract_status' => EntityLotteryPrizeSetting::CONTRACT_SIGNED,
        ]);

        $set = new \App\Models\Set(['physical_participations' => 10, 'digital_participations' => 0]);
        $participation = Mockery::mock(Participation::class)->makePartial();
        $participation->id = 51;
        $participation->entity_id = 20;
        $participation->participation_code = '1/00051';
        $participation->wallet_mode = null;
        $participation->collected_at = null;
        $participation->donated_at = null;
        $participation->status = 'vendida';
        $participation->shouldReceive('loadMissing')->andReturnSelf();
        $participation->shouldReceive('requiresOnlinePrizeCollection')->andReturn(false);
        $participation->setRelation('set', $set);

        $service = Mockery::mock(EntityLotteryPrizePaymentService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveLotteryId')->andReturn(5);
        $service->shouldReceive('isCollectedOrReservedOnline')->andReturn(false);
        $service->shouldReceive('getSettings')->with(20, 5)->andReturn($setting);
        $service->shouldReceive('exceedsOnlineDecimoThreshold')->andReturn(true);

        $result = $service->evaluatePresencialPayment($participation, 250.0);

        $this->assertTrue($result['allowed']);
        $this->assertSame('high_decimo_prize', $result['reason']);
    }

    public function test_pedrea_does_not_trigger_high_decimo_gate(): void
    {
        $setting = new EntityLotteryPrizeSetting([
            'prize_payment_mode' => EntityLotteryPrizeSetting::MODE_ONLINE,
            'online_payments_enabled' => true,
            'funds_status' => EntityLotteryPrizeSetting::FUNDS_CONFIRMED,
            'contract_status' => EntityLotteryPrizeSetting::CONTRACT_SIGNED,
            'unlocked_user_message' => EntityLotteryPrizeSetting::DEFAULT_UNLOCKED_MESSAGE,
        ]);

        $participation = Mockery::mock(Participation::class)->makePartial();
        $participation->id = 52;
        $participation->entity_id = 20;
        $participation->collected_at = null;
        $participation->donated_at = null;
        $participation->status = 'vendida';
        $participation->participation_code = '1/00052';

        $service = Mockery::mock(EntityLotteryPrizePaymentService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveLotteryId')->andReturn(5);
        $service->shouldReceive('isCollectedOrReservedOnline')->andReturn(false);
        $service->shouldReceive('exceedsOnlineDecimoThreshold')->andReturn(false);
        $service->shouldReceive('getSettings')->with(20, 5)->andReturn($setting);

        $result = $service->evaluateOnlineCollection($participation, 20.0);

        $this->assertTrue($result['cobrable']);
        $this->assertNotSame('high_decimo_prize', $result['block_reason']);
    }

    public function test_exceeds_threshold_compares_against_config(): void
    {
        config(['prize_wallet.max_online_prize_per_decimo' => 2000]);

        $participation = Mockery::mock(Participation::class)->makePartial();
        $service = Mockery::mock(EntityLotteryPrizePaymentService::class)->makePartial();
        $service->shouldReceive('maxPremioPorDecimo')->with($participation)->andReturn(2000.01);
        $this->assertTrue($service->exceedsOnlineDecimoThreshold($participation));

        $serviceLow = Mockery::mock(EntityLotteryPrizePaymentService::class)->makePartial();
        $serviceLow->shouldReceive('maxPremioPorDecimo')->with($participation)->andReturn(20.0);
        $this->assertFalse($serviceLow->exceedsOnlineDecimoThreshold($participation));
    }
}

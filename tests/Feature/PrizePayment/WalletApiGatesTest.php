<?php

namespace Tests\Feature\PrizePayment;

use App\Models\EntityLotteryPrizeSetting;
use App\Services\Qa\PrizePaymentScenarioFactory;
use Tests\Support\PrizePaymentTestCase;

class WalletApiGatesTest extends PrizePaymentTestCase
{
    private ?PrizePaymentScenarioFactory $scenario = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scenario = PrizePaymentScenarioFactory::create('WALLET');
        $this->scenario->bindMockPrizeInfo(50.0);
        $this->scenario->lockOnlinePartilot((int) $this->scenario->gestor->id);
    }

    protected function tearDown(): void
    {
        $this->scenario?->destroy();
        \Mockery::close();
        parent::tearDown();
    }

    public function test_wallet_lists_blocked_prize_when_online_not_activated(): void
    {
        $response = $this->jsonApi(
            $this->scenario->client,
            'GET',
            '/api/wallet/participations'
        );

        $response->assertOk();
        $items = $response->json('participations');
        $this->assertNotEmpty($items);

        $blocked = collect($items)->first(fn ($i) => ($i['premio'] ?? 0) > 0 && ! empty($i['payment_blocked']));
        $this->assertNotNull($blocked, 'Debe haber participación premiada bloqueada');
    }

    public function test_cobrables_empty_when_blocked(): void
    {
        $response = $this->jsonApi(
            $this->scenario->client,
            'GET',
            '/api/wallet/participations/cobrables'
        );

        $response->assertOk();
        $this->assertEmpty($response->json('participations'));
    }

    public function test_cobro_rejected_when_blocked(): void
    {
        $response = $this->jsonApi(
            $this->scenario->client,
            'POST',
            '/api/wallet/cobro',
            [
                'participation_ids' => [$this->scenario->participation->id],
                'nombre' => 'QA',
                'apellidos' => 'Test',
                'nif' => '12345678Z',
                'iban' => 'ES9121000418450200051332',
                'importe_total' => 50,
            ]
        );

        $response->assertStatus(422);
    }

    public function test_online_entity_payer_allows_collection_without_partilot_activation(): void
    {
        $this->scenario->lockOnlineEntity((int) $this->scenario->gestor->id);
        $setting = EntityLotteryPrizeSetting::query()
            ->where('entity_id', $this->scenario->entity->id)
            ->where('lottery_id', $this->scenario->lottery->id)
            ->first();
        $setting?->update(['online_payments_enabled' => true]);

        $response = $this->jsonApi(
            $this->scenario->client,
            'GET',
            '/api/wallet/participations/cobrables'
        );

        $response->assertOk();
        $ids = collect($response->json('participations'))->pluck('id');
        $this->assertTrue($ids->contains($this->scenario->participation->id));
    }
}

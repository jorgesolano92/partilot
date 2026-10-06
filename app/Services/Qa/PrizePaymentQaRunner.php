<?php

namespace App\Services\Qa;

use App\Models\EntityLotteryPrizeSetting;
use App\Models\Participation;
use App\Models\User;
use App\Services\EntityLotteryPrizePaymentService;
use App\Services\LotteryDigitalizationService;
use App\Services\Qa\PrizePaymentScenarioFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class PrizePaymentQaRunner
{
    /** @var list<array{id: string, section: string, name: string, passed: bool, message: string}> */
    protected array $results = [];

    protected ?PrizePaymentScenarioFactory $scenario = null;

    protected bool $bootstrapped = false;

    public function __construct(
        protected EntityLotteryPrizePaymentService $prizeService,
        protected LotteryDigitalizationService $digitalizationService
    ) {}

    /**
     * @return list<array{id: string, section: string, name: string, passed: bool, message: string}>
     */
    public function run(array $options = []): array
    {
        $this->results = [];

        $bootstrap = (bool) ($options['bootstrap'] ?? false);
        $entityId = isset($options['entity']) ? (int) $options['entity'] : null;
        $lotteryId = isset($options['lottery']) ? (int) $options['lottery'] : null;
        $clientUserId = isset($options['user']) ? (int) $options['user'] : null;

        if ($bootstrap) {
            try {
                $this->scenario = PrizePaymentScenarioFactory::create();
                $this->bootstrapped = true;
                $entityId = (int) $this->scenario->entity->id;
                $lotteryId = (int) $this->scenario->lottery->id;
                $clientUserId = (int) $this->scenario->client->id;
                $this->scenario->bindMockPrizeInfo(50.0);
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                if (str_contains($message, '2002') || str_contains($message, 'Connection refused')) {
                    $message = 'MySQL no disponible. Arranca XAMPP e inténtalo de nuevo.';
                }
                $this->record('A.0', 'A', 'Bootstrap escenario QA', false, $message);
                $this->runSectionA();

                return $this->results;
            }
        }

        $this->runSectionA();
        $this->runSectionB($entityId, $lotteryId, $clientUserId);
        $this->runSectionD($clientUserId);
        $this->runSectionAnexo($clientUserId);

        if ($this->bootstrapped && $this->scenario) {
            try {
                $this->scenario->destroy();
            } catch (\Throwable $e) {
                $this->record('Z.0', 'Z', 'Limpieza datos QA', false, $e->getMessage());
            }
        }

        return $this->results;
    }

    public function passedCount(): int
    {
        return count(array_filter($this->results, fn ($r) => $r['passed']));
    }

    public function failedCount(): int
    {
        return count(array_filter($this->results, fn ($r) => ! $r['passed']));
    }

    protected function runSectionA(): void
    {
        $tables = [
            'entity_lottery_prize_settings',
            'entity_lottery_prize_activation_logs',
            'participation_collection_items',
        ];

        foreach ($tables as $i => $table) {
            $this->record(
                'A.'.($i + 1),
                'A',
                "Tabla {$table} existe",
                Schema::hasTable($table),
                Schema::hasTable($table) ? 'OK' : "Falta tabla {$table}"
            );
        }

        if (Schema::hasTable('entity_lottery_prize_settings')) {
            $this->record(
                'A.4',
                'A',
                'Columna online_payer en settings',
                Schema::hasColumn('entity_lottery_prize_settings', 'online_payer'),
                Schema::hasColumn('entity_lottery_prize_settings', 'online_payer')
                    ? 'OK'
                    : 'Ejecutar migración anexo digitalización/almacén'
            );
        }

        if (Schema::hasTable('participations')) {
            $this->record(
                'A.5',
                'A',
                'Columna wallet_mode en participations',
                Schema::hasColumn('participations', 'wallet_mode'),
                Schema::hasColumn('participations', 'wallet_mode')
                    ? 'OK'
                    : 'Ejecutar migración anexo digitalización/almacén'
            );
        }
    }

    protected function runSectionB(?int $entityId, ?int $lotteryId, ?int $clientUserId): void
    {
        if ($this->bootstrapped && $this->scenario) {
            $this->runBootstrapSectionB();
            $this->runBootstrapSectionB4Api($clientUserId);

            return;
        }

        if (! $entityId || ! $lotteryId) {
            $this->record('B.0', 'B', 'Datos entidad/sorteo', false, 'Usa --bootstrap o pasa --entity y --lottery');

            return;
        }

        $setting = $this->prizeService->getSettings($entityId, $lotteryId);
        $this->record(
            'B.1',
            'B',
            'Settings existentes para entidad/sorteo',
            $setting !== null && $setting->prize_payment_mode !== null,
            $setting
                ? "Modo: {$setting->modeLabel()}"
                : 'No hay entity_lottery_prize_settings; liquida una devolución primero'
        );

        if ($clientUserId) {
            $this->runWalletApiChecks($clientUserId, $entityId, $lotteryId);
        }
    }

    protected function runBootstrapSectionB(): void
    {
        $scenario = $this->scenario;
        if (! $scenario) {
            return;
        }

        $setting = $scenario->lockOnlinePartilot((int) $scenario->gestor->id);
        $this->record(
            'B.1',
            'B',
            'lockModeFromDevolution online PARTILOT',
            $setting->isModeOnline() && $setting->isOnlinePayerPartilot(),
            'Modo online PARTILOT bloqueado en settings'
        );

        $this->record(
            'B.3',
            'B',
            'Online sin activar → funds pending',
            $setting->funds_status === EntityLotteryPrizeSetting::FUNDS_PENDING
                && ! $setting->online_payments_enabled,
            "funds_status={$setting->funds_status}, online_enabled=".($setting->online_payments_enabled ? '1' : '0')
        );

        $presencial = $scenario->lockPresencial((int) $scenario->gestor->id);
        $presencial->update([
            'has_sold_digital_participations' => false,
            'funds_status' => EntityLotteryPrizeSetting::FUNDS_NOT_REQUIRED,
            'contract_status' => EntityLotteryPrizeSetting::CONTRACT_NOT_REQUIRED,
            'presencial_payments_enabled' => true,
        ]);
        $physical = $scenario->participation->fresh();
        $gate = $this->prizeService->evaluatePresencialPayment($physical, 50.0);
        $this->record(
            'B.3b',
            'B',
            'Presencial habilitado permite pago físico',
            $gate['allowed'] === true,
            $gate['message'] ?? 'OK'
        );

        $entityOnline = $scenario->lockOnlineEntity((int) $scenario->gestor->id);
        $entityOnline->update(['online_payments_enabled' => true]);
        $onlineGate = $this->prizeService->evaluateOnlineCollection($scenario->participation, 50.0);
        $this->record(
            'B.C',
            'B',
            'Online entidad (legacy) cobrable sin fondos PARTILOT',
            $onlineGate['cobrable'] === true,
            $onlineGate['user_message'] ?? ($onlineGate['block_reason'] ?? 'OK')
        );
    }

    protected function runBootstrapSectionB4Api(?int $clientUserId): void
    {
        if (! $clientUserId || ! $this->scenario) {
            return;
        }

        $this->scenario->lockOnlinePartilot((int) $this->scenario->gestor->id);
        $this->runWalletApiChecks($clientUserId, (int) $this->scenario->entity->id, (int) $this->scenario->lottery->id);
    }

    protected function runWalletApiChecks(int $clientUserId, int $entityId, int $lotteryId): void
    {
        $user = User::query()->find($clientUserId);
        if (! $user) {
            $this->record('B.4', 'B', 'Usuario cartera existe', false, "Usuario {$clientUserId} no encontrado");

            return;
        }

        $token = Crypt::encrypt(['user_id' => $user->id, 'exp' => now()->addHour()->timestamp]);

        $walletResp = $this->internalJsonApi('GET', '/api/wallet/participations', $token);
        $this->record(
            'B.4.1',
            'B',
            'GET /api/wallet/participations',
            $walletResp['status'] >= 200 && $walletResp['status'] < 300,
            ($walletResp['status'] >= 200 && $walletResp['status'] < 300)
                ? 'HTTP '.$walletResp['status']
                : ($walletResp['json']['message'] ?? 'Error')
        );

        if ($walletResp['status'] >= 200 && $walletResp['status'] < 300) {
            $items = $walletResp['json']['participations'] ?? [];
            $blocked = collect($items)->first(fn ($i) => ! empty($i['payment_blocked']) && ($i['premio'] ?? 0) > 0);
            $this->record(
                'B.4.2',
                'B',
                'Participación premiada con payment_blocked (sin activación)',
                $blocked !== null,
                $blocked
                    ? 'block_reason='.($blocked['block_reason'] ?? '—')
                    : 'No hay participación premiada bloqueada (¿escrutinio/premio?)'
            );
        }

        $cobrablesResp = $this->internalJsonApi('GET', '/api/wallet/participations/cobrables', $token);
        $cobrables = $cobrablesResp['json']['participations'] ?? [];
        $this->record(
            'B.4.3',
            'B',
            'GET cobrables excluye bloqueadas',
            $cobrablesResp['status'] >= 200 && $cobrablesResp['status'] < 300 && count($cobrables) === 0,
            'Cobrables: '.count($cobrables)
        );

        $participationId = $this->scenario?->participation->id
            ?? Participation::query()
                ->where('buyer_name', (string) $clientUserId)
                ->whereHas('set.reserve', fn ($q) => $q->where('lottery_id', $lotteryId))
                ->value('id');

        if ($participationId) {
            $cobroResp = $this->internalJsonApi('POST', '/api/wallet/cobro', $token, [
                'participation_ids' => [$participationId],
                'nombre' => 'QA',
                'apellidos' => 'Test',
                'nif' => '12345678Z',
                'iban' => 'ES9121000418450200051332',
                'importe_total' => 50,
            ]);
            $this->record(
                'B.4.4',
                'B',
                'POST /api/wallet/cobro rechaza si bloqueado',
                $cobroResp['status'] === 422,
                'HTTP '.$cobroResp['status'].': '.($cobroResp['json']['message'] ?? '—')
            );

            $donacionResp = $this->internalJsonApi('POST', '/api/wallet/donacion', $token, [
                'participation_ids' => [$participationId],
                'importe_donacion' => 10,
                'importe_codigo' => 0,
            ]);
            $this->record(
                'B.4.5',
                'B',
                'POST /api/wallet/donacion rechaza si bloqueado',
                $donacionResp['status'] === 422,
                'HTTP '.$donacionResp['status'].': '.($donacionResp['json']['message'] ?? '—')
            );
        }
    }

    protected function runSectionD(?int $clientUserId): void
    {
        if (! $this->bootstrapped || ! $this->scenario) {
            $this->record('D.0', 'D', 'Presencial / LOPD (bootstrap)', false, 'Requiere --bootstrap');

            return;
        }

        $digital = $this->scenario->digitalParticipation;
        $presencialGate = $this->prizeService->evaluatePresencialPayment($digital, 25.0);
        $this->record(
            'D.3.1',
            'D',
            'Nativa digital rechazada en presencial',
            $presencialGate['allowed'] === false && $presencialGate['reason'] === 'native_digital',
            $presencialGate['message'] ?? 'OK'
        );

        $this->scenario->participation->update([
            'collected_at' => now(),
            'status' => 'pagada',
        ]);
        $presencialLopd = $this->prizeService->evaluatePresencialPayment($this->scenario->participation->fresh(), 25.0);
        $this->record(
            'D.3.2',
            'D',
            'Participación ya cobrada → LOPD en presencial',
            $presencialLopd['allowed'] === false
                && in_array($presencialLopd['reason'], ['collected_online', 'already_paid'], true),
            $presencialLopd['message'] ?? $presencialLopd['reason'] ?? '—'
        );

        $this->scenario->participation->update([
            'collected_at' => null,
            'status' => 'vendida',
        ]);
    }

    protected function runSectionAnexo(?int $clientUserId): void
    {
        if (! $this->bootstrapped || ! $this->scenario || ! $clientUserId) {
            $this->record('ANX.0', 'Anexo', 'Digitalización / almacén', false, 'Requiere --bootstrap');

            return;
        }

        $user = $this->scenario->client;
        $token = Crypt::encrypt(['user_id' => $user->id, 'exp' => now()->addHour()->timestamp]);

        $physical = Participation::query()->create([
            'entity_id' => $this->scenario->entity->id,
            'set_id' => $this->scenario->set->id,
            'design_format_id' => $this->scenario->participation->design_format_id,
            'participation_number' => 99,
            'participation_code' => '9001/0099',
            'book_number' => 1,
            'status' => 'disponible',
        ]);

        $ref = \App\Support\ParticipationTicketReference::generate(
            (int) $this->scenario->entity->id,
            (int) $this->scenario->set->reserve_id
        );
        $set = $this->scenario->set;
        $tickets = is_array($set->tickets) ? $set->tickets : [];
        $tickets[] = ['n' => 99, 'r' => $ref];
        $set->update(['tickets' => $tickets]);

        $checkResp = $this->internalJsonApi('GET', '/api/wallet/participations/check?referencia='.urlencode($ref), $token);
        $this->record(
            'ANX.1',
            'Anexo',
            'GET check → can_digitalize / can_store_in_warehouse',
            $checkResp['status'] >= 200 && $checkResp['status'] < 300
                && (($checkResp['json']['wallet_options']['can_digitalize'] ?? false) === true
                    || ($checkResp['json']['wallet_options']['can_store_in_warehouse'] ?? false) === true),
            'status='.($checkResp['json']['status'] ?? '—')
        );

        $storeResp = $this->internalJsonApi('POST', '/api/wallet/participations/store-warehouse', $token, [
            'referencia' => $ref,
        ]);
        $this->record(
            'ANX.2',
            'Anexo',
            'POST store-warehouse',
            $storeResp['status'] >= 200 && $storeResp['status'] < 300,
            $storeResp['json']['message'] ?? 'HTTP '.$storeResp['status']
        );

        if ($storeResp['status'] >= 200 && $storeResp['status'] < 300) {
            $stored = Participation::query()->find($storeResp['json']['participation']['id'] ?? null);
            $this->record(
                'ANX.3',
                'Anexo',
                'wallet_mode=storage en BD',
                $stored && $stored->isWalletStorage(),
                'wallet_mode='.($stored->wallet_mode ?? 'null')
            );

            $this->scenario->bindMockPrizeInfo(30.0);

            $cobrablesAfter = $this->internalJsonApi('GET', '/api/wallet/participations/cobrables', $token);
            $ids = collect($cobrablesAfter['json']['participations'] ?? [])->pluck('id');
            $this->record(
                'ANX.4',
                'Anexo',
                'Almacén excluido de cobrables',
                $stored && ! $ids->contains($stored->id),
                'En cobrables: '.($stored && $ids->contains($stored->id) ? 'sí (mal)' : 'no (bien)')
            );
        }
    }

    /**
     * @return array{status: int, json: array<string, mixed>}
     */
    protected function internalJsonApi(string $method, string $uri, string $token, array $data = []): array
    {
        $server = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];

        if (strtoupper($method) === 'GET') {
            $request = Request::create($uri, 'GET', [], [], [], $server);
        } else {
            $request = Request::create($uri, strtoupper($method), [], [], [], $server, json_encode($data));
        }

        $response = app()->handle($request);
        $decoded = json_decode($response->getContent(), true);

        return [
            'status' => $response->getStatusCode(),
            'json' => is_array($decoded) ? $decoded : [],
        ];
    }

    protected function record(string $id, string $section, string $name, bool $passed, string $message): void
    {
        $this->results[] = [
            'id' => $id,
            'section' => $section,
            'name' => $name,
            'passed' => $passed,
            'message' => $message,
        ];
    }
}

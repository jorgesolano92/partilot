<?php

namespace App\Services\Qa;

use App\Models\Administration;
use App\Models\Entity;
use App\Models\EntityLotteryPrizeSetting;
use App\Models\Lottery;
use App\Models\LotteryType;
use App\Models\Manager;
use App\Models\Participation;
use App\Models\Reserve;
use App\Models\Set;
use App\Models\User;
use App\Services\EntityLotteryPrizePaymentService;
use App\Support\ParticipationTicketReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Crea datos mínimos para pruebas de cobro de premios (MySQL).
 * Prefijo QA_PRIZE_ para identificar registros temporales.
 */
class PrizePaymentScenarioFactory
{
    public const PRIZE_REFERENCE = '';

    public User $client;

    public User $gestor;

    public User $superadmin;

    public Administration $administration;

    public Entity $entity;

    public Entity $entityB;

    public Lottery $lottery;

    public Set $set;

    public Participation $participation;

    public Participation $digitalParticipation;

    public string $reference;

    /** @var list<int> */
    private array $createdUserIds = [];

    /** @var list<int> */
    private array $createdEntityIds = [];

    /** @var list<int> */
    private array $createdLotteryIds = [];

    /** @var list<int> */
    private array $createdLotteryTypeIds = [];

    public static function create(?string $suffix = null): self
    {
        $factory = new self;
        $factory->bootstrap($suffix ?? Str::upper(Str::random(6)));

        return $factory;
    }

    public function bootstrap(string $suffix): void
    {
        $tag = 'QA_PRIZE_'.$suffix;

        $this->superadmin = User::query()->create([
            'name' => $tag.' Superadmin',
            'email' => strtolower($tag).'@qa.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $this->createdUserIds[] = $this->superadmin->id;

        $this->client = User::query()->create([
            'name' => $tag.' Cliente',
            'email' => strtolower($tag).'.client@qa.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_CLIENT,
        ]);
        $this->createdUserIds[] = $this->client->id;

        $this->gestor = User::query()->create([
            'name' => $tag.' Gestor',
            'email' => strtolower($tag).'.gestor@qa.test',
            'password' => bcrypt('password'),
            'role' => User::ROLE_CLIENT,
        ]);
        $this->createdUserIds[] = $this->gestor->id;

        $this->administration = Administration::query()->create([
            'name' => $tag.' Administración',
            'email' => strtolower($tag).'.admin@qa.test',
            'status' => true,
        ]);

        $this->entity = Entity::query()->create([
            'administration_id' => $this->administration->id,
            'name' => $tag.' Entidad A',
            'email' => strtolower($tag).'.entity@qa.test',
            'status' => true,
        ]);
        $this->createdEntityIds[] = $this->entity->id;

        $this->entityB = Entity::query()->create([
            'administration_id' => $this->administration->id,
            'name' => $tag.' Entidad B',
            'email' => strtolower($tag).'.entityb@qa.test',
            'status' => true,
        ]);
        $this->createdEntityIds[] = $this->entityB->id;

        Manager::query()->create([
            'user_id' => $this->gestor->id,
            'entity_id' => $this->entity->id,
            'administration_id' => null,
        ]);

        $lotteryType = LotteryType::query()->create([
            'name' => $tag.' Tipo',
            'ticket_price' => 20,
            'prize_categories' => json_encode([]),
            'is_active' => true,
        ]);
        $this->createdLotteryTypeIds[] = $lotteryType->id;

        $this->lottery = Lottery::query()->create([
            'name' => $tag.' Sorteo',
            'draw_date' => now()->subDay(),
            'deadline_date' => now()->addMonth(),
            'lottery_type_id' => $lotteryType->id,
            'status' => 1,
        ]);
        $this->createdLotteryIds[] = $this->lottery->id;

        $reserveId = $this->insertReserveRow(
            entityId: (int) $this->entity->id,
            lotteryId: (int) $this->lottery->id,
        );
        $reserve = Reserve::query()->findOrFail($reserveId);

        $this->reference = ParticipationTicketReference::generate(
            (int) $this->entity->id,
            (int) $reserve->id
        );
        $tickets = [['n' => 1, 'r' => $this->reference]];

        $this->set = Set::query()->create([
            'entity_id' => $this->entity->id,
            'reserve_id' => $reserve->id,
            'set_name' => $tag.' Set',
            'set_number' => 9001,
            'total_participations' => 2,
            'participation_price' => 5,
            'total_amount' => 10,
            'physical_participations' => 2,
            'digital_participations' => 0,
            'played_amount' => 5,
            'donation_amount' => 0,
            'tickets' => $tickets,
            'status' => 1,
        ]);

        $designFormatId = DB::table('design_formats')->insertGetId([
            'entity_id' => $this->entity->id,
            'lottery_id' => $this->lottery->id,
            'set_id' => $this->set->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->participation = Participation::query()->create([
            'entity_id' => $this->entity->id,
            'set_id' => $this->set->id,
            'design_format_id' => $designFormatId,
            'participation_number' => 1,
            'participation_code' => '9001/0001',
            'book_number' => 1,
            'status' => 'vendida',
            'buyer_name' => (string) $this->client->id,
            'wallet_mode' => Participation::WALLET_MODE_DIGITAL,
        ]);

        $this->digitalParticipation = Participation::query()->create([
            'entity_id' => $this->entity->id,
            'set_id' => $this->set->id,
            'design_format_id' => $designFormatId,
            'participation_number' => 2,
            'participation_code' => '1D/QA0002',
            'book_number' => 1,
            'status' => 'vendida',
            'buyer_name' => (string) $this->client->id,
        ]);
    }

    public function lockOnlinePartilot(int $userId): EntityLotteryPrizeSetting
    {
        return app(EntityLotteryPrizePaymentService::class)->lockModeFromDevolution(
            (int) $this->entity->id,
            (int) $this->lottery->id,
            EntityLotteryPrizeSetting::MODE_ONLINE,
            $userId,
            EntityLotteryPrizeSetting::PAYER_PARTILOT
        );
    }

    public function lockOnlineEntity(int $userId): EntityLotteryPrizeSetting
    {
        return app(EntityLotteryPrizePaymentService::class)->lockModeFromDevolution(
            (int) $this->entity->id,
            (int) $this->lottery->id,
            EntityLotteryPrizeSetting::MODE_ONLINE,
            $userId,
            EntityLotteryPrizeSetting::PAYER_ENTITY
        );
    }

    public function lockPresencial(int $userId): EntityLotteryPrizeSetting
    {
        return app(EntityLotteryPrizePaymentService::class)->lockModeFromDevolution(
            (int) $this->entity->id,
            (int) $this->lottery->id,
            EntityLotteryPrizeSetting::MODE_PRESENCIAL,
            $userId
        );
    }

    public function activateOnline(EntityLotteryPrizeSetting $setting): EntityLotteryPrizeSetting
    {
        $service = app(EntityLotteryPrizePaymentService::class);
        if ($setting->funds_status === EntityLotteryPrizeSetting::FUNDS_PENDING) {
            $service->confirmFunds($setting, (int) $this->superadmin->id, 100);
            $setting = $setting->fresh();
        }
        if ($setting->contract_status === EntityLotteryPrizeSetting::CONTRACT_PENDING) {
            $service->markContractSigned($setting->fresh(), (int) $this->superadmin->id);
            $setting = $setting->fresh();
        }

        return $service->activateOnlinePayments($setting->fresh(), (int) $this->superadmin->id);
    }

    public function bindMockPrizeInfo(float $amount = 50.0): void
    {
        $primaryRef = $this->reference;
        $mock = \Mockery::mock(\App\Http\Controllers\ApiController::class)->makePartial();
        $mock->shouldReceive('getPrizeInfoForReference')
            ->andReturnUsing(function (string $ref) use ($amount, $primaryRef) {
                if ($ref === $primaryRef || ParticipationTicketReference::isValid($ref)) {
                    return [
                        'has_won' => true,
                        'prize_amount' => $amount,
                        'prize_category' => 'QA',
                    ];
                }

                return ['has_won' => false, 'prize_amount' => 0, 'prize_category' => null];
            });
        app()->instance(\App\Http\Controllers\ApiController::class, $mock);
    }

    public function destroy(): void
    {
        Participation::withoutEvents(function () {
            Participation::query()->whereIn('entity_id', $this->createdEntityIds)->delete();
        });
        Set::query()->whereIn('entity_id', $this->createdEntityIds)->delete();
        Reserve::query()->whereIn('entity_id', $this->createdEntityIds)->delete();
        EntityLotteryPrizeSetting::query()
            ->whereIn('entity_id', $this->createdEntityIds)
            ->delete();
        Manager::query()->whereIn('user_id', $this->createdUserIds)->delete();
        Entity::query()->whereIn('id', $this->createdEntityIds)->delete();
        Lottery::query()->whereIn('id', $this->createdLotteryIds)->delete();
        if (! empty($this->createdLotteryTypeIds)) {
            LotteryType::query()->whereIn('id', $this->createdLotteryTypeIds)->delete();
        }
        User::query()->whereIn('id', $this->createdUserIds)->delete();
        if (isset($this->administration)) {
            $this->administration->delete();
        }
    }

    protected function insertReserveRow(int $entityId, int $lotteryId): int
    {
        $row = [
            'entity_id' => $entityId,
            'lottery_id' => $lotteryId,
            'reservation_numbers' => json_encode(['12345']),
            'total_amount' => 100,
            'total_tickets' => 1,
            'status' => 1,
            'reservation_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('reserves', 'customer_name')) {
            $row['customer_name'] = 'Cliente QA';
        }
        if (Schema::hasColumn('reserves', 'reservation_amount')) {
            $row['reservation_amount'] = 20;
        }
        if (Schema::hasColumn('reserves', 'reservation_tickets')) {
            $row['reservation_tickets'] = 1;
        }

        return (int) DB::table('reserves')->insertGetId($row);
    }
}

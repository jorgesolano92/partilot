<?php

namespace Tests\Unit;

use App\Models\Entity;
use App\Models\ParticipationDonation;
use App\Services\PrepagoCodigosService;
use App\Services\PrizeWalletOperationGuardService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityOleadaFTest extends TestCase
{
    #[Test]
    public function inactive_entity_is_blocked_for_wallet_operations(): void
    {
        $entity = new Entity(['status' => 0]);
        $guard = new PrizeWalletOperationGuardService;

        $this->assertSame(
            'La entidad no está activa para cobros o donaciones.',
            $guard->assertEntityActive($entity)
        );
    }

    #[Test]
    public function donation_rejects_non_prized_participations(): void
    {
        $guard = new PrizeWalletOperationGuardService;
        $participations = new Collection([(object) ['id' => 1]]);

        $message = $guard->assertAllParticipationsHavePrize($participations, fn () => [
            'has_won' => false,
            'prize_amount' => 0,
        ]);

        $this->assertSame(
            'Solo se pueden operar participaciones premiadas con premio mayor que cero.',
            $message
        );
    }

    #[Test]
    public function global_cap_rejects_excessive_amounts(): void
    {
        config(['prize_wallet.max_operation_amount' => 100]);

        $guard = new PrizeWalletOperationGuardService;
        $this->assertNotNull($guard->assertWithinGlobalCap(150));
        $this->assertNull($guard->assertWithinGlobalCap(50));
    }

    #[Test]
    public function participation_donation_blocks_mass_assignment_of_donated_at(): void
    {
        $donation = new ParticipationDonation;
        $donation->fill([
            'user_id' => 1,
            'importe_donacion' => 10,
            'importe_codigo' => 0,
            'donated_at' => '2000-01-01 00:00:00',
        ]);

        $this->assertNull($donation->donated_at);
    }

    #[Test]
    public function prepago_service_rejects_amount_above_cap(): void
    {
        config(['prize_wallet.max_prepago_code_amount' => 50]);

        $service = new PrepagoCodigosService;
        $this->assertNull($service->generateCode(null, 100));
    }
}

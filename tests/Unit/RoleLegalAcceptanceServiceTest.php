<?php

namespace Tests\Unit;

use App\Models\Entity;
use App\Models\LegalAcceptance;
use App\Models\Manager;
use App\Models\Seller;
use App\Models\User;
use App\Services\RoleLegalAcceptanceService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

class RoleLegalAcceptanceServiceTest extends TestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->databaseAvailable() || ! Schema::hasTable('legal_acceptances')) {
            $this->markTestSkipped('Se requiere MySQL con migraciones legales aplicadas.');
        }
    }

    public function test_pending_invitations_includes_manager_and_seller(): void
    {
        $user = User::factory()->create();
        $entity = Entity::query()->create([
            'name' => 'Entidad QA Legal',
            'status' => 1,
        ]);

        $manager = Manager::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'status' => null,
            'confirmation_token' => 'token-gr-qa',
            'pending_primary' => true,
        ]);

        $seller = Seller::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => Seller::STATUS_PENDING,
            'confirmation_token' => 'token-seller-qa',
        ]);
        $seller->entities()->attach($entity->id);

        $pending = app(RoleLegalAcceptanceService::class)->pendingInvitationsForUser($user);

        $this->assertCount(2, $pending);
        $this->assertSame('manager-'.$manager->id, $pending[0]['key']);
        $this->assertSame('gestor_responsable', $pending[0]['type']);

        $manager->delete();
        $seller->entities()->detach();
        $seller->delete();
        $entity->delete();
        $user->delete();
    }

    public function test_respond_by_key_accepts_seller_invitation(): void
    {
        $user = User::factory()->create();
        $entity = Entity::query()->create([
            'name' => 'Entidad QA Seller',
            'status' => 1,
        ]);
        $seller = Seller::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => Seller::STATUS_PENDING,
            'confirmation_token' => 'token-seller-accept',
        ]);
        $seller->entities()->attach($entity->id);

        $service = app(RoleLegalAcceptanceService::class);
        $result = $service->respondByKey($user, 'seller-'.$seller->id, 'accept', Request::create('/'));

        $this->assertTrue($result['success']);
        $seller->refresh();
        $this->assertSame(Seller::STATUS_ACTIVE, $seller->status);
        $this->assertNull($seller->confirmation_token);

        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => $user->id,
            'action' => LegalAcceptance::ACTION_ACEPTACION_ROL_VENDEDOR,
            'result' => LegalAcceptance::RESULT_ACEPTADO,
        ]);

        $seller->delete();
        $entity->delete();
        $user->delete();
    }

    public function test_respond_by_key_rejects_manager_invitation(): void
    {
        $user = User::factory()->create();
        $entity = Entity::query()->create([
            'name' => 'Entidad QA Manager',
            'status' => 1,
        ]);
        $manager = Manager::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'status' => null,
            'confirmation_token' => 'token-mgr-reject',
            'pending_primary' => true,
        ]);

        $service = app(RoleLegalAcceptanceService::class);
        $result = $service->respondByKey($user, 'manager-'.$manager->id, 'reject', Request::create('/'));

        $this->assertTrue($result['success']);
        $manager->refresh();
        $this->assertNull($manager->confirmation_token);
        $this->assertFalse((bool) $manager->pending_primary);

        $manager->delete();
        $entity->delete();
        $user->delete();
    }

    protected function databaseAvailable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

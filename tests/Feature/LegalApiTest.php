<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\Manager;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegalApiTest extends TestCase
{
    protected function apiHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.Crypt::encrypt([
                'user_id' => $user->id,
                'exp' => now()->addDays(30)->timestamp,
            ]),
            'Accept' => 'application/json',
        ];
    }
    #[Test]
    public function legal_config_endpoint_is_public(): void
    {
        $this->getJson('/api/legal/config')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'registration' => ['checkbox_label', 'terms_url', 'privacy_url'],
                    'documents',
                    'prize_collection' => ['title', 'irreversibility_warning'],
                    'prize_donation' => ['title', 'notice_template'],
                ],
            ]);
    }

    #[Test]
    public function legal_documents_endpoint_lists_documents(): void
    {
        $response = $this->getJson('/api/legal/documents');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($response->json('documents'));
    }

    #[Test]
    public function cookie_status_endpoint_reports_needs_banner_without_cookie(): void
    {
        $this->getJson('/api/legal/cookies/status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('needs_banner', true);
    }

    #[Test]
    public function cookie_consent_can_be_stored(): void
    {
        $this->postJson('/api/legal/cookies', [
            'choice' => 'necessary',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cookies_analiticas', false);
    }

    #[Test]
    public function pending_acceptances_requires_auth(): void
    {
        if (! $this->legalTablesAvailable()) {
            $this->markTestSkipped('Se requiere MySQL con migraciones legales aplicadas.');
        }

        $this->getJson('/api/legal/pending-acceptances')
            ->assertStatus(401);
    }

    #[Test]
    public function authenticated_user_can_list_pending_role_invitations(): void
    {
        if (! $this->legalTablesAvailable()) {
            $this->markTestSkipped('Se requiere MySQL con migraciones legales aplicadas.');
        }
        $user = User::factory()->create();
        $entity = Entity::query()->create([
            'name' => 'Entidad API Legal',
            'status' => 1,
        ]);
        $seller = Seller::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => Seller::STATUS_PENDING,
            'confirmation_token' => 'token-api-seller',
        ]);
        $seller->entities()->attach($entity->id);

        $response = $this->getJson('/api/legal/pending-acceptances', $this->apiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $pending = $response->json('pending');
        $this->assertNotEmpty($pending);
        $this->assertSame('seller-'.$seller->id, $pending[0]['key']);

        $seller->entities()->detach();
        $seller->delete();
        $entity->delete();
        $user->delete();
    }

    #[Test]
    public function authenticated_user_can_respond_to_role_invitation(): void
    {
        if (! $this->legalTablesAvailable()) {
            $this->markTestSkipped('Se requiere MySQL con migraciones legales aplicadas.');
        }
        $user = User::factory()->create();
        $entity = Entity::query()->create([
            'name' => 'Entidad API Respond',
            'status' => 1,
        ]);
        $manager = Manager::create([
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'status' => null,
            'confirmation_token' => 'token-api-mgr',
            'pending_primary' => false,
        ]);

        $this->postJson(
            '/api/legal/role-invitations/manager-'.$manager->id.'/respond',
            ['action' => 'accept'],
            $this->apiHeaders($user)
        )->assertOk()
            ->assertJsonPath('success', true);

        $manager->refresh();
        $this->assertSame(1, (int) $manager->status);

        $manager->delete();
        $entity->delete();
        $user->delete();
    }

    protected function legalTablesAvailable(): bool
    {
        if (! $this->databaseAvailable()) {
            return false;
        }

        return Schema::hasTable('legal_acceptances');
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

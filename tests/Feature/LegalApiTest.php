<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegalApiTest extends TestCase
{
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
}

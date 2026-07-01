<?php

namespace Tests\Unit;

use App\Models\LegalAcceptance;
use App\Services\LegalAcceptanceService;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegalAcceptanceServiceTest extends TestCase
{
    #[Test]
    public function client_config_includes_registration_checkbox_label(): void
    {
        $config = app(LegalAcceptanceService::class)->clientConfig();

        $this->assertArrayHasKey('registration', $config);
        $this->assertStringContainsString('Marco Legal', $config['registration']['checkbox_label']);
        $this->assertNotEmpty($config['registration']['terms_url']);
    }

    #[Test]
    public function list_public_documents_includes_core_slugs(): void
    {
        $documents = app(LegalAcceptanceService::class)->listPublicDocuments();
        $slugs = array_column($documents, 'slug');

        $this->assertContains('terminos-y-condiciones', $slugs);
        $this->assertContains('politica-de-privacidad', $slugs);
        $this->assertContains('politica-de-cookies', $slugs);
    }

    #[Test]
    public function maps_registration_consent_type_to_legal_action(): void
    {
        $service = app(LegalAcceptanceService::class);

        $this->assertSame(
            LegalAcceptance::ACTION_REGISTRO_ACEPTACION_TCU,
            $service->mapUserConsentType('registration_terms')
        );
    }

    #[Test]
    public function detect_channel_reads_header(): void
    {
        $service = app(LegalAcceptanceService::class);
        $request = Request::create('/api/legal/config', 'GET');
        $request->headers->set('X-Partilot-Channel', 'app_ios');

        $this->assertSame(LegalAcceptance::CHANNEL_APP_IOS, $service->detectChannel($request));
    }
}

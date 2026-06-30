<?php

namespace Tests\Feature;

use App\Models\Entity;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SecurityOleadaATest extends TestCase
{
    #[Test]
    public function public_upload_image_api_route_is_removed(): void
    {
        $this->postJson('/api/upload-image', [])->assertNotFound();
    }

    #[Test]
    public function public_design_save_format_api_route_is_removed(): void
    {
        $this->postJson('/api/design/save-format', [])->assertNotFound();
    }

    #[Test]
    public function stripe_webhook_rejects_when_no_secrets_configured(): void
    {
        config(['services.stripe.webhook_secret' => '']);

        $this->postJson('/api/stripe/webhook', ['type' => 'payment_intent.succeeded'], [
            'Stripe-Signature' => 't=1710000000,v1=fakesignature',
        ])->assertStatus(400)
            ->assertJson(['ok' => false, 'message' => 'Invalid signature']);
    }

    #[Test]
    public function entity_comments_are_sanitized_on_save(): void
    {
        $entity = new Entity;
        $entity->comments = '<img src=x onerror=alert(1)>Comentario';

        $this->assertSame('Comentario', $entity->comments);
    }
}

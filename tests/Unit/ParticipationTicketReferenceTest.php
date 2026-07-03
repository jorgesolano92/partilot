<?php

namespace Tests\Unit;

use App\Models\Participation;
use App\Support\ParticipationTicketReference;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParticipationTicketReferenceTest extends TestCase
{
    #[Test]
    public function generate_produces_21_digits_with_valid_check(): void
    {
        $ref = ParticipationTicketReference::generate(1, 1);

        $this->assertSame(21, strlen($ref));
        $this->assertTrue(ctype_digit($ref));
        $this->assertTrue(ParticipationTicketReference::isValid($ref));
        $this->assertStringStartsWith(
            ParticipationTicketReference::encodeScopeDigits(1, 1),
            $ref
        );
    }

    #[Test]
    public function invalid_check_is_rejected(): void
    {
        $ref = ParticipationTicketReference::generate(5, 12);
        $tampered = substr($ref, 0, 20).((int) substr($ref, 20, 1) + 1) % 10;

        $this->assertFalse(ParticipationTicketReference::isValid($tampered));
    }

    #[Test]
    public function normalize_strips_non_digits(): void
    {
        $this->assertSame(
            '000100011234567890123',
            ParticipationTicketReference::normalize('0001 0001-1234567890123')
        );
    }

    #[Test]
    public function references_in_same_set_are_not_sequential(): void
    {
        $refs = [];
        for ($i = 0; $i < 5; $i++) {
            $refs[] = ParticipationTicketReference::generate(1, 1);
        }

        $this->assertCount(5, array_unique($refs));
    }

    #[Test]
    public function large_ids_do_not_collide_with_truncated_min_9999_encoding(): void
    {
        $legacyTruncated = str_pad('1', 4, '0', STR_PAD_LEFT)
            .str_pad('1', 4, '0', STR_PAD_LEFT);
        $encodedLarge = ParticipationTicketReference::encodeScopeDigits(10001, 1);

        $this->assertNotSame($legacyTruncated, $encodedLarge);
        $this->assertNotSame(
            ParticipationTicketReference::encodeScopeDigits(1, 1),
            ParticipationTicketReference::encodeScopeDigits(10001, 1)
        );
    }

    #[Test]
    public function generate_unique_skips_existing_references(): void
    {
        $seen = ['000000000000000000001' => true];

        $ref = ParticipationTicketReference::generateUnique(9, 9, static fn (string $candidate): bool => isset($seen[$candidate]));

        $this->assertTrue(ParticipationTicketReference::isValid($ref));
        $this->assertArrayNotHasKey($ref, $seen);
    }

    #[Test]
    public function returnable_devolution_statuses_exclude_sold_states(): void
    {
        $returnable = Participation::returnableDevolutionStatuses();

        $this->assertContains('disponible', $returnable);
        $this->assertContains('asignada', $returnable);
        $this->assertNotContains('vendida', $returnable);
        $this->assertNotContains('pagada', $returnable);
    }

    #[Test]
    public function signed_url_contains_hmac_and_legacy_unsigned_still_allowed(): void
    {
        config([
            'lottery.participation_qr_hmac.require_signature' => false,
            'lottery.participation_qr_hmac.allow_legacy_unsigned' => true,
            'lottery.participation_qr_public_url' => 'https://partilot.es',
        ]);

        $ref = ParticipationTicketReference::generate(2, 3);
        $url = ParticipationTicketReference::signedCheckUrl($ref);

        $this->assertStringStartsWith('https://partilot.es/comprobar-participacion?', $url);
        $this->assertStringContainsString('ref='.$ref, $url);
        $this->assertStringContainsString('sig=', $url);
        $this->assertNull(ParticipationTicketReference::authenticationError($ref, null));
    }

    #[Test]
    public function signed_url_uses_configurable_public_base_url(): void
    {
        config(['lottery.participation_qr_public_url' => 'https://check.example.test']);

        $ref = ParticipationTicketReference::generate(10, 20);
        $url = ParticipationTicketReference::signedCheckUrl($ref);

        $this->assertStringStartsWith('https://check.example.test/comprobar-participacion?', $url);
        $this->assertStringNotContainsString('panel.', $url);
    }

    #[Test]
    public function tampered_signature_is_rejected(): void
    {
        $ref = ParticipationTicketReference::generate(4, 5);
        $sig = ParticipationTicketReference::signature($ref);

        $this->assertNotNull(ParticipationTicketReference::authenticationError($ref, '00000000'));
        $this->assertNull(ParticipationTicketReference::authenticationError($ref, $sig));
    }

    #[Test]
    public function require_signature_rejects_unsigned_when_legacy_disabled(): void
    {
        config([
            'lottery.participation_qr_hmac.require_signature' => true,
            'lottery.participation_qr_hmac.allow_legacy_unsigned' => false,
        ]);

        $ref = ParticipationTicketReference::generate(7, 8);

        $this->assertNotNull(ParticipationTicketReference::authenticationError($ref, null));
        $this->assertNull(
            ParticipationTicketReference::authenticationError($ref, ParticipationTicketReference::signature($ref))
        );
    }
}

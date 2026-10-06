<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Http\Request;

class UserConsentService
{
    public function __construct(
        private readonly LegalAcceptanceService $legalAcceptance
    ) {}

    public function record(?User $user, string $type, Request $request, array $context = []): UserConsent
    {
        $meta = $this->legalAcceptance->registrationDocumentMeta();

        $consent = UserConsent::create([
            'user_id' => $user?->id,
            'type' => $type,
            'version' => $meta['version'],
            'text_hash' => $meta['text_hash'],
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
            'context' => $context ?: null,
            'accepted_at' => now(),
        ]);

        $this->legalAcceptance->recordFromRequest(
            action: $this->legalAcceptance->mapUserConsentType($type),
            request: $request,
            user: $user,
            version: $meta['version'],
            textHash: $meta['text_hash'],
            context: array_merge($context, [
                'user_consent_id' => $consent->id,
                'legacy_type' => $type,
            ]),
        );

        return $consent;
    }
}

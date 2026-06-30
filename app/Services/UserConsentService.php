<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Http\Request;

class UserConsentService
{
    public function record(?User $user, string $type, Request $request, array $context = []): UserConsent
    {
        return UserConsent::create([
            'user_id' => $user?->id,
            'type' => $type,
            'version' => (string) config('legal.terms_version', '1'),
            'text_hash' => (string) config('legal.terms_text_hash', 'terms_v1'),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
            'context' => $context ?: null,
            'accepted_at' => now(),
        ]);
    }
}

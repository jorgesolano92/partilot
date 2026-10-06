<?php

namespace App\Services;

use App\Models\CookieConsent;
use App\Models\LegalAcceptance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CookieConsentService
{
    public function consentCookieName(): string
    {
        return (string) config('legal.cookie_consent_name', 'partilot_cookie_consent');
    }

    public function needsBanner(Request $request): bool
    {
        return ! $request->cookies->has($this->consentCookieName());
    }

    /**
     * @return array{cookies_tecnicas: bool, cookies_analiticas: bool, choice: string}
     */
    public function readFromRequest(Request $request): ?array
    {
        $raw = $request->cookie($this->consentCookieName());
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        return [
            'cookies_tecnicas' => true,
            'cookies_analiticas' => (bool) ($decoded['cookies_analiticas'] ?? false),
            'choice' => (string) ($decoded['choice'] ?? CookieConsent::CHOICE_NECESSARY),
        ];
    }

    public function analyticsAllowed(Request $request): bool
    {
        $consent = $this->readFromRequest($request);

        return $consent !== null && $consent['cookies_analiticas'];
    }

    /**
     * @return array{cookie: \Symfony\Component\HttpFoundation\Cookie, consent: CookieConsent|null}
     */
    public function store(
        Request $request,
        string $choice,
        bool $analytics,
        ?User $user = null
    ): array {
        $tecnicas = true;
        $analytics = $choice === CookieConsent::CHOICE_ALL
            ? true
            : ($choice === CookieConsent::CHOICE_CUSTOM ? $analytics : false);

        $visitorKey = $request->cookie('partilot_visitor_key');
        if (! is_string($visitorKey) || strlen($visitorKey) < 16) {
            $visitorKey = Str::random(40);
        }

        $consent = null;
        if (Schema::hasTable('cookie_consents')) {
            $consent = CookieConsent::create([
                'user_id' => $user?->id,
                'visitor_key' => $visitorKey,
                'cookies_tecnicas' => $tecnicas,
                'cookies_analiticas' => $analytics,
                'choice' => $choice,
                'channel' => app(LegalAcceptanceService::class)->detectChannel($request),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
                'accepted_at' => now(),
            ]);

            app(LegalAcceptanceService::class)->record(
                action: LegalAcceptance::ACTION_COOKIES_ACEPTACION,
                user: $user,
                version: (string) config('legal.documents.cookies.version', '3'),
                textHash: (string) config('legal.documents.cookies.hash', 'cookies_v3'),
                channel: app(LegalAcceptanceService::class)->detectChannel($request),
                request: $request,
                context: [
                    'choice' => $choice,
                    'cookies_analiticas' => $analytics,
                    'cookie_consent_id' => $consent->id,
                ],
            );
        }

        $payload = json_encode([
            'choice' => $choice,
            'cookies_analiticas' => $analytics,
            'accepted_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        $minutes = (int) config('legal.cookie_consent_days', 365) * 24 * 60;

        $cookie = Cookie::make(
            $this->consentCookieName(),
            $payload,
            $minutes,
            '/',
            null,
            $request->isSecure(),
            false,
            false,
            'Lax'
        );

        $visitorCookie = Cookie::make(
            'partilot_visitor_key',
            $visitorKey,
            $minutes,
            '/',
            null,
            $request->isSecure(),
            false,
            false,
            'Lax'
        );

        return [
            'cookies' => [$cookie, $visitorCookie],
            'consent' => $consent,
            'analytics' => $analytics,
        ];
    }
}

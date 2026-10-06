@php
    $cookieConsent = app(\App\Services\CookieConsentService::class);
    $consent = $cookieConsent->readFromRequest(request());
    $analyticsAllowed = $consent !== null && ($consent['cookies_analiticas'] ?? false);
@endphp
<script>
    window.partilotCookieConsent = @json($consent);
    window.partilotAnalyticsAllowed = @json($analyticsAllowed);
    window.partilotWaitForCookieConsent = function (callback) {
        if (document.cookie.indexOf('{{ config('legal.cookie_consent_name', 'partilot_cookie_consent') }}=') !== -1) {
            callback(window.partilotCookieConsent || {});
            return;
        }
        window.addEventListener('partilot-cookie-consent', function (event) {
            callback(event.detail || {});
        }, { once: true });
    };
</script>

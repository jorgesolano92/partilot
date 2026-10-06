@php
    $consent = app(\App\Services\CookieConsentService::class)->readFromRequest(request());
    $analyticsAllowed = $consent !== null && ($consent['cookies_analiticas'] ?? false);
@endphp
@if($analyticsAllowed)
@foreach(config('legal.analytics_scripts', []) as $script)
<script src="{{ $script }}" defer></script>
@endforeach
@endif

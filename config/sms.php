<?php

return [
    /*
    | Verificación OTP por SMS en registro (app y web comprador).
    | driver "log": código en storage/logs/laravel.log (desarrollo).
    | driver "httpsms": pasarela httpSMS (móvil Android + línea).
    */
    'enabled' => (bool) env('SMS_VERIFICATION_ENABLED', false),
    'driver' => env('SMS_DRIVER', 'log'),
    'code_length' => (int) env('SMS_CODE_LENGTH', 6),
    'code_ttl_minutes' => (int) env('SMS_CODE_TTL_MINUTES', 10),
    'resend_cooldown_seconds' => (int) env('SMS_RESEND_COOLDOWN_SECONDS', 60),
    'max_verify_attempts' => (int) env('SMS_MAX_VERIFY_ATTEMPTS', 5),
    'verify_lockout_minutes' => (int) env('SMS_VERIFY_LOCKOUT_MINUTES', 15),
    'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '34'),
];

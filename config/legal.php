<?php

return [
    /*
    | Versión global del marco legal (A1). Incrementar al publicar cambios.
    */
    'terms_version' => env('LEGAL_TERMS_VERSION', '10'),
    'terms_text_hash' => env('LEGAL_TERMS_TEXT_HASH', 'marco_legal_v10'),

    /*
    | Texto L1 mostrado en registro (app y API). Debe coincidir con legales2.
    */
    'registration_checkbox_label' => 'He leído y acepto los Términos y Condiciones de Uso, la Política de Privacidad y el Marco Legal Integral de PARTILOT.',

    /*
    | Frase introductoria en pantallas de aceptación de rol (L3–L5).
    */
    'role_intro_sentence' => 'Al registrarte en PARTILOT ya aceptaste las condiciones generales. Esta pantalla te recuerda las responsabilidades específicas de este rol para que tomes tu decisión con toda la información.',

    /*
    | Documentos públicos enlazables desde la app y el panel.
    */
    'documents' => [
        'marco_legal' => [
            'slug' => 'marco-legal',
            'title' => 'Marco Legal Integral',
            'version' => env('LEGAL_MARCO_VERSION', '10'),
            'hash' => env('LEGAL_MARCO_HASH', 'marco_legal_v10'),
            'route' => 'legal.terminos-y-condiciones',
        ],
        'terminos' => [
            'slug' => 'terminos-y-condiciones',
            'title' => 'Términos y Condiciones de Uso',
            'version' => env('LEGAL_TERMINOS_VERSION', '10'),
            'hash' => env('LEGAL_TERMINOS_HASH', 'terminos_v10'),
            'route' => 'legal.terminos-y-condiciones',
        ],
        'privacidad' => [
            'slug' => 'politica-de-privacidad',
            'title' => 'Política de Privacidad',
            'version' => env('LEGAL_PRIVACIDAD_VERSION', '10'),
            'hash' => env('LEGAL_PRIVACIDAD_HASH', 'privacidad_v10'),
            'route' => 'legal.politica-de-privacidad',
        ],
        'cookies' => [
            'slug' => 'politica-de-cookies',
            'title' => 'Política de Cookies',
            'version' => env('LEGAL_COOKIES_VERSION', '3'),
            'hash' => env('LEGAL_COOKIES_HASH', 'cookies_v3'),
            'route' => 'legal.politica-de-cookies',
        ],
        'aviso_legal' => [
            'slug' => 'aviso-legal',
            'title' => 'Aviso Legal',
            'version' => env('LEGAL_AVISO_VERSION', '3'),
            'hash' => env('LEGAL_AVISO_HASH', 'aviso_v3'),
            'route' => 'legal.aviso-legal',
        ],
    ],

    /*
    | Cookie de consentimiento en panel web (L2).
    */
    'cookie_consent_name' => 'partilot_cookie_consent',
    'cookie_consent_days' => (int) env('LEGAL_COOKIE_CONSENT_DAYS', 365),

    /*
    | Scripts de analítica (GA, etc.) — solo se cargan con cookies analíticas aceptadas.
    | Ejemplo: 'https://www.googletagmanager.com/gtag/js?id=G-XXXX'
    */
    'analytics_scripts' => array_filter(array_map('trim', explode(',', (string) env('LEGAL_ANALYTICS_SCRIPTS', '')))),

    /*
    | Firebase web (notificaciones push en panel): requiere interacción con banner L2.
    */
    'defer_firebase_until_cookie_banner' => env('LEGAL_DEFER_FIREBASE_UNTIL_COOKIE_BANNER', true),
];

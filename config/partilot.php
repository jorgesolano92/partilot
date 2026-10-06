<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Magic link acceso panel administración (días de caducidad)
    |--------------------------------------------------------------------------
    */
    'panel_magic_link_ttl_days' => (int) env('PARTILOT_PANEL_MAGIC_LINK_TTL_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Búsqueda global de participaciones
    |--------------------------------------------------------------------------
    | Mínimo de caracteres para ejecutar la búsqueda por número de referencia.
    */
    'search_min_chars' => (int) env('PARTILOT_SEARCH_MIN_CHARS', 16),

    /*
    |--------------------------------------------------------------------------
    | Cobro por transferencia: horas para confirmar email (doble opt-in)
    |--------------------------------------------------------------------------
    */
    'transfer_collection_verify_hours' => (int) env('PARTILOT_TRANSFER_COLLECTION_VERIFY_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | Web app (vendedores y usuarios)
    |--------------------------------------------------------------------------
    */
    'webapp_url' => rtrim((string) env('PARTILOT_WEBAPP_URL', 'https://partilot.es/panel'), '/'),
];

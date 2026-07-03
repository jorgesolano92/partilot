<?php

/**
 * Copiar a config.php y ajustar valores.
 */
return [
    // API del panel (VPS panel.partilot.es)
    'panel_api_url' => 'https://panel.partilot.es/api/public/participation-check',

    // Caché de resultados válidos (segundos)
    'cache_ttl' => 60,

    // App publicada en stores
    'app_package' => 'com.partilot.app',
    'play_store_url' => 'https://play.google.com/store/apps/details?id=com.partilot.app',
    // Sustituir cuando tengáis el ID real de App Store
    'app_store_url' => 'https://apps.apple.com/app/id0000000000',

    // Bloqueo IP tras referencias inválidas (segundos por escalón; el 4.º es permanente)
    'ip_block_steps' => [60, 300, 600],

    // Carpeta escribible (caché + SQLite)
    'data_dir' => __DIR__ . '/data',

    // Confianza en X-Forwarded-For (true si hay proxy/CDN delante)
    'trust_proxy' => true,
];

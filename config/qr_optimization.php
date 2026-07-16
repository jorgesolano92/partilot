<?php

return [
    /*
    |--------------------------------------------------------------------------
    | QR Code Optimization Settings
    |--------------------------------------------------------------------------
    |
    | Configuración para optimización de QR codes y PDFs
    |
    */

    // Optimización de imágenes en HTML
    'optimize_images' => env('QR_OPTIMIZE_IMAGES', false),

    // Si true, no genera ni inyecta QR en PDFs de participación (más rápido para probar maquetación).
    'skip_in_pdf' => env('PDF_SKIP_QR', false),
    
    // Límite de imágenes para optimizar (para evitar ralentizar)
    'max_images_to_optimize' => env('QR_MAX_IMAGES_OPTIMIZE', 5),
    
    // Configuración de QR codes
    'qr_code' => [
        'size' => env('QR_CODE_SIZE', 100),
        'margin' => env('QR_CODE_MARGIN', 0),
        'cache_ttl' => env('QR_CODE_CACHE_TTL', 1800), // 30 minutos
    ],
    
    // Configuración de rendimiento
    'performance' => [
        'batch_size' => env('QR_BATCH_SIZE', 100),
        'ultra_fast_threshold' => env('QR_ULTRA_FAST_THRESHOLD', 200),
        'enable_cache' => env('QR_ENABLE_CACHE', true),
    ],
];

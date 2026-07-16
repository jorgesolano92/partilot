<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración de Optimización de PDFs
    |--------------------------------------------------------------------------
    */

    'sync_limit' => 500,
    'async_limit' => 1000,

    // Chunks más grandes = menos renders DomPDF + menos re-embebidos al unir con FPDI
    'chunk_size' => (int) env('PDF_CHUNK_SIZE', 250),
    'job_chunk_size' => (int) env('PDF_JOB_CHUNK_SIZE', 250),

    'queue' => env('PDF_QUEUE', 'default'),

    'memory_limit' => env('PDF_MEMORY_LIMIT', '2048M'),
    'max_execution_time' => 300,
    'job_timeout' => 0,
    'job_timeout_per_chunk' => 120,
    'job_timeout_min' => 900,
    'job_timeout_max' => 7200,

    'cache_ttl' => 3600,
    'cache_prefix' => 'pdf_',

    'temp_path' => 'temp_pdfs/',
    'generated_path' => 'generated_pdfs/',
    'cleanup_temp' => true,

    'dompdf_options' => [
        'defaultFont' => 'Arial',
        'isRemoteEnabled' => true,
        'isHtml5ParserEnabled' => true,
        'isPhpEnabled' => true,
    ],

    // DPI DomPDF: debe ser 96 (px del diseño ↔ ticket en mm).
    'dpi' => 96,

    // Subsetting reduce mucho el peso cuando hay muchas páginas.
    'font_subsetting' => env('PDF_FONT_SUBSETTING', true),

    // Fondo materializado: 1.0 ≈ tamaño CSS; subir infla MB y tiempo.
    'bg_pixel_scale' => (float) env('PDF_BG_PIXEL_SCALE', 1.0),
    'bg_jpeg_quality' => (int) env('PDF_BG_JPEG_QUALITY', 82),

    // QR como ficheros en disco (DomPDF reutiliza XObject por ruta) en vez de data-URI.
    'qr_as_files' => env('PDF_QR_AS_FILES', true),
];

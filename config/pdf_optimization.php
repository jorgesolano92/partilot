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
    'bg_pixel_scale' => (float) env('PDF_BG_PIXEL_SCALE', 1.5),
    'bg_jpeg_quality' => (int) env('PDF_BG_JPEG_QUALITY', 90),

    // QR como ficheros en disco (DomPDF reutiliza XObject por ruta) en vez de data-URI.
    'qr_as_files' => env('PDF_QR_AS_FILES', true),

    // Plantilla: DomPDF renderiza 1 celda; FPDI la repite en rejilla fija + estampa ref/nº/QR.
    'use_stamp_template' => env('PDF_USE_STAMP_TEMPLATE', false),

    // Origen de la rejilla en la hoja (mm).
    'stamp_offset_x' => (float) env('PDF_STAMP_OFFSET_X', 0),
    'stamp_offset_y' => (float) env('PDF_STAMP_OFFSET_Y', 0),

    // Desplazamiento SOLO de overlays (imgs/ref/nº/QR) respecto al arte de la celda (mm).
    // Positivo Y = bajar. Con slots sin hacks DomPDF suele bastar ~0.5–1.0.
    'stamp_content_offset_x' => (float) env('PDF_STAMP_CONTENT_OFFSET_X', 0),
    'stamp_content_offset_y' => (float) env('PDF_STAMP_CONTENT_OFFSET_Y', 0),

    // Borde fino alrededor de cada participación en la hoja (ayuda a diferenciar celdas).
    'stamp_cell_border' => env('PDF_STAMP_CELL_BORDER', true),

    // Bordes rosa de depuración en cajas de elementos / stamps (padding vs posición).
    'debug_element_borders' => filter_var(env('PDF_DEBUG_ELEMENT_BORDERS', false), FILTER_VALIDATE_BOOLEAN),

    // Enviar email con enlace de descarga al terminar el PDF (por defecto no).
    'send_email' => env('PDF_SEND_EMAIL', false),
];

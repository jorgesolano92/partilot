<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración de Optimización de PDFs
    |--------------------------------------------------------------------------
    |
    | Configuraciones para optimizar el rendimiento de generación de PDFs
    | con muchas participaciones.
    |
    */

    // Límites para procesamiento síncrono vs asíncrono
    'sync_limit' => 500,        // Hasta 500 participaciones se procesan síncronamente
    'async_limit' => 1000,      // Más de 1000 participaciones se procesan asíncronamente
    
    // Tamaño de chunks para procesamiento por lotes
    'chunk_size' => 100,        // Procesar de 100 en 100 participaciones
    'job_chunk_size' => 100,    // Chunks en jobs asíncronos (menos renders = menos tiempo total)

    // Cola de jobs PDF (usar "default" salvo que haya worker dedicado: PDF_QUEUE=pdf)
    'queue' => env('PDF_QUEUE', 'default'),

    // Configuración de memoria y tiempo
    'memory_limit' => '2048M',  // Límite de memoria para PDFs grandes
    'max_execution_time' => 300, // 5 minutos para PDFs síncronos
    'job_timeout' => 0,         // 0 = calcular según participaciones (ver job_timeout_*)
    'job_timeout_per_chunk' => 120, // Segundos estimados por chunk DomPDF
    'job_timeout_min' => 900,   // Mínimo 15 minutos
    'job_timeout_max' => 7200,    // Máximo 2 horas
    
    // Cache
    'cache_ttl' => 3600,        // TTL del cache en segundos (1 hora)
    'cache_prefix' => 'pdf_',   // Prefijo para las claves de cache
    
    // Configuración de archivos temporales
    'temp_path' => 'temp_pdfs/',
    'generated_path' => 'generated_pdfs/',
    'cleanup_temp' => true,     // Limpiar archivos temporales automáticamente
    
    // Configuración de DomPDF
    'dompdf_options' => [
        'defaultFont' => 'Arial',
        'isRemoteEnabled' => true,
        'isHtml5ParserEnabled' => true,
        'isPhpEnabled' => true,
    ],
];

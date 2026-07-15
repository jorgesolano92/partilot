<?php

namespace App\Services;

use App\Support\ParticipationTicketReference;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\QrCodeInterface;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Cache;

class EndroidQrCodeService
{
    private function qrUrlForReference(string $reference): string
    {
        return ParticipationTicketReference::signedCheckUrl($reference);
    }

    /**
     * PNG vía GD cuando está disponible; si no (p. ej. PHP CLI/worker sin extension=gd),
     * SVG puro sin dependencias gráficas — válido en navegador y DomPDF.
     */
    private function isGdAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    private function qrCodeToDataUri(QrCodeInterface $qrCode): string
    {
        if ($this->isGdAvailable()) {
            return (new PngWriter())->write($qrCode)->getDataUri();
        }

        return (new SvgWriter())->write($qrCode)->getDataUri();
    }

    /**
     * Generar QR code desde texto raw (p. ej. taco_ref) - sin imagick.
     * Usado en PDF portadas de tacos.
     */
    public function generateQrFromTextBase64(string $text, int $size = 200): string
    {
        $qrCode = QrCode::create($text)
            ->setSize($size)
            ->setMargin(2);

        return $this->qrCodeToDataUri($qrCode);
    }

    /**
     * Generar QR code como base64 (optimizado con Endroid)
     */
    public function generateQrCodeBase64($reference)
    {
        // Cache simple para evitar regenerar
        $cacheKey = 'endroid_qr_v2_'.md5($reference.ParticipationTicketReference::publicCheckBaseUrl());
        $cached = Cache::get($cacheKey);
        
        if ($cached) {
            return $cached;
        }
        
        $url = $this->qrUrlForReference($reference);
        
        // Crear QR code con Endroid (mucho más rápido)
        $qrCode = QrCode::create($url)
            ->setSize(200)
            ->setMargin(2);

        $dataUri = $this->qrCodeToDataUri($qrCode);

        // Cache por 30 minutos
        Cache::put($cacheKey, $dataUri, 1800);

        return $dataUri;
    }

    /**
     * Generar múltiples QR codes en lote (ultra-optimizado con Endroid - solo memoria)
     */
    public function generateMultipleQrCodes($references)
    {
        $results = [];
        $toGenerate = [];
        
        // Verificar cuáles ya están en cache
        foreach ($references as $reference) {
            $cacheKey = 'endroid_qr_v2_'.md5($reference.ParticipationTicketReference::publicCheckBaseUrl());
            $cached = Cache::get($cacheKey);
            
            if ($cached) {
                $results[$reference] = $cached;
            } else {
                $toGenerate[] = $reference;
            }
        }
        
        // Generar los que no están en cache (solo en memoria, sin archivos)
        if (!empty($toGenerate)) {
            $batchResults = $this->generateUltraFastBatchInMemory($toGenerate);
            $results = array_merge($results, $batchResults);
        }
        
        return $results;
    }

    /**
     * Generación ultra-rápida con Endroid (solo memoria, sin archivos)
     */
    private function generateUltraFastBatchInMemory($references)
    {
        $results = [];
        
        // Configuración ultra-optimizada para máxima velocidad
        $qrCode = QrCode::create('')
            ->setSize(config('qr_optimization.qr_code.size', 120))
            ->setMargin(config('qr_optimization.qr_code.margin', 0));

        foreach ($references as $reference) {
            $url = $this->qrUrlForReference($reference);

            // Actualizar solo la URL (más eficiente)
            $qrCode = $qrCode->setData($url);
            $dataUri = $this->qrCodeToDataUri($qrCode);

            // Cache inmediato (solo en memoria)
            $cacheKey = 'endroid_qr_v2_'.md5($reference.ParticipationTicketReference::publicCheckBaseUrl());
            Cache::put($cacheKey, $dataUri, 1800);

            $results[$reference] = $dataUri;
        }
        
        return $results;
    }

    /**
     * Generación ultra-rápida con Endroid (versión con archivos - mantenida para compatibilidad)
     */
    private function generateUltraFastBatch($references)
    {
        foreach ($references as $reference) {
            $url = $this->qrUrlForReference($reference);

            // Actualizar solo la URL (más eficiente)
            $qrCode = $qrCode->setData($url);
            $dataUri = $this->qrCodeToDataUri($qrCode);

            // Cache inmediato
            $cacheKey = 'endroid_qr_v2_'.md5($reference.ParticipationTicketReference::publicCheckBaseUrl());
            Cache::put($cacheKey, $dataUri, 1800);

            $results[$reference] = $dataUri;
        }
        
        return $results;
    }

    /**
     * Generación ultra-rápida con configuración mínima (solo memoria)
     */
    public function generateUltraFastQrCodes($references)
    {
        $results = [];
        
        // Configuración ultra-optimizada para máxima velocidad
        $qrCode = QrCode::create('')
            ->setSize(120)
            ->setMargin(0);

        // Procesar en lotes para mejor gestión de memoria
        $batchSize = 100; // Lotes más grandes para mejor eficiencia
        $batches = array_chunk($references, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            error_log("Endroid QR: Procesando lote " . ($batchIndex + 1) . " de " . count($batches) . " (" . count($batch) . " QR codes)");

            foreach ($batch as $reference) {
                $url = $this->qrUrlForReference($reference);

                // Actualizar solo la URL
                $qrCode = $qrCode->setData($url);
                $dataUri = $this->qrCodeToDataUri($qrCode);

                // Cache inmediato (solo en memoria)
                $cacheKey = 'endroid_qr_v2_'.md5($reference.ParticipationTicketReference::publicCheckBaseUrl());
                Cache::put($cacheKey, $dataUri, 1800);

                $results[$reference] = $dataUri;
            }
        }
        
        return $results;
    }

    /**
     * Limpiar cache de QR codes
     */
    public function clearQrCache()
    {
        // Limpiar cache de Endroid QR codes
        $keys = Cache::get('endroid_qr_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget('endroid_qr_keys');
    }

    /**
     * Obtener estadísticas de cache
     */
    public function getCacheStats()
    {
        return [
            'cache_driver' => config('cache.default'),
            'cache_prefix' => 'endroid_qr_base64_',
            'cache_ttl' => '30 minutos'
        ];
    }
}

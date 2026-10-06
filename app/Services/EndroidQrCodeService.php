<?php

namespace App\Services;

use App\Support\ParticipationTicketReference;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\QrCodeInterface;
use Endroid\QrCode\RoundBlockSizeMode;
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
        $cacheKey = 'endroid_qr_v3_'.md5($reference.ParticipationTicketReference::publicCheckBaseUrl());
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
            $cacheKey = 'endroid_qr_v3_'.md5($reference.ParticipationTicketReference::publicCheckBaseUrl());
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
            $cacheKey = 'endroid_qr_v3_'.md5($reference.ParticipationTicketReference::publicCheckBaseUrl());
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
            $cacheKey = 'endroid_qr_v3_'.md5($reference.ParticipationTicketReference::publicCheckBaseUrl());
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
        $size = max(40, (int) config('qr_optimization.qr_code.size', 100));
        $margin = max(0, (int) config('qr_optimization.qr_code.margin', 0));

        $qrCode = QrCode::create('')
            ->setSize($size)
            ->setMargin($margin);

        $batchSize = max(50, (int) config('qr_optimization.performance.batch_size', 100));
        $batches = array_chunk($references, $batchSize);

        foreach ($batches as $batch) {
            foreach ($batch as $reference) {
                $url = $this->qrUrlForReference((string) $reference);
                $qrCode = $qrCode->setData($url);
                $dataUri = $this->qrCodeToDataUri($qrCode);

                $cacheKey = 'endroid_qr_v3_'.md5($reference.ParticipationTicketReference::publicCheckBaseUrl());
                Cache::put($cacheKey, $dataUri, (int) config('qr_optimization.qr_code.cache_ttl', 1800));

                $results[$reference] = $dataUri;
            }
        }

        return $results;
    }

    /**
     * QR como rutas de fichero PNG (mejor para DomPDF: reutiliza XObject por path).
     *
     * @param  string[]  $references
     * @return array<string, string> ref => absolute path
     */
    public function generateUltraFastQrCodeFilePaths(array $references): array
    {
        $results = [];
        if ($references === []) {
            return $results;
        }

        $dir = storage_path('app/pdf_qr_cache');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $size = max(120, (int) config('qr_optimization.qr_code.size', 160));
        // Margen 0 + recorte cuadrado centrado (sin quiet zone asimétrico).
        $margin = 0;

        $qrCode = QrCode::create('')
            ->setSize($size)
            ->setMargin($margin)
            ->setRoundBlockSizeMode(RoundBlockSizeMode::None)
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255, 0));

        $useGd = $this->isGdAvailable();
        $writer = $useGd ? new PngWriter() : new SvgWriter();
        $ext = $useGd ? 'png' : 'svg';

        foreach ($references as $reference) {
            $reference = (string) $reference;
            $url = $this->qrUrlForReference($reference);
            $path = $dir.'/'.md5($url.'|'.$size.'|0|'.$ext.'|centered5').'.'.$ext;
            $pathNorm = str_replace('\\', '/', $path);

            if (! is_file($path)) {
                $qrCode = $qrCode->setData($url);
                $binary = $writer->write($qrCode)->getString();
                if ($useGd) {
                    $binary = $this->cropQrPngCenteredSquare($binary, 1) ?? $binary;
                }
                file_put_contents($path, $binary);
            }

            $results[$reference] = $pathNorm;
        }

        return $results;
    }

    /**
     * QR de texto libre (taco_ref) como ficheros PNG/SVG para FPDI.
     *
     * @param  string[]  $texts
     * @return array<string, string> text => absolute path
     */
    public function generateQrFromTextFilePaths(array $texts): array
    {
        $results = [];
        if ($texts === []) {
            return $results;
        }

        $dir = storage_path('app/pdf_qr_cache');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $size = max(120, (int) config('qr_optimization.qr_code.size', 160));
        $useGd = $this->isGdAvailable();
        $writer = $useGd ? new PngWriter() : new SvgWriter();
        $ext = $useGd ? 'png' : 'svg';

        $qrCode = QrCode::create('')
            ->setSize($size)
            ->setMargin(0)
            ->setRoundBlockSizeMode(RoundBlockSizeMode::None)
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255));

        foreach ($texts as $text) {
            $text = (string) $text;
            if ($text === '') {
                continue;
            }
            $path = $dir.'/taco_'.md5($text.'|'.$size.'|0|'.$ext).'.'.$ext;
            $pathNorm = str_replace('\\', '/', $path);
            if (! is_file($path)) {
                $qrCode = $qrCode->setData($text);
                $binary = $writer->write($qrCode)->getString();
                if ($useGd) {
                    $binary = $this->cropQrPngCenteredSquare($binary, 1) ?? $binary;
                }
                file_put_contents($path, $binary);
            }
            $results[$text] = $pathNorm;
        }

        return $results;
    }

    /**
     * Recorta a un cuadrado centrado en los módulos negros, con quiet zone uniforme.
     */
    private function cropQrPngCenteredSquare(string $pngBinary, int $padPx = 2): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $src = @imagecreatefromstring($pngBinary);
        if ($src === false) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($src, $x, $y);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                // Módulo negro (fondo blanco/gris claro se ignora)
                if ($r < 64 && $g < 64 && $b < 64) {
                    if ($x < $minX) {
                        $minX = $x;
                    }
                    if ($y < $minY) {
                        $minY = $y;
                    }
                    if ($x > $maxX) {
                        $maxX = $x;
                    }
                    if ($y > $maxY) {
                        $maxY = $y;
                    }
                }
            }
        }

        if ($maxX < 0 || $maxY < 0) {
            imagedestroy($src);

            return null;
        }

        $contentW = $maxX - $minX + 1;
        $contentH = $maxY - $minY + 1;
        $padPx = max(0, $padPx);
        $inner = max($contentW, $contentH);
        $side = $inner + (2 * $padPx);

        // Centrar el bloque de módulos en el cuadrado (márgenes L/R y T/B iguales ±1px).
        $dstX = (int) floor(($side - $contentW) / 2);
        $dstY = (int) floor(($side - $contentH) / 2);

        $dst = imagecreatetruecolor($side, $side);
        $white = imagecolorallocate($dst, 255, 255, 255);
        $black = imagecolorallocate($dst, 0, 0, 0);
        imagefilledrectangle($dst, 0, 0, $side - 1, $side - 1, $white);

        for ($y = 0; $y < $contentH; $y++) {
            for ($x = 0; $x < $contentW; $x++) {
                $rgba = imagecolorat($src, $minX + $x, $minY + $y);
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                if ($r < 64 && $g < 64 && $b < 64) {
                    imagesetpixel($dst, $dstX + $x, $dstY + $y, $black);
                }
            }
        }

        ob_start();
        imagepng($dst);
        $out = ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return is_string($out) && $out !== '' ? $out : null;
    }

    /**
     * Asegura PNG truecolor con canal alpha (FPDI/FPDF lo respeta mejor).
     */
    private function ensurePngHasAlpha(string $pngBinary): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $im = @imagecreatefromstring($pngBinary);
        if ($im === false) {
            return null;
        }
        imagesavealpha($im, true);
        imagealphablending($im, false);
        ob_start();
        imagepng($im);
        $out = ob_get_clean();
        imagedestroy($im);

        return is_string($out) && $out !== '' ? $out : null;
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

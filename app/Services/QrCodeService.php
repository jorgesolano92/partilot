<?php

namespace App\Services;

use App\Support\ParticipationTicketReference;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class QrCodeService
{
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = ParticipationTicketReference::publicCheckBaseUrl().'/comprobar-participacion?ref=';
    }

    /**
     * Crear QR code y guardarlo en archivo
     */
    public function createQrCode($url, $path)
    {
        // Asegurar que el directorio existe
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        QrCode::format('png')
            ->size(300)
            ->margin(1)
            ->errorCorrection('M') // Nivel medio de corrección de errores
            ->generate($url, $path);
    }

    /**
     * Obtener ruta del QR code
     */
    private function getQrCodePath($reference)
    {
        $qrDir = storage_path('app/qr_codes');
        if (!is_dir($qrDir)) {
            mkdir($qrDir, 0755, true);
        }
        
        $qrPath = $qrDir . '/' . md5($reference) . '.png';
        return $qrPath;
    }

    /**
     * Generar QR code como base64 para HTML inline (optimizado)
     */
    public function generateQrCodeBase64($reference)
    {
        // Primero intentar obtener desde archivo guardado
        $qrPath = $this->getQrCodePath($reference);
        
        if (file_exists($qrPath)) {
            // Si el archivo existe, convertirlo a base64
            $qrCodeData = file_get_contents($qrPath);
            return 'data:image/png;base64,' . base64_encode($qrCodeData);
        }
        
        // Si no existe, generar y guardar
        $url = $this->baseUrl . $reference;
        
        // Configuración optimizada para velocidad y calidad
        $qrCode = QrCode::format('png')
            ->size(200) // Tamaño optimizado
            ->margin(2) // Margen mínimo
            ->errorCorrection('M') // Nivel medio para balance velocidad/calidad
            ->generate($url);
        
        // Guardar el archivo PNG
        file_put_contents($qrPath, $qrCode);
        
        // Retornar como base64
        return 'data:image/png;base64,' . base64_encode($qrCode);
    }

    /**
     * Generar múltiples QR codes en lote (optimizado)
     */
    public function generateMultipleQrCodes($references)
    {
        $results = [];
        $toGenerate = [];
        
        // Verificar cuáles ya existen
        foreach ($references as $reference) {
            $qrPath = $this->getQrCodePath($reference);
            if (file_exists($qrPath)) {
                // Si existe, cargar desde archivo
                $qrCodeData = file_get_contents($qrPath);
                $results[$reference] = 'data:image/png;base64,' . base64_encode($qrCodeData);
            } else {
                // Si no existe, agregar a la lista de generación
                $toGenerate[] = $reference;
            }
        }
        
        // Generar los que no existen en lote
        if (!empty($toGenerate)) {
            $batchResults = $this->generateBatchQrCodes($toGenerate);
            $results = array_merge($results, $batchResults);
        }
        
        return $results;
    }

    /**
     * Generar QR codes en lote (optimizado para velocidad)
     */
    private function generateBatchQrCodes($references)
    {
        // Siempre usar generación ultra-rápida para mejor rendimiento
        return $this->generateUltraFastQrCodes($references);
    }

    /**
     * Generación ultra-rápida optimizada para cualquier cantidad de QR codes
     */
    private function generateUltraFastQrCodes($references)
    {
        $results = [];
        $baseUrl = $this->baseUrl;
        
        // Configuración ultra-optimizada para máxima velocidad
        $qrConfig = [
            'size' => 60, // Tamaño mínimo para velocidad máxima
            'margin' => 0, // Sin margen para velocidad
            'errorCorrection' => 'L' // Nivel bajo para máxima velocidad
        ];
        
        // Para cantidades pequeñas, usar memoria pura
        if (count($references) <= 200) {
            return $this->processUltraFastBatchInMemory($references, $baseUrl, $qrConfig);
        }
        
        // Para cantidades grandes, usar híbrido: generar en memoria pero cachear
        $batchSize = 50; // Lotes más pequeños para mejor gestión de memoria
        $batches = array_chunk($references, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            // Log del progreso (opcional)
            error_log("QR Ultra-Fast: Procesando lote " . ($batchIndex + 1) . " de " . count($batches) . " (" . count($batch) . " QR codes)");
            
            $batchResults = $this->processUltraFastBatchHybrid($batch, $baseUrl, $qrConfig);
            $results = array_merge($results, $batchResults);
        }
        
        return $results;
    }

    /**
     * Procesar lote ultra-rápido en memoria (solo base64, sin archivos)
     */
    private function processUltraFastBatchInMemory($references, $baseUrl, $qrConfig)
    {
        $results = [];
        
        // Usar cache en memoria para evitar regenerar
        $cache = [];
        
        foreach ($references as $reference) {
            $url = $baseUrl . $reference;
            
            // Verificar cache en memoria primero
            $cacheKey = md5($url);
            if (isset($cache[$cacheKey])) {
                $results[$reference] = $cache[$cacheKey];
                continue;
            }
            
            // Generar QR code con configuración ultra-optimizada
            $qrCode = QrCode::format('png')
                ->size($qrConfig['size'])
                ->margin($qrConfig['margin'])
                ->errorCorrection($qrConfig['errorCorrection'])
                ->generate($url);
            
            // Convertir a base64
            $base64 = 'data:image/png;base64,' . base64_encode($qrCode);
            
            // Cache en memoria
            $cache[$cacheKey] = $base64;
            $results[$reference] = $base64;
        }
        
        return $results;
    }

    /**
     * Procesar lote híbrido ultra-rápido (memoria + cache opcional)
     */
    private function processUltraFastBatchHybrid($references, $baseUrl, $qrConfig)
    {
        $results = [];
        
        foreach ($references as $reference) {
            $url = $baseUrl . $reference;
            
            // Verificar si ya existe en cache
            $qrPath = $this->getQrCodePath($reference);
            if (file_exists($qrPath)) {
                // Si existe, cargar desde archivo (más rápido que regenerar)
                $qrCodeData = file_get_contents($qrPath);
                $results[$reference] = 'data:image/png;base64,' . base64_encode($qrCodeData);
            } else {
                // Si no existe, generar en memoria y opcionalmente guardar
                $qrCode = QrCode::format('png')
                    ->size($qrConfig['size'])
                    ->margin($qrConfig['margin'])
                    ->errorCorrection($qrConfig['errorCorrection'])
                    ->generate($url);
                
                // Guardar para futuras reutilizaciones (solo si es necesario)
                file_put_contents($qrPath, $qrCode);
                
                // Convertir a base64
                $results[$reference] = 'data:image/png;base64,' . base64_encode($qrCode);
            }
        }
        
        return $results;
    }

    /**
     * Procesar lote ultra-rápido (versión con archivos - mantenida para compatibilidad)
     */
    private function processUltraFastBatch($references, $baseUrl, $qrConfig)
    {
        $results = [];
        
        foreach ($references as $reference) {
            $url = $baseUrl . $reference;
            $qrPath = $this->getQrCodePath($reference);
            
            // Generar QR code con configuración optimizada
            $qrCode = QrCode::format('png')
                ->size($qrConfig['size'])
                ->margin($qrConfig['margin'])
                ->errorCorrection($qrConfig['errorCorrection'])
                ->generate($url);
            
            // Guardar archivo
            file_put_contents($qrPath, $qrCode);
            
            // Convertir a base64
            $results[$reference] = 'data:image/png;base64,' . base64_encode($qrCode);
        }
        
        return $results;
    }

    /**
     * Procesar un lote de QR codes
     */
    private function processBatch($references)
    {
        $results = [];
        
        foreach ($references as $reference) {
            $url = $this->baseUrl . $reference;
            $qrPath = $this->getQrCodePath($reference);
            
            // Generar QR code
            $qrCode = QrCode::format('png')
                ->size(200)
                ->margin(2)
                ->errorCorrection('M')
                ->generate($url);
            
            // Guardar archivo
            file_put_contents($qrPath, $qrCode);
            
            // Convertir a base64
            $results[$reference] = 'data:image/png;base64,' . base64_encode($qrCode);
        }
        
        return $results;
    }

    /**
     * Limpiar QR codes antiguos
     */
    public function clearOldQrCodes($hours = 24)
    {
        $qrDir = storage_path('app/qr_codes');
        
        if (!is_dir($qrDir)) {
            return 0;
        }

        $deleted = 0;
        $cutoffTime = time() - ($hours * 3600);
        
        $files = glob($qrDir . '/*.png');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }

    /**
     * Obtener estadísticas de QR codes
     */
    public function getQrCodeStats()
    {
        $qrDir = storage_path('app/qr_codes');
        
        if (!is_dir($qrDir)) {
            return [
                'total_files' => 0,
                'total_size' => 0,
                'average_size' => 0,
                'directory' => $qrDir
            ];
        }

        $files = glob($qrDir . '/*.png');
        $totalFiles = count($files);
        $totalSize = 0;

        foreach ($files as $file) {
            $totalSize += filesize($file);
        }

        return [
            'total_files' => $totalFiles,
            'total_size' => $totalSize,
            'average_size' => $totalFiles > 0 ? $totalSize / $totalFiles : 0,
            'directory' => $qrDir
        ];
    }
}
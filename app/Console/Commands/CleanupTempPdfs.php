<?php

namespace App\Console\Commands;

use App\Support\GeneratedPdfCatalog;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanupTempPdfs extends Command
{
    protected $signature = 'pdf:cleanup {--hours=168 : Horas de antigüedad (168 = 7 días)}';

    protected $description = 'Limpiar PDFs generados antiguos (por defecto tras 7 días)';

    public function handle()
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoffTime = Carbon::now()->subHours($hours);

        $tempPath = storage_path('app/temp_pdfs');
        $generatedPath = storage_path('app/generated_pdfs');

        $cleaned = 0;

        if (is_dir($tempPath)) {
            foreach (glob($tempPath.DIRECTORY_SEPARATOR.'*.pdf') ?: [] as $file) {
                if (is_file($file) && filemtime($file) < $cutoffTime->timestamp) {
                    @unlink($file);
                    $cleaned++;
                }
            }
        }

        if (is_dir($generatedPath)) {
            foreach (glob($generatedPath.DIRECTORY_SEPARATOR.'*.meta.json') ?: [] as $metaFile) {
                $jobId = basename($metaFile, '.meta.json');
                if (GeneratedPdfCatalog::isExpired($jobId)) {
                    GeneratedPdfCatalog::deleteArtifacts($jobId);
                    $cleaned++;
                }
            }

            foreach (glob($generatedPath.DIRECTORY_SEPARATOR.'*.pdf') ?: [] as $pdfFile) {
                if (! is_file($pdfFile)) {
                    continue;
                }
                $jobId = basename($pdfFile, '.pdf');
                // Sin meta o caducado por antigüedad del fichero
                if (GeneratedPdfCatalog::isExpired($jobId) || filemtime($pdfFile) < $cutoffTime->timestamp) {
                    GeneratedPdfCatalog::deleteArtifacts($jobId);
                    $cleaned++;
                }
            }
        }

        $this->info("Se limpiaron {$cleaned} archivos PDF temporales/generados (umbral {$hours}h).");

        return 0;
    }
}

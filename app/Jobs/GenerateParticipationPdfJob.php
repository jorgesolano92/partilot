<?php

namespace App\Jobs;

use App\Http\Controllers\DesignController;
use App\Models\DesignFormat;
use App\Models\Set;
use App\Services\DesignApprovalService;
use App\Services\ManagementFeeService;
use App\Support\FpdiPdfMerge;
use App\Support\GeneratedPdfCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateParticipationPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Tiempo máximo del job (segundos); el worker usa este valor si está definido. */
    public int $timeout;

    public int $tries = 1;

    protected int $designId;

    protected string $jobId;

    protected int $participationFrom;

    protected int $participationTo;

    public function __construct(int $designId, string $jobId, int $participationFrom, int $participationTo)
    {
        $this->designId = $designId;
        $this->jobId = (string) $jobId;
        $this->participationFrom = $participationFrom;
        $this->participationTo = $participationTo;
        $this->timeout = self::resolveTimeout($participationFrom, $participationTo);
        $this->onQueue((string) config('pdf_optimization.queue', 'pdf'));
    }

    public static function resolveTimeout(int $from, int $to): int
    {
        $fixedTimeout = (int) config('pdf_optimization.job_timeout', 0);
        if ($fixedTimeout > 0) {
            return $fixedTimeout;
        }

        $count = max(1, $to - $from + 1);
        $chunkSize = max(1, (int) config('pdf_optimization.job_chunk_size', 100));
        $perChunk = max(30, (int) config('pdf_optimization.job_timeout_per_chunk', 120));
        $min = max(60, (int) config('pdf_optimization.job_timeout_min', 900));
        $max = max($min, (int) config('pdf_optimization.job_timeout_max', 7200));

        $chunks = (int) ceil($count / $chunkSize);

        return min($max, max($min, $chunks * $perChunk));
    }

    public function handle(): void
    {
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', (string) config('pdf_optimization.memory_limit', '2048M'));

        $design = DesignFormat::findOrFail($this->designId);

        if ($design->set_id) {
            $set = Set::with('entity')->find($design->set_id);
            if ($set) {
                $feeService = app(ManagementFeeService::class);
                $feeService->ensureSnapshot($set, $design);
                if ($feeService->blocksQrExport($set, $design)) {
                    throw new \RuntimeException(
                        app(DesignApprovalService::class)->blockMessage($design)
                    );
                }
            }
        }

        $controller = app(DesignController::class);

        $cacheKey = 'participation_html_pdf_v9_'.$this->designId;
        $participation_html = cache()->remember($cacheKey, 3600, function () use ($design, $controller) {
            return $controller->prepareParticipationHtmlForPdf($design->participation_html ?? '');
        });

        $set = $design->set_id ? Set::select('id', 'tickets', 'total_participations')->find($design->set_id) : null;
        $tickets = $set && $set->tickets ? $set->tickets : [];

        $from = $this->participationFrom;
        $to = $this->participationTo;

        $tickets_slice = [];
        if ($from <= $to && $to >= 1) {
            $tickets_slice = array_slice($tickets, $from - 1, max(0, $to - $from + 1));
        }

        if (config('qr_optimization.optimize_images', false)) {
            $participation_html = $controller->optimizeParticipationHtml($participation_html, $tickets_slice);
        }

        $uniqueReferences = [];
        foreach ($tickets_slice as $ticket) {
            if (isset($ticket['r']) && ! in_array($ticket['r'], $uniqueReferences)) {
                $uniqueReferences[] = $ticket['r'];
            }
        }
        $qrService = new \App\Services\EndroidQrCodeService();
        $qrCodes = $uniqueReferences !== [] ? $qrService->generateUltraFastQrCodes($uniqueReferences) : [];

        $rows = $design->rows ?? 1;
        $cols = $design->cols ?? 1;
        $per_page = max(1, (int) $rows * (int) $cols);
        $page = $design->page ?? 'a3';
        $orientation = $design->orientation ?? 'h';
        $pdfOrientation = ($orientation === 'h') ? 'landscape' : 'portrait';

        $chunk_size = max(1, (int) config('pdf_optimization.job_chunk_size', 100));
        $temp_files = [];

        Log::info('GenerateParticipationPdfJob started', [
            'job_id' => $this->jobId,
            'design_id' => $this->designId,
            'from' => $from,
            'to' => $to,
            'chunk_size' => $chunk_size,
            'timeout' => $this->timeout,
        ]);

        try {
            for ($chunk_start = $from - 1; $chunk_start < $to; $chunk_start += $chunk_size) {
                $chunk_end = min($chunk_start + $chunk_size, $to);
                $chunk_tickets = array_slice($tickets, $chunk_start, $chunk_end - $chunk_start);

                $chunk_pages = $per_page > 0 ? (int) ceil(count($chunk_tickets) / $per_page) : 0;
                $pages = $this->generatePagesOptimized($chunk_tickets, $chunk_pages, $per_page);

                $pdf = Pdf::loadView('design.pdf_participation', [
                    'pages' => $pages,
                    'participation_html' => $participation_html,
                    'rows' => $rows,
                    'cols' => $cols,
                    'qrCodes' => $qrCodes,
                ])->setPaper($page, $pdfOrientation);

                $temp_file = storage_path('app/temp_pdf_'.$this->jobId.'_'.$chunk_start.'.pdf');
                $pdf->save($temp_file);
                $temp_files[] = $temp_file;

                Log::debug('GenerateParticipationPdfJob chunk saved', [
                    'job_id' => $this->jobId,
                    'chunk_start' => $chunk_start,
                    'chunk_end' => $chunk_end,
                ]);
            }

            Storage::makeDirectory('generated_pdfs');
            $final_path = storage_path('app/generated_pdfs/'.$this->jobId.'.pdf');

            if ($temp_files === []) {
                $pdf = Pdf::loadHTML('<html><body></body></html>')->setPaper($page, $pdfOrientation);
                $pdf->save($final_path);
            } else {
                FpdiPdfMerge::mergeTemporaryFiles($temp_files, $final_path);
            }

            GeneratedPdfCatalog::writeMeta(
                $this->jobId,
                'participacion-diseno-'.$this->designId.'.pdf',
                $this->designId
            );

            Log::info('GenerateParticipationPdfJob completed', [
                'job_id' => $this->jobId,
                'design_id' => $this->designId,
                'chunks' => count($temp_files),
            ]);
        } catch (\Throwable $e) {
            $this->cleanupTempFiles();
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->cleanupTempFiles();

        Log::error('GenerateParticipationPdfJob failed', [
            'job_id' => $this->jobId,
            'design_id' => $this->designId,
            'from' => $this->participationFrom,
            'to' => $this->participationTo,
            'timeout' => $this->timeout,
            'message' => $e->getMessage(),
        ]);
    }

    protected function cleanupTempFiles(): void
    {
        foreach (glob(storage_path('app/temp_pdf_'.$this->jobId.'_*.pdf')) ?: [] as $file) {
            @unlink($file);
        }
    }

    /**
     * @param  mixed[]  $tickets_to_print
     * @return mixed[][]
     */
    private function generatePagesOptimized(array $tickets_to_print, int $total_pages, int $per_page): array
    {
        $pages = [];
        $ticket_count = count($tickets_to_print);

        for ($p = 0; $p < $total_pages; $p++) {
            $pages[$p] = [];
            for ($i = 0; $i < $per_page; $i++) {
                $ticket_index = $p + ($i * $total_pages);
                if ($ticket_index < $ticket_count) {
                    $pages[$p][$i] = $tickets_to_print[$ticket_index];
                }
            }
        }

        return $pages;
    }
}

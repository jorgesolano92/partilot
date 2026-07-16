<?php

namespace App\Jobs;

use App\Http\Controllers\DesignController;
use App\Mail\DesignPdfReadyMail;
use App\Models\DesignFormat;
use App\Models\Set;
use App\Services\DesignApprovalService;
use App\Services\ManagementFeeService;
use App\Support\GeneratedPdfCatalog;
use App\Support\PdfJobStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Conservado por compatibilidad. La UI de participaciones genera el PDF en la petición HTTP
 * (síncrono). Este job solo se usaría si algo lo encola manualmente.
 */
class GenerateParticipationPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;

    public int $tries = 1;

    protected int $designId;

    protected string $jobId;

    protected int $participationFrom;

    protected int $participationTo;

    protected ?string $notifyEmail;

    public function __construct(
        int $designId,
        string $jobId,
        int $participationFrom,
        int $participationTo,
        ?string $notifyEmail = null
    ) {
        $this->designId = $designId;
        $this->jobId = (string) $jobId;
        $this->participationFrom = $participationFrom;
        $this->participationTo = $participationTo;
        $this->notifyEmail = $notifyEmail ? trim($notifyEmail) : null;
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

        $from = $this->participationFrom;
        $to = $this->participationTo;

        Log::info('GenerateParticipationPdfJob started', [
            'job_id' => $this->jobId,
            'design_id' => $this->designId,
            'from' => $from,
            'to' => $to,
            'timeout' => $this->timeout,
            'stamp_template' => (bool) config('pdf_optimization.use_stamp_template', false),
        ]);

        PdfJobStatus::markProcessing($this->jobId);

        try {
            Storage::makeDirectory('generated_pdfs');
            $final_path = storage_path('app/generated_pdfs/'.$this->jobId.'.pdf');
            app(DesignController::class)->writeParticipationPdfToFile($design, $from, $to, $final_path);

            GeneratedPdfCatalog::writeMeta(
                $this->jobId,
                'participacion-diseno-'.$this->designId.'.pdf',
                $this->designId
            );

            PdfJobStatus::markCompleted($this->jobId);
            $this->notifyByEmail();

            Log::info('GenerateParticipationPdfJob completed', [
                'job_id' => $this->jobId,
                'design_id' => $this->designId,
                'stamp_template' => (bool) config('pdf_optimization.use_stamp_template', false),
            ]);
        } catch (\Throwable $e) {
            $this->cleanupTempFiles();
            PdfJobStatus::markFailed($this->jobId, $e->getMessage());
            throw $e;
        }
    }

    protected function notifyByEmail(): void
    {
        if (! config('pdf_optimization.send_email', false)) {
            return;
        }
        if ($this->notifyEmail === null || $this->notifyEmail === '') {
            return;
        }
        if (PdfJobStatus::wasEmailSent($this->jobId)) {
            return;
        }

        try {
            Mail::to($this->notifyEmail)->send(new DesignPdfReadyMail(
                route('design.downloadPdf', $this->jobId),
                'Participaciones PDF',
                $this->designId
            ));
            PdfJobStatus::markEmailSent($this->jobId);
            Log::info('GenerateParticipationPdfJob emailed download link', [
                'job_id' => $this->jobId,
                'email' => $this->notifyEmail,
            ]);
        } catch (\Throwable $e) {
            Log::warning('GenerateParticipationPdfJob email failed', [
                'job_id' => $this->jobId,
                'email' => $this->notifyEmail,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->cleanupTempFiles();
        PdfJobStatus::markFailed($this->jobId, $e->getMessage());

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
}

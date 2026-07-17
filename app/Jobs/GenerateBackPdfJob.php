<?php

namespace App\Jobs;

use App\Http\Controllers\DesignController;
use App\Models\DesignFormat;
use App\Support\GeneratedPdfCatalog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateBackPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $designId;

    protected string $jobId;

    protected string $copies;

    protected string $filename;

    protected ?int $exactCount;

    public function __construct(int $designId, string $jobId, string $copies, string $filename, ?int $exactCount = null)
    {
        $this->designId = $designId;
        $this->jobId = $jobId;
        $this->copies = $copies;
        $this->filename = $filename;
        $this->exactCount = $exactCount;
    }

    public function handle(): void
    {
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1024M');

        $design = DesignFormat::findOrFail($this->designId);
        $controller = app(DesignController::class);

        Storage::makeDirectory('generated_pdfs');
        $final_path = storage_path('app/generated_pdfs/'.$this->jobId.'.pdf');
        try {
            $controller->writeBackPdfToFile($design, $final_path, $this->copies, $this->exactCount);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            \Log::error('GenerateBackPdfJob #'.$this->designId.': '.$e->getMessage());
            throw $e;
        }

        GeneratedPdfCatalog::writeMeta(
            $this->jobId,
            $this->filename,
            $this->designId
        );
    }
}

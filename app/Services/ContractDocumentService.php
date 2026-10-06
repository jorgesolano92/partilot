<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ContractDocumentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function renderHtml(string $view, array $data = []): string
    {
        return view($view, $data)->render();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function renderPdfBinary(string $view, array $data = [], string $paper = 'a4', string $orientation = 'portrait'): string
    {
        return Pdf::loadView($view, $data)
            ->setPaper($paper, $orientation)
            ->output();
    }

    public function storeBinary(string $relativePath, string $binary): string
    {
        Storage::disk('local')->put($relativePath, $binary);

        return $relativePath;
    }
}

<?php

namespace App\Services;

use App\Http\Controllers\DesignController;
use App\Models\DesignFormat;
use App\Support\ParticipationPdfLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

/**
 * Portada / trasera con el mismo esquema que participaciones:
 * DomPDF 1 celda + FPDI en rejilla + sangrado y marcas de corte.
 */
class CoverBackPdfStampExporter
{
    public function __construct(
        private DesignController $controller
    ) {}

    /**
     * @param  list<array{taco_ref:string,book_number:int,label:string}>  $books
     */
    public function exportCoversToFile(
        DesignFormat $design,
        string $coverHtmlPrepared,
        array $books,
        string $finalPath,
        ?string $slotsHtml = null
    ): void {
        $t0 = microtime(true);
        $layout = ParticipationPdfLayout::fromDesign($design, $design->cover_html ?? $coverHtmlPrepared);
        $rows = $layout->rows;
        $cols = $layout->cols;
        $perPage = $rows * $cols;
        $orientation = ($design->orientation ?? 'h') === 'h' ? 'L' : 'P';

        $staticHtml = $this->makeStaticCoverHtml($coverHtmlPrepared);
        $slots = $this->resolveCoverSlots($slotsHtml ?? $coverHtmlPrepared);
        $cellW = $layout->trimWidthMm;
        $cellH = $layout->trimHeightMm;
        $designW = $layout->designWidthMm;
        $designH = $layout->designHeightMm;

        $templatePath = storage_path('app/temp_pdf_stamp_cover_'.uniqid('', true).'.pdf');
        $this->renderCellTemplate($staticHtml, $designW, $designH, $templatePath);

        $refs = [];
        foreach ($books as $book) {
            $ref = (string) ($book['taco_ref'] ?? '');
            if ($ref !== '') {
                $refs[$ref] = $ref;
            }
        }
        $qrFiles = (new EndroidQrCodeService())->generateQrFromTextFilePaths(array_values($refs));

        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($templatePath);
        if ($pageCount < 1) {
            @unlink($templatePath);
            throw new \RuntimeException('No se pudo importar la plantilla de portada PDF.');
        }
        $tplId = $pdf->importPage(1);

        [$pageW, $pageH] = [$layout->sheetWidthMm, $layout->sheetHeightMm];
        $contentDx = (float) config('pdf_optimization.stamp_content_offset_x', 0);
        $contentDy = (float) config('pdf_optimization.stamp_content_offset_y', 0);
        $drawBorder = (bool) config('pdf_optimization.stamp_cell_border', false);

        $pages = array_values(array_chunk($books, $perPage));
        foreach ($pages as $pageBooks) {
            $pdf->AddPage($orientation, [$pageW, $pageH]);

            if ($layout->drawCropMarks) {
                $this->drawCutGrid($pdf, $layout);
            }

            for ($i = 0; $i < $perPage; $i++) {
                if (! isset($pageBooks[$i]) || ! is_array($pageBooks[$i])) {
                    continue;
                }
                $col = $i % $cols;
                $row = intdiv($i, $cols);
                $originX = $layout->trimOriginX($col);
                $originY = $layout->trimOriginY($row);

                $pdf->useTemplate($tplId, $originX, $originY, $cellW, $cellH);
                if ($drawBorder) {
                    $pdf->SetDrawColor(120, 120, 120);
                    $pdf->SetLineWidth(0.25);
                    $pdf->Rect($originX, $originY, $cellW, $cellH);
                }

                $this->stampCoverCell(
                    $pdf,
                    $pageBooks[$i],
                    $qrFiles,
                    $slots,
                    $originX + $contentDx,
                    $originY + $contentDy,
                    $cellW,
                    $cellH,
                    $designW,
                    $designH
                );
            }
        }

        $dir = dirname($finalPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdf->Output('F', $finalPath);
        @unlink($templatePath);

        Log::info('CoverBackPdfStampExporter covers done', [
            'books' => count($books),
            'pages' => count($pages),
            'total_ms' => (int) round((microtime(true) - $t0) * 1000),
        ]);
    }

    public function exportBackCopiesToFile(
        DesignFormat $design,
        string $backHtmlPrepared,
        int $copies,
        string $finalPath
    ): void {
        $t0 = microtime(true);
        $copies = max(1, $copies);
        $layout = ParticipationPdfLayout::fromDesign($design, $design->back_html ?? $backHtmlPrepared);
        $rows = $layout->rows;
        $cols = $layout->cols;
        $perPage = $rows * $cols;
        $orientation = ($design->orientation ?? 'h') === 'h' ? 'L' : 'P';

        $staticHtml = $this->makeStaticBackHtml($backHtmlPrepared);
        $cellW = $layout->trimWidthMm;
        $cellH = $layout->trimHeightMm;
        $designW = $layout->designWidthMm;
        $designH = $layout->designHeightMm;

        $templatePath = storage_path('app/temp_pdf_stamp_back_'.uniqid('', true).'.pdf');
        $this->renderCellTemplate($staticHtml, $designW, $designH, $templatePath);

        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($templatePath);
        if ($pageCount < 1) {
            @unlink($templatePath);
            throw new \RuntimeException('No se pudo importar la plantilla de trasera PDF.');
        }
        $tplId = $pdf->importPage(1);

        [$pageW, $pageH] = [$layout->sheetWidthMm, $layout->sheetHeightMm];
        $drawBorder = (bool) config('pdf_optimization.stamp_cell_border', false);

        $totalPages = (int) ceil($copies / $perPage);
        for ($p = 0; $p < $totalPages; $p++) {
            $pdf->AddPage($orientation, [$pageW, $pageH]);

            if ($layout->drawCropMarks) {
                $this->drawCutGrid($pdf, $layout);
            }

            for ($i = 0; $i < $perPage; $i++) {
                $col = $i % $cols;
                $row = intdiv($i, $cols);
                $originX = $layout->trimOriginX($col);
                $originY = $layout->trimOriginY($row);
                $index = $p + ($i * $totalPages);

                if ($index >= $copies) {
                    continue;
                }

                $pdf->useTemplate($tplId, $originX, $originY, $cellW, $cellH);
                if ($drawBorder) {
                    $pdf->SetDrawColor(120, 120, 120);
                    $pdf->SetLineWidth(0.25);
                    $pdf->Rect($originX, $originY, $cellW, $cellH);
                }
            }
        }

        $dir = dirname($finalPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdf->Output('F', $finalPath);
        @unlink($templatePath);

        Log::info('CoverBackPdfStampExporter backs done', [
            'copies' => $copies,
            'pages' => $totalPages,
            'total_ms' => (int) round((microtime(true) - $t0) * 1000),
        ]);
    }

    private function drawCutGrid(Fpdi $pdf, ParticipationPdfLayout $layout): void
    {
        if (! $layout->drawCropMarks) {
            return;
        }

        $pdf->SetDrawColor($layout->guideColorR, $layout->guideColorG, $layout->guideColorB);
        $pdf->SetLineWidth(max(0.1, $layout->guideLineWidthMm));

        foreach ($layout->cutGridSegments() as $segment) {
            $pdf->Line($segment['x1'], $segment['y1'], $segment['x2'], $segment['y2']);
        }
    }

    private function makeStaticCoverHtml(string $html): string
    {
        $html = preg_replace(
            '/<div[^>]*\bclass="[^"]*\bqr\b[^"]*"[^>]*>.*?<\/div>/is',
            '',
            $html,
            1
        ) ?? $html;

        $html = preg_replace_callback(
            '/(<div[^>]*\bclass="[^"]*\bcontext\b[^"]*"[^>]*>)(.*?)(<\/div>)/is',
            static fn (array $m): string => $m[1].$m[3],
            $html,
            1
        ) ?? $html;

        $html = str_ireplace(
            ['{{taco_label}}', '{{ taco_label }}', '@{{taco_label}}', '%%TACO_LABEL%%'],
            '',
            $html
        );

        return $html;
    }

    private function makeStaticBackHtml(string $html): string
    {
        return $html;
    }

    /**
     * @return array{qr:?array,context:?array}
     */
    private function resolveCoverSlots(string $html): array
    {
        $format = $this->resolveFormatBoxMm($html);
        $slots = ['qr' => null, 'context' => null];

        if (! preg_match_all(
            '/<div([^>]*\bclass="[^"]*\belements\b[^"]*"[^>]*)>(.*?)<\/div>/is',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            return $slots;
        }

        foreach ($matches as $match) {
            $attrs = $match[1];
            if (! preg_match('/\bclass="([^"]+)"/i', $attrs, $cm)) {
                continue;
            }
            $class = strtolower($cm[1]);
            if (! preg_match('/\bstyle="([^"]*)"/i', $attrs, $sm)) {
                continue;
            }
            $style = $sm[1];
            $box = $this->parseFlexibleBoxMm($style, $format['w'], $format['h']);
            if ($box === null) {
                continue;
            }

            if (preg_match('/(^|\s)qr(\s|$)/', $class)) {
                $slots['qr'] = $box;
            } elseif (str_contains($class, 'context')) {
                $slots['context'] = $box;
            }
        }

        return $slots;
    }

    /**
     * @return array{x:float,y:float,w:float,h:float,pad_l:float,pad_t:float,pad_b:float}|null
     */
    private function parseFlexibleBoxMm(string $style, float $formatWmm, float $formatHmm): ?array
    {
        $left = $this->cssLengthPx($style, 'left');
        $top = $this->cssLengthPx($style, 'top');

        $widthPx = $this->cssLengthPx($style, 'width');
        if ($widthPx === null && preg_match('/\bwidth\s*:\s*calc\(\s*100%\s*-\s*([\d.]+)\s*px\s*\)/i', $style, $m)) {
            $widthPx = ($formatWmm * 96.0 / 25.4) - (float) $m[1];
        }

        $heightPx = $this->cssLengthPx($style, 'height');
        if ($heightPx === null && preg_match('/\bheight\s*:\s*([\d.]+)\s*%/i', $style, $m)) {
            $heightPx = ($formatHmm * 96.0 / 25.4) * ((float) $m[1] / 100.0);
        }

        if ($left === null || $top === null || $widthPx === null || $heightPx === null) {
            return null;
        }

        [$padT, $padR, $padB, $padL] = $this->parsePaddingPx($style);

        return [
            'x' => $this->pxToMm($left),
            'y' => $this->pxToMm($top),
            'w' => $this->pxToMm(max(1.0, $widthPx)),
            'h' => $this->pxToMm(max(1.0, $heightPx)),
            'pad_l' => $this->pxToMm($padL),
            'pad_t' => $this->pxToMm($padT),
            'pad_b' => $this->pxToMm($padB),
        ];
    }

    /**
     * @return array{0:float,1:float,2:float,3:float}
     */
    private function parsePaddingPx(string $style): array
    {
        if (preg_match('/\bpadding\s*:\s*([^;]+)/i', $style, $m)) {
            $parts = preg_split('/\s+/', trim($m[1])) ?: [];
            $vals = [];
            foreach ($parts as $part) {
                if (preg_match('/([\d.]+)\s*px/i', $part, $pm)) {
                    $vals[] = (float) $pm[1];
                }
            }
            if (count($vals) === 1) {
                return [$vals[0], $vals[0], $vals[0], $vals[0]];
            }
            if (count($vals) === 2) {
                return [$vals[0], $vals[1], $vals[0], $vals[1]];
            }
            if (count($vals) === 3) {
                return [$vals[0], $vals[1], $vals[2], $vals[1]];
            }
            if (count($vals) >= 4) {
                return [$vals[0], $vals[1], $vals[2], $vals[3]];
            }
        }

        return [0.0, 0.0, 0.0, 0.0];
    }

    private function cssLengthPx(string $style, string $prop): ?float
    {
        if (preg_match('/\b'.preg_quote($prop, '/').'\s*:\s*([\d.]+)\s*px/i', $style, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    private function pxToMm(float $px): float
    {
        return $px * 25.4 / 96.0;
    }

    /**
     * @return array{w:float,h:float}
     */
    private function resolveFormatBoxMm(string $html): array
    {
        [$w, $h] = ParticipationPdfLayout::parseFormatBoxMm($html);

        return ['w' => $w, 'h' => $h];
    }

    private function renderCellTemplate(
        string $staticHtml,
        float $cellWmm,
        float $cellHmm,
        string $templatePath
    ): void {
        $wPt = $cellWmm * 72.0 / 25.4;
        $hPt = $cellHmm * 72.0 / 25.4;
        $pdf = Pdf::loadView('design.pdf_participation_cell', [
            'participation_html' => $staticHtml,
        ])->setPaper([0, 0, $wPt, $hPt]);
        $this->controller->applyDompdfOptions($pdf);
        $pdf->save($templatePath);
    }

    /**
     * @param  array{taco_ref?:string,label?:string}  $book
     * @param  array<string,string>  $qrFiles
     * @param  array{qr:?array,context:?array}  $slots
     */
    private function stampCoverCell(
        Fpdi $pdf,
        array $book,
        array $qrFiles,
        array $slots,
        float $originX,
        float $originY,
        float $cellW,
        float $cellH,
        float $designW,
        float $designH
    ): void {
        $scaleX = $designW > 0 ? ($cellW / $designW) : 1.0;
        $scaleY = $designH > 0 ? ($cellH / $designH) : 1.0;
        $ref = (string) ($book['taco_ref'] ?? '');
        $label = (string) ($book['label'] ?? '');

        if ($slots['qr'] !== null && $ref !== '' && isset($qrFiles[$ref])) {
            $src = $qrFiles[$ref];
            if (is_string($src) && $src !== '' && is_file($src)) {
                $q = $slots['qr'];
                $boxW = $q['w'] * $scaleX;
                $boxH = $q['h'] * $scaleY;
                $minMm = max(5.0, (float) config('qr_optimization.qr_code.min_print_size_mm', 15));
                $side = max($boxW, $boxH, $minMm);
                $qx = $originX + ($q['x'] * $scaleX);
                $qy = $originY + ($q['y'] * $scaleY);
                $this->debugStampBox($pdf, $qx, $qy, $side, $side);
                $pdf->Image($src, $qx, $qy, $side, $side);
            }
        }

        if ($slots['context'] !== null && $label !== '') {
            $c = $slots['context'];
            $bx = $originX + ($c['x'] * $scaleX);
            $by = $originY + ($c['y'] * $scaleY);
            $bw = $c['w'] * $scaleX;
            $bh = $c['h'] * $scaleY;
            $this->debugStampBox($pdf, $bx, $by, $bw, $bh);

            $fontPt = max(10.0, min(18.0, ($bh * 72.0 / 25.4) * 0.45));
            $safe = $this->fpdiSafeText($label);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Helvetica', 'B', $fontPt);
            $tw = $pdf->GetStringWidth($safe);
            $fontMm = $fontPt * (25.4 / 72.0);
            $glyphHmm = $fontMm * 0.75;
            $textX = $bx + max(0.0, ($bw - $tw) / 2.0);
            $textTop = $by + max(0.0, ($bh - $glyphHmm) / 2.0);
            $baseline = $textTop + ($fontMm * 0.72);
            $pdf->Text($textX, $baseline, $safe);
        }
    }

    private function debugStampBox(Fpdi $pdf, float $x, float $y, float $w, float $h): void
    {
        if (! config('pdf_optimization.debug_element_borders', false)) {
            return;
        }
        $pdf->SetDrawColor(255, 20, 147);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($x, $y, $w, $h);
    }

    private function fpdiSafeText(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);

        return $converted !== false ? $converted : $text;
    }
}

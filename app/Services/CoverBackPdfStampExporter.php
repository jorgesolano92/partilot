<?php

namespace App\Services;

use App\Http\Controllers\DesignController;
use App\Models\DesignFormat;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

/**
 * Portada / trasera con el mismo esquema que participaciones:
 * DomPDF 1 celda + FPDI en rejilla fija + stamps (QR / etiqueta taco en portada).
 * No modifica ParticipationPdfStampExporter::exportToFile.
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
        $rows = max(1, (int) ($design->rows ?? 1));
        $cols = max(1, (int) ($design->cols ?? 1));
        $perPage = $rows * $cols;
        $pageKey = strtolower((string) ($design->page ?? 'a3'));
        $orientation = ($design->orientation ?? 'h') === 'h' ? 'L' : 'P';

        $staticHtml = $this->makeStaticCoverHtml($coverHtmlPrepared);
        $slots = $this->resolveCoverSlots($slotsHtml ?? $coverHtmlPrepared);
        $format = $this->resolveFormatBoxMm($coverHtmlPrepared);
        $cellW = $format['w'];
        $cellH = $format['h'];

        $templatePath = storage_path('app/temp_pdf_stamp_cover_'.uniqid('', true).'.pdf');
        $this->renderCellTemplate($staticHtml, $cellW, $cellH, $templatePath);

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

        [$pageW, $pageH] = $this->sheetSizeMm($pageKey, $orientation);
        $marginX = 10.0 + (float) config('pdf_optimization.stamp_offset_x', 0);
        $marginY = 8.0 + (float) config('pdf_optimization.stamp_offset_y', 0);
        $contentDx = (float) config('pdf_optimization.stamp_content_offset_x', 0);
        $contentDy = (float) config('pdf_optimization.stamp_content_offset_y', 0);
        $drawBorder = (bool) config('pdf_optimization.stamp_cell_border', true);

        $pages = array_values(array_chunk($books, $perPage));
        foreach ($pages as $pageBooks) {
            $pdf->AddPage($orientation, [$pageW, $pageH]);
            for ($i = 0; $i < $perPage; $i++) {
                if (! isset($pageBooks[$i]) || ! is_array($pageBooks[$i])) {
                    continue;
                }
                $col = $i % $cols;
                $row = intdiv($i, $cols);
                $originX = $marginX + ($col * $cellW);
                $originY = $marginY + ($row * $cellH);

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
                    $format['w']
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
        $rows = max(1, (int) ($design->rows ?? 1));
        $cols = max(1, (int) ($design->cols ?? 1));
        $perPage = $rows * $cols;
        $pageKey = strtolower((string) ($design->page ?? 'a3'));
        $orientation = ($design->orientation ?? 'h') === 'h' ? 'L' : 'P';

        $staticHtml = $this->makeStaticBackHtml($backHtmlPrepared);
        $format = $this->resolveFormatBoxMm($backHtmlPrepared);
        $cellW = $format['w'];
        $cellH = $format['h'];

        $templatePath = storage_path('app/temp_pdf_stamp_back_'.uniqid('', true).'.pdf');
        $this->renderCellTemplate($staticHtml, $cellW, $cellH, $templatePath);

        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($templatePath);
        if ($pageCount < 1) {
            @unlink($templatePath);
            throw new \RuntimeException('No se pudo importar la plantilla de trasera PDF.');
        }
        $tplId = $pdf->importPage(1);

        [$pageW, $pageH] = $this->sheetSizeMm($pageKey, $orientation);
        $marginX = 10.0 + (float) config('pdf_optimization.stamp_offset_x', 0);
        $marginY = 8.0 + (float) config('pdf_optimization.stamp_offset_y', 0);
        $drawBorder = (bool) config('pdf_optimization.stamp_cell_border', true);

        $totalPages = (int) ceil($copies / $perPage);
        // Misma ordenación talonario/guillotina que participaciones.
        for ($p = 0; $p < $totalPages; $p++) {
            $pdf->AddPage($orientation, [$pageW, $pageH]);
            for ($i = 0; $i < $perPage; $i++) {
                $index = $p + ($i * $totalPages);
                if ($index >= $copies) {
                    continue;
                }
                $col = $i % $cols;
                $row = intdiv($i, $cols);
                $originX = $marginX + ($col * $cellW);
                $originY = $marginY + ($row * $cellH);
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

    private function makeStaticCoverHtml(string $html): string
    {
        // QR solo por FPDI (evita caja blanca vacía).
        $html = preg_replace(
            '/<div[^>]*\bclass="[^"]*\bqr\b[^"]*"[^>]*>.*?<\/div>/is',
            '',
            $html,
            1
        ) ?? $html;

        // Barra .context: conservar caja; vaciar contenido (etiqueta se estampa).
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
        $w = 200.0;
        $h = 92.0;
        if (preg_match('/format-box[^>]*style=(["\'])(.*?)\1/is', $html, $m)
            || preg_match('/style=(["\'])(.*?)\1[^>]*format-box/is', $html, $m)) {
            $style = $m[2];
            if (preg_match('/\bwidth\s*:\s*([\d.]+)\s*mm/i', $style, $wm)) {
                $w = (float) $wm[1];
            }
            if (preg_match('/\bheight\s*:\s*([\d.]+)\s*mm/i', $style, $hm)) {
                $h = (float) $hm[1];
            }
        }

        return ['w' => max(10.0, $w), 'h' => max(10.0, $h)];
    }

    /**
     * @return array{0:float,1:float}
     */
    private function sheetSizeMm(string $pageKey, string $orientation): array
    {
        $sizes = [
            'a3' => [297.0, 420.0],
            'a4' => [210.0, 297.0],
            'a5' => [148.0, 210.0],
            'letter' => [215.9, 279.4],
        ];
        [$short, $long] = $sizes[$pageKey] ?? $sizes['a3'];

        return $orientation === 'L' ? [$long, $short] : [$short, $long];
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
        float $designW
    ): void {
        $scale = $designW > 0 ? ($cellW / $designW) : 1.0;
        $ref = (string) ($book['taco_ref'] ?? '');
        $label = (string) ($book['label'] ?? '');

        if ($slots['qr'] !== null && $ref !== '' && isset($qrFiles[$ref])) {
            $src = $qrFiles[$ref];
            if (is_string($src) && $src !== '' && is_file($src)) {
                $q = $slots['qr'];
                $boxW = $q['w'] * $scale;
                $boxH = $q['h'] * $scale;
                $side = min($boxW, $boxH);
                $qx = $originX + ($q['x'] * $scale) + (($boxW - $side) / 2.0);
                $qy = $originY + ($q['y'] * $scale) + (($boxH - $side) / 2.0);
                $this->debugStampBox($pdf, $originX + ($q['x'] * $scale), $originY + ($q['y'] * $scale), $boxW, $boxH);
                $pdf->Image($src, $qx, $qy, $side, $side);
            }
        }

        if ($slots['context'] !== null && $label !== '') {
            $c = $slots['context'];
            $bx = $originX + ($c['x'] * $scale);
            $by = $originY + ($c['y'] * $scale);
            $bw = $c['w'] * $scale;
            $bh = $c['h'] * $scale;
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

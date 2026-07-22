<?php

namespace App\Services;

use App\Http\Controllers\DesignController;
use App\Models\DesignFormat;
use App\Support\ParticipationPdfLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

/**
 * Exportación rápida de participaciones:
 * 1) DomPDF genera UNA sola celda (diseño estático).
 * 2) FPDI la coloca en una cuadrícula fija (misma posición/proporción en todas).
 * 3) Estampa ref / nº / QR con el mismo origen por celda.
 */
class ParticipationPdfStampExporter
{
    public function __construct(
        private DesignController $controller
    ) {}

    /**
     * @param  list<array{r?: string, n?: int|string}>  $tickets
     * @param  array<string, string>  $qrCodes  ref => path|data-uri
     * @param  string|null  $slotsHtml  HTML ligero (coords/fuente sin hacks DomPDF). Si null, usa $participationHtmlPrepared.
     */
    public function exportToFile(
        DesignFormat $design,
        string $participationHtmlPrepared,
        array $tickets,
        array $qrCodes,
        string $finalPath,
        ?string $slotsHtml = null
    ): void {
        $t0 = microtime(true);

        $layout = ParticipationPdfLayout::fromDesign($design, $design->participation_html ?? '');
        $rows = $layout->rows;
        $cols = $layout->cols;
        $perPage = $rows * $cols;
        $pageKey = strtolower((string) ($design->page ?? 'a3'));
        $orientation = ($design->orientation ?? 'h') === 'h' ? 'L' : 'P';

        $staticHtml = $this->makeStaticParticipationHtml($participationHtmlPrepared);
        $slots = $this->resolveSlotsFromHtml($slotsHtml ?? $participationHtmlPrepared);
        $cellW = $layout->trimWidthMm;
        $cellH = $layout->trimHeightMm;
        $designW = $layout->designWidthMm;
        $designH = $layout->designHeightMm;

        $templatePath = storage_path('app/temp_pdf_stamp_cell_'.uniqid('', true).'.pdf');
        $this->renderCellTemplate($staticHtml, $designW, $designH, $templatePath);

        $tTemplate = microtime(true);

        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($templatePath);
        if ($pageCount < 1) {
            @unlink($templatePath);
            throw new \RuntimeException('No se pudo importar la plantilla de celda PDF.');
        }
        $tplId = $pdf->importPage(1);

        [$pageW, $pageH] = [$layout->sheetWidthMm, $layout->sheetHeightMm];

        $contentDx = (float) config('pdf_optimization.stamp_content_offset_x', 0);
        $contentDy = (float) config('pdf_optimization.stamp_content_offset_y', 0.8);
        $drawBorder = (bool) config('pdf_optimization.stamp_cell_border', false);

        $pages = $this->paginateTickets($tickets, $perPage);
        foreach ($pages as $pageTickets) {
            $pdf->AddPage($orientation, [$pageW, $pageH]);

            for ($i = 0; $i < $perPage; $i++) {
                $col = $i % $cols;
                $row = intdiv($i, $cols);
                $originX = $layout->trimOriginX($col);
                $originY = $layout->trimOriginY($row);

                if (! isset($pageTickets[$i]) || ! is_array($pageTickets[$i])) {
                    continue;
                }

                $pdf->useTemplate($tplId, $originX, $originY, $cellW, $cellH);

                if ($drawBorder) {
                    $pdf->SetDrawColor(120, 120, 120);
                    $pdf->SetLineWidth(0.25);
                    $pdf->Rect($originX, $originY, $cellW, $cellH);
                }

                $this->stampTicket(
                    $pdf,
                    $pageTickets[$i],
                    $qrCodes,
                    $slots,
                    $originX + $contentDx,
                    $originY + $contentDy,
                    $cellW,
                    $designW
                );
            }

            // Grilla independiente encima (no altera posición/tamaño de participaciones).
            if ($layout->drawCropMarks) {
                $this->drawCutGrid($pdf, $layout);
            }
        }

        $dir = dirname($finalPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdf->Output('F', $finalPath);
        @unlink($templatePath);

        Log::info('ParticipationPdfStampExporter done', [
            'tickets' => count($tickets),
            'pages' => count($pages),
            'mode' => 'tiled-cell',
            'cell_mm' => [$cellW, $cellH],
            'template_ms' => (int) round(($tTemplate - $t0) * 1000),
            'stamp_ms' => (int) round((microtime(true) - $tTemplate) * 1000),
            'total_ms' => (int) round((microtime(true) - $t0) * 1000),
            'bytes' => is_file($finalPath) ? filesize($finalPath) : 0,
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

    /**
     * @return array{0: float, 1: float} widthMm, heightMm
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
        // DomPDF custom paper en puntos (1 pt = 1/72 in)
        $wPt = $cellWmm * 72.0 / 25.4;
        $hPt = $cellHmm * 72.0 / 25.4;

        $pdf = Pdf::loadView('design.pdf_participation_cell', [
            'participation_html' => $staticHtml,
        ])->setPaper([0, 0, $wPt, $hPt]);

        $this->controller->applyDompdfOptions($pdf);
        $pdf->save($templatePath);
    }

    /**
     * @param  list<array{r?: string, n?: int|string}>  $tickets
     * @return list<list<array{r?: string, n?: int|string}>>
     */
    private function paginateTickets(array $tickets, int $perPage): array
    {
        $count = count($tickets);
        if ($count === 0 || $perPage < 1) {
            return [];
        }

        $totalPages = (int) ceil($count / $perPage);
        $pages = [];
        for ($p = 0; $p < $totalPages; $p++) {
            $pages[$p] = [];
            for ($i = 0; $i < $perPage; $i++) {
                $ticketIndex = $p + ($i * $totalPages);
                if ($ticketIndex < $count) {
                    $pages[$p][$i] = $tickets[$ticketIndex];
                }
            }
        }

        return $pages;
    }

    public function makeStaticParticipationHtml(string $html): string
    {
        $html = str_replace('00000000000000000000', '', $html);
        $html = str_replace('1/0001', '', $html);

        // Quitar por completo el hueco QR: DomPDF pintaba un rectángulo blanco vacío
        // y el stamp FPDI quedaba "raro" encima. El QR solo se estampa con FPDI.
        $html = preg_replace(
            '/<div[^>]*\bclass="[^"]*\bqr\b[^"]*"[^>]*>.*?<\/div>/is',
            '',
            $html,
            1
        ) ?? $html;

        // DomPDF pinta estas imgs debajo del fondo opaco; se estampan con FPDI encima.
        $html = preg_replace(
            '/<div[^>]*\bclass="[^"]*\bimages\b[^"]*"[^>]*>.*?<\/div>/is',
            '',
            $html
        ) ?? $html;

        return $html;
    }

    /**
     * @return array{w: float, h: float}
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
     * @return array{
     *   qr: ?array{x:float,y:float,w:float,h:float},
     *   references: list<array{x:float,y:float,w:float,h:float,size:float,color:array{0:int,1:int,2:int}}>,
     *   participations: list<array{x:float,y:float,w:float,h:float,size:float,color:array{0:int,1:int,2:int}}>,
     *   images: list<array{x:float,y:float,w:float,h:float,src:string}>
     * }
     */
    public function resolveSlotsFromHtml(string $html): array
    {
        $slots = [
            'qr' => null,
            'references' => [],
            'participations' => [],
            'images' => [],
        ];

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
            $inner = $match[2];
            if (! preg_match('/\bclass="([^"]+)"/i', $attrs, $cm)) {
                continue;
            }
            $class = strtolower($cm[1]);
            if (! preg_match('/\bstyle="([^"]*)"/i', $attrs, $sm)) {
                continue;
            }
            $style = $sm[1];
            $box = $this->parseBoxMm($style);
            if ($box === null) {
                continue;
            }

            $fontPt = $this->parseFontSizePt($style);
            if (preg_match('/font-size\s*:\s*([\d.]+)\s*px/i', $inner, $fm)) {
                $fontPt = max(5.0, (float) $fm[1] * 0.75);
            } elseif (preg_match('/font-size\s*:\s*([\d.]+)\s*pt/i', $inner, $fm)) {
                $fontPt = max(5.0, (float) $fm[1]);
            }

            $color = $this->parseCssColor($style) ?? $this->parseCssColor($inner) ?? [0, 0, 0];
            $bold = (bool) preg_match('/<(strong|b)\b/i', $inner);

            if (preg_match('/(^|\s)qr(\s|$)/', $class)) {
                $slots['qr'] = $box;
            } elseif (str_contains($class, 'reference')) {
                $slots['references'][] = $box + ['size' => $fontPt, 'color' => $color, 'bold' => $bold];
            } elseif (str_contains($class, 'participation')) {
                $slots['participations'][] = $box + ['size' => $fontPt, 'color' => $color, 'bold' => $bold];
            } elseif (str_contains($class, 'images')) {
                $src = null;
                if (preg_match('/<img[^>]+src=(["\'])([^"\']+)\1/i', $inner, $im)) {
                    $src = html_entity_decode($im[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                if (is_string($src) && $src !== '' && is_file($src)) {
                    $slots['images'][] = $box + ['src' => $src];
                }
            }
        }

        return $slots;
    }

    /**
     * @return array{0:int,1:int,2:int}|null RGB 0-255
     */
    private function parseCssColor(string $cssOrHtml): ?array
    {
        // Solo la propiedad "color" (no background-color / border-color).
        // No usar \b tras rgb(...): ")" no es word-char y el match fallaba → negro por defecto.
        if (! preg_match_all(
            '/(?<![-\w])color\s*:\s*(#[0-9a-f]{3,8}|rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+(?:\s*,\s*[\d.]+)?\s*\)|[a-z]+)/i',
            $cssOrHtml,
            $matches
        )) {
            return null;
        }

        $parsed = null;
        foreach ($matches[1] as $raw) {
            $raw = trim($raw);
            if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $raw, $m)) {
                $hex = strtolower($m[1]);
                if (strlen($hex) === 3) {
                    $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
                }
                $parsed = [
                    hexdec(substr($hex, 0, 2)),
                    hexdec(substr($hex, 2, 2)),
                    hexdec(substr($hex, 4, 2)),
                ];
                continue;
            }
            if (preg_match('/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/i', $raw, $m)) {
                $parsed = [
                    max(0, min(255, (int) round((float) $m[1]))),
                    max(0, min(255, (int) round((float) $m[2]))),
                    max(0, min(255, (int) round((float) $m[3]))),
                ];
                continue;
            }
            $named = [
                'white' => [255, 255, 255],
                'black' => [0, 0, 0],
                'red' => [255, 0, 0],
                'green' => [0, 128, 0],
                'blue' => [0, 0, 255],
                'gray' => [128, 128, 128],
                'grey' => [128, 128, 128],
                'silver' => [192, 192, 192],
                'yellow' => [255, 255, 0],
            ];
            $key = strtolower($raw);
            if (isset($named[$key])) {
                $parsed = $named[$key];
            }
        }

        return $parsed;
    }

    /**
     * @return array{x:float,y:float,w:float,h:float,pad_l:float,pad_t:float,pad_b:float}|null
     */
    private function parseBoxMm(string $style): ?array
    {
        $left = $this->cssPx($style, 'left');
        $top = $this->cssPx($style, 'top');
        $width = $this->cssPx($style, 'width');
        $height = $this->cssPx($style, 'height');
        if ($left === null || $top === null || $width === null || $height === null) {
            return null;
        }

        [$padT, $padR, $padB, $padL] = $this->parsePaddingPx($style);

        return [
            'x' => $this->pxToMm($left),
            'y' => $this->pxToMm($top),
            'w' => $this->pxToMm(max(1.0, $width)),
            'h' => $this->pxToMm(max(1.0, $height)),
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

        $one = $this->cssPx($style, 'padding') ?? 0.0;

        return [$one, $one, $one, $one];
    }

    private function parseFontSizePt(string $style): float
    {
        if (preg_match('/\bfont-size\s*:\s*([\d.]+)\s*px/i', $style, $m)) {
            return max(5.0, (float) $m[1] * 0.75);
        }
        if (preg_match('/\bfont-size\s*:\s*([\d.]+)\s*pt/i', $style, $m)) {
            return max(5.0, (float) $m[1]);
        }

        return 9.0;
    }

    private function cssPx(string $style, string $prop): ?float
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
     * @param  array{r?: string, n?: int|string}  $ticket
     * @param  array<string, string>  $qrCodes
     * @param  array{qr: ?array, references: list, participations: list}  $slots
     */
    private function stampTicket(
        Fpdi $pdf,
        array $ticket,
        array $qrCodes,
        array $slots,
        float $originX,
        float $originY,
        float $cellW,
        float $designW
    ): void {
        $scale = $designW > 0 ? ($cellW / $designW) : 1.0;

        $ref = (string) ($ticket['r'] ?? '');
        $num = '1/'.str_pad((string) ((int) ($ticket['n'] ?? 0)), 4, '0', STR_PAD_LEFT);

        foreach ($slots['images'] ?? [] as $img) {
            $src = $img['src'] ?? '';
            if (! is_string($src) || $src === '' || ! is_file($src)) {
                continue;
            }
            $ix = $originX + ($img['x'] * $scale);
            $iy = $originY + ($img['y'] * $scale);
            $iw = $img['w'] * $scale;
            $ih = $img['h'] * $scale;
            $pdf->Image($src, $ix, $iy, $iw, $ih);
            $this->debugStampBox($pdf, $ix, $iy, $iw, $ih);
        }

        foreach ($slots['references'] as $box) {
            $bx = $originX + ($box['x'] * $scale);
            $by = $originY + ($box['y'] * $scale);
            $bw = $box['w'] * $scale;
            $bh = $box['h'] * $scale;
            $this->debugStampBox($pdf, $bx, $by, $bw, $bh);
            $this->stampText(
                $pdf,
                $ref,
                $bx,
                $by,
                $bw,
                $bh,
                ($box['size'] ?? 7.0) * $scale,
                ($box['pad_l'] ?? 0.0) * $scale,
                ($box['pad_t'] ?? 0.0) * $scale,
                ($box['pad_b'] ?? $box['pad_t'] ?? 0.0) * $scale,
                $box['color'] ?? [0, 0, 0],
                (bool) ($box['bold'] ?? false)
            );
        }

        foreach ($slots['participations'] as $box) {
            $bx = $originX + ($box['x'] * $scale);
            $by = $originY + ($box['y'] * $scale);
            $bw = $box['w'] * $scale;
            $bh = $box['h'] * $scale;
            $this->debugStampBox($pdf, $bx, $by, $bw, $bh);
            $this->stampText(
                $pdf,
                $num,
                $bx,
                $by,
                $bw,
                $bh,
                ($box['size'] ?? 7.0) * $scale,
                ($box['pad_l'] ?? 0.0) * $scale,
                ($box['pad_t'] ?? 0.0) * $scale,
                ($box['pad_b'] ?? $box['pad_t'] ?? 0.0) * $scale,
                $box['color'] ?? [0, 0, 0],
                (bool) ($box['bold'] ?? false)
            );
        }

        if ($slots['qr'] !== null && $ref !== '' && isset($qrCodes[$ref])) {
            $src = $qrCodes[$ref];
            if (is_string($src) && $src !== '' && ! str_starts_with($src, 'data:') && is_file($src)) {
                $q = $slots['qr'];
                $boxW = $q['w'] * $scale;
                $boxH = $q['h'] * $scale;
                $minMm = max(10.0, (float) config('qr_optimization.qr_code.min_print_size_mm', 20));
                $side = max(min($boxW, $boxH), $minMm);
                $cx = $originX + ($q['x'] * $scale) + ($boxW / 2.0);
                $cy = $originY + ($q['y'] * $scale) + ($boxH / 2.0);
                $qx = $cx - ($side / 2.0);
                $qy = $cy - ($side / 2.0);
                $this->debugStampBox(
                    $pdf,
                    $qx,
                    $qy,
                    $side,
                    $side
                );
                $pdf->Image($src, $qx, $qy, $side, $side);
            }
        }
    }

    /** Rectángulo rosa = caja estampada (coords FPDI, con offset de contenido). */
    private function debugStampBox(Fpdi $pdf, float $x, float $y, float $w, float $h): void
    {
        if (! config('pdf_optimization.debug_element_borders', false)) {
            return;
        }
        $pdf->SetDrawColor(255, 20, 147);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($x, $y, $w, $h);
    }

    /**
     * Estampa texto respetando el padding del editor y centrado en el área de contenido.
     * FPDF::Cell centra con una fórmula rara (0.5*h+0.3*fs); Text() usa baseline explícito.
     *
     * @param  array{0:int,1:int,2:int}  $color
     */
    private function stampText(
        Fpdi $pdf,
        string $text,
        float $x,
        float $y,
        float $w,
        float $h,
        float $fontPt,
        float $padL,
        float $padT,
        float $padB = 0.0,
        array $color = [0, 0, 0],
        bool $bold = false
    ): void {
        if ($text === '') {
            return;
        }

        $fontPt = max(4.0, min(22.0, $fontPt));
        $fontMm = $fontPt * (25.4 / 72.0);
        // Altura visual aprox. de mayúsculas Helvetica (coincide mejor con el editor que line-height).
        $glyphHmm = $fontMm * 0.75;
        $contentTop = $y + $padT;
        $contentH = max($glyphHmm, $h - $padT - $padB);
        $textTop = $contentTop + max(0.0, ($contentH - $glyphHmm) / 2.0);
        // Text($x,$y): $y es la baseline, no el top del glifo.
        $baseline = $textTop + ($fontMm * 0.72);

        $pdf->SetTextColor(
            max(0, min(255, (int) ($color[0] ?? 0))),
            max(0, min(255, (int) ($color[1] ?? 0))),
            max(0, min(255, (int) ($color[2] ?? 0)))
        );
        $pdf->SetFont('Helvetica', $bold ? 'B' : '', $fontPt);
        $pdf->Text($x + $padL, $baseline, $this->fpdiSafeText($text));
    }

    private function fpdiSafeText(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);

        return $converted !== false ? $converted : $text;
    }
}

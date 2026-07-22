<?php



namespace App\Support;



use App\Models\DesignFormat;



/**

 * Cálculo de rejilla de impresión: sangrado por participación, márgenes de hoja y marcas de corte.

 *

 * Convención de márgenes (editor):

 * - margins.top  → sangrado superior

 * - margins.up   → sangrado inferior (campo margin_up en UI)

 * - margins.left / margins.right → sangrados laterales

 * - margin_custom → sangrado uniforme si falta un lado

 *

 * La hoja se llena así: sangrado exterior = margen @page; el área útil reparte

 * filas×columnas de corte (trim) con callejones = sangrado derecho + hueco + sangrado izquierdo.

 */

class ParticipationPdfLayout

{

    public function __construct(

        public float $sheetWidthMm,

        public float $sheetHeightMm,

        public int $rows,

        public int $cols,

        public float $designWidthMm,

        public float $designHeightMm,

        public float $trimWidthMm,

        public float $trimHeightMm,

        public float $bleedTop,

        public float $bleedBottom,

        public float $bleedLeft,

        public float $bleedRight,

        public float $horizontalGapMm,

        public float $verticalGapMm,

        public bool $drawCropMarks,

        public int $guideColorR,

        public int $guideColorG,

        public int $guideColorB,

        public float $guideLineWidthMm,

        public float $imageBleedMm = 0.0,

    ) {}



    public static function fromDesign(DesignFormat $design, string $participationHtml): self

    {

        $margins = is_array($design->margins) ? $design->margins : [];

        $custom = max(0, (float) ($design->margin_custom ?? 0));

        // Márgenes de página (p.ej. 1 mm): posición del bloque de participaciones.
        $bleedTop = self::marginSide($margins, 'top', $custom);
        $bleedBottom = self::marginSide($margins, 'up', $custom);
        $bleedLeft = self::marginSide($margins, 'left', $custom);
        $bleedRight = self::marginSide($margins, 'right', $custom);

        // «Sangres de la imagen»: solo posiciona la grilla (no mueve participaciones).
        $imageBleedMm = max(0.0, (float) ($design->identation ?? 2.5));



        $output = is_array($design->output) ? $design->output : [];

        $drawCropMarks = ($output['draw_guides'] ?? true) !== false;

        [$guideR, $guideG, $guideB] = self::parseGuideColor((string) ($output['guide_color'] ?? '#9333ea'));

        $guideWeight = max(0.1, min(0.35, (float) ($output['guide_weight'] ?? 0.15)));
        // Si el formulario tiene un valor absurdo (p.ej. 5 mm), usar un trazo fino de imprenta.
        if ((float) ($output['guide_weight'] ?? 0) > 1.0) {
            $guideWeight = 0.2;
        }



        $orientation = ($design->orientation ?? 'h') === 'h' ? 'L' : 'P';

        [$sheetW, $sheetH] = self::sheetSizeMm(strtolower((string) ($design->page ?? 'a3')), $orientation);

        [$designW, $designH] = self::parseFormatBoxMm($participationHtml);



        $layout = new self(

            sheetWidthMm: $sheetW,

            sheetHeightMm: $sheetH,

            rows: max(1, (int) ($design->rows ?? 1)),

            cols: max(1, (int) ($design->cols ?? 1)),

            designWidthMm: $designW,

            designHeightMm: $designH,

            trimWidthMm: $designW,

            trimHeightMm: $designH,

            bleedTop: $bleedTop,

            bleedBottom: $bleedBottom,

            bleedLeft: $bleedLeft,

            bleedRight: $bleedRight,

            horizontalGapMm: max(0, (float) ($design->horizontal_space ?? 0)),

            verticalGapMm: max(0, (float) ($design->vertical_space ?? 0)),

            drawCropMarks: $drawCropMarks,

            guideColorR: $guideR,

            guideColorG: $guideG,

            guideColorB: $guideB,

            guideLineWidthMm: $guideWeight,

            imageBleedMm: $imageBleedMm,

        );



        [$trimW, $trimH] = $layout->computeFittedTrimDimensions();

        $layout->trimWidthMm = $trimW;

        $layout->trimHeightMm = $trimH;



        return $layout;

    }



    /**

     * @param  array<string, mixed>  $margins

     */

    private static function marginSide(array $margins, string $key, float $fallback): float

    {

        if (array_key_exists($key, $margins) && $margins[$key] !== '' && $margins[$key] !== null) {

            return max(0, (float) $margins[$key]);

        }



        return $fallback;

    }



    /**

     * @return array{0: float, 1: float}

     */

    public static function sheetSizeMm(string $pageKey, string $orientation): array

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



    /**

     * @return array{0: float, 1: float}

     */

    public static function parseFormatBoxMm(string $html): array

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



        return [max(10.0, $w), max(10.0, $h)];

    }



    /**

     * @return array{0: int, 1: int, 2: int}

     */

    private static function parseGuideColor(string $color): array

    {

        $color = trim($color);

        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color, $m)) {

            $hex = strtolower($m[1]);

            if (strlen($hex) === 3) {

                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];

            }



            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

        }



        return [147, 51, 234];

    }



    public function contentAreaWidthMm(): float

    {

        return max(1.0, $this->sheetWidthMm - $this->bleedLeft - $this->bleedRight);

    }



    public function contentAreaHeightMm(): float

    {

        return max(1.0, $this->sheetHeightMm - $this->bleedTop - $this->bleedBottom);

    }



    /**
     * Espacio horizontal entre participaciones (solo hueco del formulario).
     * La sangre NO mueve las piezas: las líneas de corte son independientes.
     */
    public function horizontalGutterMm(): float
    {
        return max(0.0, $this->horizontalGapMm);
    }

    /**
     * Espacio vertical entre participaciones (solo hueco del formulario).
     */
    public function verticalGutterMm(): float
    {
        return max(0.0, $this->verticalGapMm);
    }



    /**

     * Dimensiones de corte que llenan el área útil de la hoja (filas × columnas + callejones).

     *

     * @return array{0: float, 1: float}

     */

    public function computeFittedTrimDimensions(): array

    {

        $gutterW = $this->horizontalGutterMm();

        $gutterH = $this->verticalGutterMm();



        $trimW = ($this->contentAreaWidthMm() - max(0, $this->cols - 1) * $gutterW) / $this->cols;

        $trimH = ($this->contentAreaHeightMm() - max(0, $this->rows - 1) * $gutterH) / $this->rows;



        return [max(10.0, $trimW), max(10.0, $trimH)];

    }



    public function trimScaleX(): float

    {

        return $this->designWidthMm > 0 ? ($this->trimWidthMm / $this->designWidthMm) : 1.0;

    }



    public function trimScaleY(): float

    {

        return $this->designHeightMm > 0 ? ($this->trimHeightMm / $this->designHeightMm) : 1.0;

    }



    public function pageMarginTop(): float

    {

        return $this->bleedTop;

    }



    public function pageMarginRight(): float

    {

        return $this->bleedRight;

    }



    public function pageMarginBottom(): float

    {

        return $this->bleedBottom;

    }



    public function pageMarginLeft(): float

    {

        return $this->bleedLeft;

    }



    /** Origen X del área de corte dentro del área útil (tras margen @page). */

    public function trimOriginXContent(int $col): float

    {

        return $col * ($this->trimWidthMm + $this->horizontalGutterMm());

    }



    /** Origen Y del área de corte dentro del área útil (tras margen @page). */

    public function trimOriginYContent(int $row): float

    {

        return $row * ($this->trimHeightMm + $this->verticalGutterMm());

    }



    /** Origen X absoluto en la hoja física (mm). */

    public function trimOriginX(int $col): float

    {

        return $this->bleedLeft + $this->trimOriginXContent($col);

    }



    /** Origen Y absoluto en la hoja física (mm). */

    public function trimOriginY(int $row): float

    {

        return $this->bleedTop + $this->trimOriginYContent($row);

    }



    public function cropMarkLengthMm(): float
    {
        return max($this->imageBleedMm, 1.0);
    }

    /**
     * Grilla de corte independiente del layout de participaciones.
     * Las piezas quedan a ras; las líneas marcan el trim = borde de arte ± sangre.
     * Entre piezas: dos líneas (seam − sangre y seam + sangre), como el PDF de imprenta.
     *
     * @return list<array{x1: float, y1: float, x2: float, y2: float}>
     */
    public function cutGridSegments(): array
    {
        if (! $this->drawCropMarks) {
            return [];
        }

        $sangre = max(0.0, $this->imageBleedMm);
        $x0 = $this->trimOriginX(0);
        $y0 = $this->trimOriginY(0);
        $x1 = $this->trimOriginX($this->cols - 1) + $this->trimWidthMm;
        $y1 = $this->trimOriginY($this->rows - 1) + $this->trimHeightMm;

        // Las líneas atraviesan la hoja; en margen de página se ven los stubs.
        $yTop = 0.0;
        $yBot = $this->sheetHeightMm;
        $xLeft = 0.0;
        $xRight = $this->sheetWidthMm;

        $segments = [];
        $seen = [];

        $add = static function (array &$segments, array &$seen, float $xa, float $ya, float $xb, float $yb): void {
            $key = sprintf('%.3f:%.3f:%.3f:%.3f', $xa, $ya, $xb, $yb);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            if (abs($xb - $xa) < 0.05 && abs($yb - $ya) < 0.05) {
                return;
            }
            $segments[] = ['x1' => $xa, 'y1' => $ya, 'x2' => $xb, 'y2' => $yb];
        };

        // Verticales exteriores: inset sangre hacia dentro del bloque de arte.
        $add($segments, $seen, $x0 + $sangre, $yTop, $x0 + $sangre, $yBot);
        $add($segments, $seen, $x1 - $sangre, $yTop, $x1 - $sangre, $yBot);

        // Verticales internas: dos líneas por junta (independientes del arte a ras).
        for ($c = 0; $c < $this->cols - 1; $c++) {
            $seam = $this->trimOriginX($c) + $this->trimWidthMm;
            $add($segments, $seen, $seam - $sangre, $yTop, $seam - $sangre, $yBot);
            $add($segments, $seen, $seam + $sangre, $yTop, $seam + $sangre, $yBot);
        }

        // Horizontales exteriores.
        $add($segments, $seen, $xLeft, $y0 + $sangre, $xRight, $y0 + $sangre);
        $add($segments, $seen, $xLeft, $y1 - $sangre, $xRight, $y1 - $sangre);

        // Horizontales internas: dos líneas por junta.
        for ($r = 0; $r < $this->rows - 1; $r++) {
            $seam = $this->trimOriginY($r) + $this->trimHeightMm;
            $add($segments, $seen, $xLeft, $seam - $sangre, $xRight, $seam - $sangre);
            $add($segments, $seen, $xLeft, $seam + $sangre, $xRight, $seam + $sangre);
        }

        return $segments;
    }

    /**
     * Compatibilidad: la grilla se genera una vez por hoja vía cutGridSegments().
     *
     * @return list<array{x1: float, y1: float, x2: float, y2: float}>
     */
    public function cropMarkSegmentsForCell(int $col, int $row): array
    {
        if ($col === 0 && $row === 0) {
            return $this->cutGridSegments();
        }

        return [];
    }

    /**
     * @return list<array{x1: float, y1: float, x2: float, y2: float}>
     */
    public function cropMarkSegmentsForCellOnSheet(int $col, int $row): array
    {
        return $this->cropMarkSegmentsForCell($col, $row);
    }

    /**
     * @return list<array{x1: float, y1: float, x2: float, y2: float}>
     */
    public function cropMarkSegmentsForCellInContent(int $col, int $row): array
    {
        return $this->cropMarkSegmentsForCellOnSheet($col, $row);
    }
}



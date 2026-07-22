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

    ) {}



    public static function fromDesign(DesignFormat $design, string $participationHtml): self

    {

        $margins = is_array($design->margins) ? $design->margins : [];

        $custom = max(0, (float) ($design->margin_custom ?? 0));



        $bleedTop = self::marginSide($margins, 'top', $custom);

        $bleedBottom = self::marginSide($margins, 'up', $custom);

        $bleedLeft = self::marginSide($margins, 'left', $custom);

        $bleedRight = self::marginSide($margins, 'right', $custom);



        $output = is_array($design->output) ? $design->output : [];

        $drawCropMarks = ($output['draw_guides'] ?? true) !== false;

        [$guideR, $guideG, $guideB] = self::parseGuideColor((string) ($output['guide_color'] ?? '#9333ea'));

        $guideWeight = max(0.05, (float) ($output['guide_weight'] ?? 0.1));



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
     * Espacio horizontal entre participaciones.
     * Solo el hueco configurado en el diseño (no el sangrado): sin calles por defecto.
     */
    public function horizontalGutterMm(): float
    {
        return max(0.0, $this->horizontalGapMm);
    }

    /**
     * Espacio vertical entre participaciones.
     * Solo el hueco configurado en el diseño (no el sangrado): sin calles por defecto.
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
        $maxBleed = max($this->bleedTop, $this->bleedRight, $this->bleedBottom, $this->bleedLeft, 0.5);

        // Con 1 mm de sangrado: ticks ~2 mm hacia fuera del margen.
        return max(1.5, min(3.0, $maxBleed * 2.0));
    }

    /**
     * Marcas de corte justo en el margen de cada participación.
     * La participación se dibuja encima: solo se ve lo que sobresale al sangrado/callejón.
     *
     * @return list<array{x1: float, y1: float, x2: float, y2: float}>
     */
    public function cropMarkSegmentsForCell(int $col, int $row): array
    {
        if (! $this->drawCropMarks) {
            return [];
        }

        $x = $this->trimOriginX($col);
        $y = $this->trimOriginY($row);
        $w = $this->trimWidthMm;
        $h = $this->trimHeightMm;
        $len = $this->cropMarkLengthMm();
        $sheetW = $this->sheetWidthMm;
        $sheetH = $this->sheetHeightMm;
        $segments = [];

        // Ticks L en cada esquina: alineados con el margen (tocan el borde), hacia el sangrado.
        // Superior-izquierda
        $segments[] = ['x1' => max(0.0, $x - $len), 'y1' => $y, 'x2' => $x, 'y2' => $y];
        $segments[] = ['x1' => $x, 'y1' => max(0.0, $y - $len), 'x2' => $x, 'y2' => $y];
        // Superior-derecha
        $segments[] = ['x1' => $x + $w, 'y1' => $y, 'x2' => min($sheetW, $x + $w + $len), 'y2' => $y];
        $segments[] = ['x1' => $x + $w, 'y1' => max(0.0, $y - $len), 'x2' => $x + $w, 'y2' => $y];
        // Inferior-izquierda
        $segments[] = ['x1' => max(0.0, $x - $len), 'y1' => $y + $h, 'x2' => $x, 'y2' => $y + $h];
        $segments[] = ['x1' => $x, 'y1' => $y + $h, 'x2' => $x, 'y2' => min($sheetH, $y + $h + $len)];
        // Inferior-derecha
        $segments[] = ['x1' => $x + $w, 'y1' => $y + $h, 'x2' => min($sheetW, $x + $w + $len), 'y2' => $y + $h];
        $segments[] = ['x1' => $x + $w, 'y1' => $y + $h, 'x2' => $x + $w, 'y2' => min($sheetH, $y + $h + $len)];

        // Entre participaciones: línea completa en el margen compartido (callejón).
        if ($col < $this->cols - 1) {
            $segments[] = ['x1' => $x + $w, 'y1' => $y, 'x2' => $x + $w, 'y2' => $y + $h];
        }
        if ($row < $this->rows - 1) {
            $segments[] = ['x1' => $x, 'y1' => $y + $h, 'x2' => $x + $w, 'y2' => $y + $h];
        }

        return array_values(array_filter(
            $segments,
            static fn (array $segment): bool => abs($segment['x2'] - $segment['x1']) >= 0.05
                || abs($segment['y2'] - $segment['y1']) >= 0.05
        ));
    }

    /**
     * Coordenadas absolutas en la hoja (para DomPDF con @page sin margen).
     *
     * @return list<array{x1: float, y1: float, x2: float, y2: float}>
     */
    public function cropMarkSegmentsForCellOnSheet(int $col, int $row): array
    {
        return $this->cropMarkSegmentsForCell($col, $row);
    }

    /**
     * @deprecated Usar cropMarkSegmentsForCellOnSheet con hoja completa.
     * @return list<array{x1: float, y1: float, x2: float, y2: float}>
     */
    public function cropMarkSegmentsForCellInContent(int $col, int $row): array
    {
        return $this->cropMarkSegmentsForCellOnSheet($col, $row);
    }
}



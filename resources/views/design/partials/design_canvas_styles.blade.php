{{-- Tipografía y reset Bootstrap compartidos: editor (.format-box) ↔ DomPDF --}}
{{-- No usar all:unset (DomPDF no lo aplica bien). Reset explícito de lo que Bootstrap toca. --}}

@include('design.partials.design_custom_fonts')

.format-box {
    /* Fallback DomPDF-safe; Asgonlae solo si CKEditor la pone en style inline */
    font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
    font-size: 16px;
    line-height: 1.15;
    color: #000000;
    padding: 0;
}

/*
 * No forzar font-family !important en hijos: anularía Asgonlae (y otras)
 * aplicadas por CKEditor vía style="font-family:…".
 * Los nodos sin fuente explícita heredan DejaVu del .format-box.
 */
/*
 * Neutralizar Bootstrap (h1–h6, .h1–, p):
 * - sin márgenes (Bootstrap ~0.5em / rem)
 * - sin font-size en rem del tema (usan span con font-size inline del diseño)
 * - line-height fijo como DomPDF
 */
.format-box h1,
.format-box h2,
.format-box h3,
.format-box h4,
.format-box h5,
.format-box h6,
.format-box .h1,
.format-box .h2,
.format-box .h3,
.format-box .h4,
.format-box .h5,
.format-box .h6 {
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
    /* En PDF no usar !important: syncHeadingLineMetricsForDomPdf pone el font-size de los spans */
    font-size: inherit{{ !empty($designPdfFonts) ? '' : ' !important' }};
    font-weight: inherit !important;
    line-height: {{ !empty($designPdfFonts) ? '1' : '1.15' }} !important;
    /* No forzar color: inherit !important — anula color:#fff inline en DomPDF. */
}

.format-box p {
    margin: 0 !important;
    padding: 0 !important;
    font-size: inherit{{ !empty($designPdfFonts) ? '' : ' !important' }};
    line-height: {{ !empty($designPdfFonts) ? '1' : '1.15' }} !important;
}

.format-box strong,
.format-box b {
    font-weight: bold !important;
}

.format-box em {
    font-style: italic !important;
}

.format-box u {
    text-decoration: underline !important;
}

.format-box s,
.format-box strike,
.format-box del {
    text-decoration: line-through !important;
}

.format-box span {
    line-height: inherit;
}

.format-box .elements {
    z-index: 1000;
    border: 1px solid transparent;
    /* Editor: border-box. En PDF se fuerza content-box + compensación (ver adjustElementBoxModelForDomPdf). */
    box-sizing: border-box !important;
}

.format-box .elements.text,
.format-box .elements.reference,
.format-box .elements.participation,
.format-box .elements.number {
    overflow: hidden;
    line-height: 1.15;
@if(config('pdf_optimization.debug_element_borders'))
    /* DEBUG: borde rosa editor ↔ PDF */
    border-color: #ff1493 !important;
@endif
}

/*
 * Texto vertical = letras de lado (rotate -90°).
 * Importante: NO transformar el .elements exterior (left/top/width/height
 * deben coincidir con lo visible; DomPDF/FPDI usan esas coords).
 * La rotación va en el span interior.
 */
@if(empty($designPdfFonts))
.format-box .elements.text.text-vertical,
.format-box .elements.text[data-text-vertical="1"] {
    writing-mode: horizontal-tb;
    text-orientation: mixed;
    transform: none !important;
    overflow: hidden !important;
}
.format-box .elements.text.text-vertical > span,
.format-box .elements.text[data-text-vertical="1"] > span {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%) rotate(-90deg);
    transform-origin: center center;
    white-space: nowrap;
    max-width: none;
    line-height: 1.15;
}
@else
.format-box .elements.text.text-vertical,
.format-box .elements.text[data-text-vertical="1"] {
    overflow: visible !important;
    transform: none !important;
}
@endif

.format-box .elements.text *,
.format-box .elements.reference *,
.format-box .elements.participation *,
.format-box .elements.number * {
    line-height: 1.15 !important;
}

@if(!empty($designPdfFonts))
{{-- DomPDF: métricas más altas que el navegador; acercar al editor.
     Base 13.6px = 16 * 0.85 (scaleFontSizesForDomPdf).
     NO forzar font-size:1px en h/p: oculta texto sin font-size en los spans (p.ej. fecha). --}}
.format-box {
    font-size: 13.6px;
    line-height: 1;
}
.format-box h1,
.format-box h2,
.format-box h3,
.format-box h4,
.format-box h5,
.format-box h6,
.format-box .h1,
.format-box .h2,
.format-box .h3,
.format-box .h4,
.format-box .h5,
.format-box .h6,
.format-box p {
    line-height: 1 !important;
}
.format-box .elements.text,
.format-box .elements.reference,
.format-box .elements.participation,
.format-box .elements.number {
    line-height: 1;
}
.format-box .elements.text *,
.format-box .elements.reference *,
.format-box .elements.participation *,
.format-box .elements.number * {
    line-height: 1 !important;
}
@endif

.format-box .elements.qr {
    padding: 0 !important;
    margin: 0 !important;
    overflow: hidden !important;
    background-color: #fff;
    /* Mínimo 0,9 cm ≈ 35px @ 96dpi (mismo valor que enforceQrMinSize; no usar mm aquí). */
    min-width: 35px !important;
    min-height: 35px !important;
}

.format-box .elements.qr img,
.format-box .elements.qr .qr-code {
    width: 100% !important;
    height: 100% !important;
    display: block !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
}

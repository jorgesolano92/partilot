{{-- Tipografía y reset Bootstrap compartidos: editor (.format-box) ↔ DomPDF --}}
{{-- No usar all:unset (DomPDF no lo aplica bien). Reset explícito de lo que Bootstrap toca. --}}

.format-box {
    font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
    font-size: 16px;
    line-height: 1.15;
    color: #000000;
    padding: 0;
}

/* Misma familia en todo el lienzo (salvo iconos Remix) */
.format-box *:not([class*="ri-"]):not(button):not(.edit-btn) {
    font-family: DejaVu Sans, Helvetica, Arial, sans-serif !important;
}

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
    font-size: inherit !important;
    font-weight: inherit !important;
    line-height: 1.15 !important;
    /* No forzar color: inherit !important — anula color:#fff inline en DomPDF. */
}

.format-box p {
    margin: 0 !important;
    padding: 0 !important;
    font-size: inherit !important;
    line-height: 1.15 !important;
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

.format-box .elements.text *,
.format-box .elements.reference *,
.format-box .elements.participation *,
.format-box .elements.number * {
    line-height: 1.15 !important;
}

.format-box .elements.qr {
    padding: 0 !important;
    margin: 0 !important;
    overflow: hidden !important;
    background-color: #fff;
    /* Mínimo 1,5 cm ≈ 57px @ 96dpi (mismo valor que enforceQrMinSize; no usar mm aquí). */
    min-width: 57px !important;
    min-height: 57px !important;
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

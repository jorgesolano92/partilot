{{-- Plantilla de UNA participación (sin márgenes de hoja) para tiled FPDI --}}
@php
    $html = $participation_html ?? '';
    $designPdfFonts = true;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Celda participación</title>
    <style>
        @@page {
            margin: 0;
        }
        body {
            margin: 0;
            padding: 0;
        }
        @include('design.partials.design_canvas_styles')

        [id*="containment-wrapper"] {
            position: relative;
            width: unset !important;
        }

        #design-participation-bg,
        #design-cover-bg,
        #design-back-bg {
            overflow: hidden !important;
        }
        /* La capa de fondo ya lleva el inset (identation);
           la img llena esa capa al 100% → margen superior e inferior iguales. */
        #design-participation-bg > img.design-pdf-bg-img,
        #design-cover-bg > img.design-pdf-bg-img,
        #design-back-bg > img.design-pdf-bg-img {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            height: 100% !important;
            border: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            display: block !important;
            z-index: 0 !important;
        }

        .format-box .elements,
        .elements {
            width: 200px;
            position: absolute !important;
            z-index: 1000;
            border: 1px solid transparent !important;
            box-sizing: content-box !important;
            overflow: hidden !important;
        }

        @if(config('pdf_optimization.debug_element_borders'))
        .format-box .elements,
        .elements {
            border: 1px solid #ff1493 !important;
        }
        .format-box .elements.text,
        .format-box .elements.reference,
        .format-box .elements.participation,
        .format-box .elements.number,
        .format-box .elements.images,
        .format-box .elements.qr {
            border-color: #ff1493 !important;
        }
        @endif

        .elements.images {
            overflow: hidden !important;
        }
        .elements.images > span {
            display: block !important;
            width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            line-height: 0 !important;
        }
        .elements.images img {
            width: 100% !important;
            height: 100% !important;
            display: block !important;
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
        }

        .format-box {
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            width: 100% !important;
            height: 100% !important;
            position: relative !important;
        }

        .margen-izquierdo,
        .margen-arriba,
        .margen-derecho,
        .margen-abajo,
        .caja-matriz,
        button {
            display: none !important;
        }
    </style>
</head>
<body>
{!! $html !!}
</body>
</html>

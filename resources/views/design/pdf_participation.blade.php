@php
    $use_prebuilt_cells = $use_prebuilt_cells ?? false;
    $pdfDocumentTitle = $pdfDocumentTitle ?? 'Participación PDF';
    $cols = max(1, (int) ($cols ?? 1));
    // Fondo ya viene promovido a <img> en prepareParticipationHtmlForPdf (una vez).
    // Aquí solo se clonan variables (ref/nº) + QR por ticket.
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $pdfDocumentTitle }}</title>
    <style>
        @page {
            margin: 8mm 10mm;
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

        #design-participation-bg {
            overflow: hidden !important;
        }
        #design-participation-bg > img.design-pdf-bg-img {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            height: calc(100% - 5mm) !important;
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
            border: 1px solid transparent;
            box-sizing: content-box !important;
            overflow: hidden !important;
        }

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

        .qr-code {
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            image-rendering: pixelated;
        }

        .format-box {
            padding: 0 !important;
            margin: 0 !important;
        }

        .margen-izquierdo,
        .margen-arriba,
        .margen-derecho,
        .margen-abajo,
        .caja-matriz,
        button {
            display: none !important;
        }

        .participation-page {
            width: 100%;
            overflow: hidden;
        }
        .participation-page::after {
            content: "";
            display: table;
            clear: both;
        }

        .participation-box {
            width: {{ 100 / $cols }}%;
            float: left;
            overflow: hidden;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
@if(!empty($pdfHtmlPreviewBanner))
<div style="position:sticky;top:0;z-index:99999;background:#fef3c7;color:#78350f;padding:8px 12px;font-size:13px;text-align:center;border-bottom:1px solid #fcd34d;font-family:system-ui,sans-serif;">
    Vista previa HTML (DESIGN_PDF_HTML_PREVIEW=true). Es el mismo markup que recibe DomPDF; no es el archivo PDF.
</div>
@endif
@foreach($pages as $pageIndex => $page)
    <div class="participation-page" style="@if($pageIndex < count($pages) - 1) page-break-after: always; @endif">
        @for($i = 0; $i < count($page); $i++)
            @if($use_prebuilt_cells)
                @php $html = $page[$i]; @endphp
            @else
                @php
                    $ticket = $page[$i];
                    $html = $participation_html;
                    $html = str_replace(['00000000000000000000', '1/0001'], [$ticket['r'], '1/'.str_pad($ticket['n'], 4,'0',STR_PAD_LEFT)], $html);
                    $qrSrc = $qrCodes[$ticket['r']] ?? '';
                    if ($qrSrc !== '') {
                        $html = app(\App\Http\Controllers\DesignController::class)
                            ->injectTicketQrIntoParticipationHtml($html, $qrSrc);
                    }
                @endphp
            @endif
            <div class="participation-box">
                {!! $html !!}
            </div>
            @if(($i + 1) % $cols == 0)
                <div style="clear: both;"></div>
            @endif
        @endfor
        <div style="clear: both;"></div>
    </div>
@endforeach
</body>
</html>

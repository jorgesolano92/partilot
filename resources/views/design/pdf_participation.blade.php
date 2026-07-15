@php
    $use_prebuilt_cells = $use_prebuilt_cells ?? false;
    $pdfDocumentTitle = $pdfDocumentTitle ?? 'Participación PDF';
    $cols = max(1, (int) ($cols ?? 1));

    /**
     * EXPERIMENTO (ajustar aquí):
     * Sustituye background-image de #design-participation-bg por <img class="design-pdf-bg-img">.
     * La capa ya lleva left/top/right/bottom = márgenes virtuales (sangrado).
     * El img rellena esa capa; tweakea estilos abajo / CSS .design-pdf-bg-img.
     */
    $pdfBgAsImgEnabled = true;
    $pdfBgImgInlineStyle = 'position:absolute;left:0;top:0;width:100%;height:100%;border:0;margin:0;padding:0;display:block;';

    $promotePdfBgToImg = static function (string $html) use ($pdfBgAsImgEnabled, $pdfBgImgInlineStyle): string {
        if (! $pdfBgAsImgEnabled || $html === '' || stripos($html, 'design-participation-bg') === false) {
            return $html;
        }

        return preg_replace_callback(
            '/(<div[^>]*\bid=(["\'])design-participation-bg\2[^>]*>)(.*?)(<\/div>)/is',
            static function (array $m) use ($pdfBgImgInlineStyle): string {
                $open = $m[1];
                $inner = $m[3];
                $close = $m[4];

                // Evitar doble inyección
                if (stripos($inner, 'design-pdf-bg-img') !== false || stripos($inner, '<img') !== false) {
                    return $m[0];
                }

                if (! preg_match('/\bstyle=(["\'])(.*?)\1/is', $open, $sm)) {
                    return $m[0];
                }
                $style = $sm[2];
                if (! preg_match('/\bbackground-image\s*:\s*url\s*\(\s*[\'"]?([^\'")\s]+)[\'"]?\s*\)/i', $style, $um)) {
                    return $m[0];
                }
                $src = trim(html_entity_decode($um[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r'\"");
                if ($src === '' || strcasecmp($src, 'none') === 0) {
                    return $m[0];
                }

                // Quitar background-* del div; conservar position + left/top/right/bottom (límites virtuales)
                $newStyle = preg_replace('/\bbackground-image\s*:[^;]+;?/i', '', $style) ?? $style;
                $newStyle = preg_replace('/\bbackground-size\s*:[^;]+;?/i', '', $newStyle) ?? $newStyle;
                $newStyle = preg_replace('/\bbackground-position\s*:[^;]+;?/i', '', $newStyle) ?? $newStyle;
                $newStyle = preg_replace('/\bbackground-repeat\s*:[^;]+;?/i', '', $newStyle) ?? $newStyle;
                $newStyle = trim(preg_replace('/;+/', ';', $newStyle) ?? $newStyle, "; \t\n\r");
                if ($newStyle !== '' && substr($newStyle, -1) !== ';') {
                    $newStyle .= ';';
                }
                if (! preg_match('/\boverflow\s*:/i', $newStyle)) {
                    $newStyle .= 'overflow:hidden;';
                }

                $open = preg_replace(
                    '/\bstyle=(["\'])(.*?)\1/is',
                    'style=$1'.$newStyle.'$1',
                    $open,
                    1
                ) ?? $open;

                $img = '<img class="design-pdf-bg-img" src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
                    .'" alt="" style="'.$pdfBgImgInlineStyle.'" />';

                return $open.$img.$inner.$close;
            },
            $html,
            1
        ) ?? $html;
    };
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

        /* Misma tipografía/reset que el editor (design_canvas_styles) */
        @include('design.partials.design_canvas_styles')

        /* Layout PDF / DomPDF (no tocar tipografía del canvas) */
        [id*="containment-wrapper"] {
            position: relative;
            background-size: cover !important;
            background-repeat: no-repeat !important;
            background-position: center center !important;
            width: unset !important;
        }

        /*
         * EXPERIMENTO: fondo como <img> dentro de #design-participation-bg
         * (esa capa ya tiene left/top/right/bottom = márgenes virtuales).
         * Ajusta width/height/left/top aquí si hace falta afinado fino.
         */
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
            /* content-box: width/height del HTML ya vienen compensados (−2×padding) */
            box-sizing: content-box !important;
            overflow: hidden !important;
        }

        /* No usar display:table en .elements: DomPDF expande la altura al ticket completo */

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
                @php $html = $promotePdfBgToImg($page[$i]); @endphp
            @else
                @php
                    $ticket = $page[$i];
                    $html = $participation_html;
                    $html = str_replace(['00000000000000000000', '1/0001'], [$ticket['r'], '1/'.str_pad($ticket['n'], 4,'0',STR_PAD_LEFT)], $html);
                    $qrCodeBase64 = $qrCodes[$ticket['r']] ?? '';
                    $html = app(\App\Http\Controllers\DesignController::class)
                        ->injectTicketQrIntoParticipationHtml($html, $qrCodeBase64);
                    $html = $promotePdfBgToImg($html);
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

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Previsualización participación</title>
    <style>
        @include('design.partials.design_canvas_styles')
        html, body { margin: 0; padding: 0; background: #f3f4f6; }
        #capture-wrap { padding: 8px; }
        #capture-wrap button,
        #capture-wrap .edit-btn,
        #capture-wrap .margen-izquierdo,
        #capture-wrap .margen-arriba,
        #capture-wrap .margen-derecho,
        #capture-wrap .margen-abajo,
        #capture-wrap .caja-matriz { display: none !important; }
        #capture-wrap [id*="containment-wrapper"] {
            position: relative;
            background-size: cover !important;
            background-repeat: no-repeat !important;
            background-position: center center !important;
            margin: 0 auto;
        }
        #capture-wrap .elements { position: absolute !important; }
        #capture-wrap .elements.images img {
            max-width: 100% !important;
            max-height: 100% !important;
            width: auto !important;
            height: auto !important;
            display: block;
        }
    </style>
</head>
<body>
    <div id="capture-wrap">
        {!! $html !!}
    </div>
    <script>
        (function () {
            var numbers = @json(array_map(fn ($n) => str_pad((string) $n, 5, '0', STR_PAD_LEFT), $reservation_numbers ?? []));
            var ref = @json($ticket['data']['participation_number'] ?? '');
            var code = @json($ticket['data']['participation_code'] ?? '');
            document.querySelectorAll('#capture-wrap .elements.number, #capture-wrap .elements.mini').forEach(function (el) {
                if (numbers[0]) el.querySelectorAll('span, strong, p').forEach(function (n) {
                    if (/\d{4,5}/.test(n.textContent || '')) n.textContent = numbers[0];
                });
            });
            document.querySelectorAll('#capture-wrap .elements.reference').forEach(function (el) {
                el.querySelectorAll('span, strong, p').forEach(function (n) {
                    if (/0{8,}|Ref/i.test(n.textContent || '') || /^\d{15,}$/.test((n.textContent || '').trim())) {
                        n.textContent = (n.textContent || '').replace(/0{10,}/, ref).replace(/\d{15,}/, ref);
                        if (!/\d{10,}/.test(n.textContent || '') && ref) n.textContent = ref;
                    }
                });
            });
            document.querySelectorAll('#capture-wrap .elements.participation').forEach(function (el) {
                if (!code) return;
                el.querySelectorAll('span, strong, p').forEach(function (n) {
                    if (/1\/0001|N[ºo°\.]/i.test(n.textContent || '')) {
                        n.textContent = (n.textContent || '').replace(/1\/0001/, code).replace(/N[ºo°\.]\s*1\/0001/i, 'N.º ' + code);
                    }
                });
            });
        })();
    </script>
</body>
</html>

@extends('layouts.layout')

@section('title', 'Imagen para redes (sin QR)')

@section('content')
<style>
    @include('design.partials.design_canvas_styles')
    .js-capture-root .edit-btn,
    .js-capture-root button {
        display: none !important;
    }
    .js-capture-root .margen-izquierdo,
    .js-capture-root .margen-arriba,
    .js-capture-root .margen-derecho,
    .js-capture-root .margen-abajo,
    .js-capture-root .caja-matriz {
        display: none !important;
    }
    .js-capture-root .elements.qr,
    .js-capture-root .elements.participation,
    .js-capture-root .elements.reference {
        display: none !important;
    }
</style>

<div class="container-fluid partilot-page-shell">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('design.index') }}">Diseño e Impresión</a></li>
                        <li class="breadcrumb-item active">Imagen marketing</li>
                    </ol>
                </div>
                <h4 class="page-title">Imagen del diseño (sin QR ni nº participación)</h4>
            </div>
        </div>
    </div>

    <div class="row partilot-page-panel-row">
        <div class="col-12">
            <div class="card partilot-page-panel">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">{{ $design->design_name ?: ($set?->set_name ?? ('Diseño #' . $design->id)) }}</h5>
                            <p class="text-muted small mb-0">Para carteles y redes. Sin QR ni nº de participación. Se genera a tamaño de impresión (~300 dpi). Preferible <strong>JPG</strong> (más ligero y nítido); el PNG pesa mucho más.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-primary" id="btn-download-jpg">
                                <i class="ri-image-line"></i> Descargar JPG
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-download-png">
                                <i class="ri-download-2-line"></i> Descargar PNG
                            </button>
                        </div>
                    </div>
                    <hr>
                    <div class="bg-light rounded p-3" style="overflow:auto;">
                        @php
                            $matrixBoxMm = (float) ($design->matrix_box ?? 40);
                            $captureWidth = max(10, 200 - $matrixBoxMm);
                        @endphp
                        <div id="capture-wrap" class="js-capture-root" style="background:#fff; display:inline-block; width: {{ $captureWidth }}mm; height: 92mm; overflow: hidden; border: 1px solid #e5e5e5; position: relative;">
                            <div id="capture" style="width: 200mm; height: 92mm; position: relative; overflow: hidden; right: {{ $matrixBoxMm }}mm;">
                                {!! $html !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/libs/html2canvas/html2canvas.min.js') }}"></script>
@include('design.partials.capture_participation_image')
<script>
(function () {
    var widthMm = {{ (float) max(10, 200 - (float) ($design->matrix_box ?? 40)) }};
    var designId = {{ (int) $design->id }};

    async function run(mime, ext, btn) {
        var el = document.getElementById('capture-wrap');
        if (!el || !window.PartilotCaptureParticipationImage) return;
        btn.disabled = true;
        var old = btn.innerHTML;
        btn.innerHTML = '<i class="ri-loader-4-line"></i> Generando…';
        try {
            await window.PartilotCaptureParticipationImage.download(el, {
                widthMm: widthMm,
                heightMm: 92,
                mime: mime,
                quality: mime === 'image/jpeg' ? 0.85 : undefined,
                filename: 'diseno-marketing-' + designId + '.' + ext,
            });
        } catch (err) {
            console.error(err);
            alert('No se pudo generar la imagen. Prueba de nuevo o usa JPG.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = old;
        }
    }

    document.getElementById('btn-download-png')?.addEventListener('click', function () {
        run('image/png', 'png', this);
    });
    document.getElementById('btn-download-jpg')?.addEventListener('click', function () {
        run('image/jpeg', 'jpg', this);
    });
})();
</script>
@endsection

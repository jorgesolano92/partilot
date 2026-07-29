@extends('layouts.layout')

@section('title','Descargar imagen (digital)')

@section('content')

<style>
    @include('design.partials.design_canvas_styles')
    .js-capture-root button {
        display: none;
    }
    .js-capture-root .margen-izquierdo,
    .js-capture-root .margen-arriba,
    .js-capture-root .margen-derecho,
    .js-capture-root .margen-abajo,
    .js-capture-root .caja-matriz {
        display: none;
    }
</style>

<div class="container-fluid partilot-page-shell">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('design.index') }}">Diseño e Impresión</a></li>
                        <li class="breadcrumb-item active">Imagen participación (digital)</li>
                    </ol>
                </div>
                <h4 class="page-title">Participación digital (imagen)</h4>
            </div>
        </div>
    </div>

    <div class="row partilot-page-panel-row">
        <div class="col-12">
            <div class="card partilot-page-panel">
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-8 partilot-page-panel__col-main">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <h5 class="mb-1">{{ $set?->set_name ?? ('Set #' . ($set?->id ?? '')) }}</h5>
                                    <div class="text-muted small">
                                        @php
                                            $nums = is_array($reservation_numbers ?? null) ? $reservation_numbers : [$reservation_numbers ?? null];
                                            $nums = array_values(array_filter($nums));
                                        @endphp
                                        Números: {{ $nums ? implode(' - ', $nums) : '—' }}
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('design.editFormat', $design->id) }}" class="btn btn-sm btn-light">
                                        <i class="ri-edit-line"></i> Editar diseño
                                    </a>
                                    <button type="button" class="btn btn-sm btn-primary" id="btn-download">
                                        <i class="ri-download-2-line"></i> Descargar JPG
                                    </button>
                                </div>
                            </div>

                            <hr>

                            <div class="bg-light rounded p-3" style="overflow:auto;">
                                @php
                                    $matrixBoxMm = (float)($design->matrix_box ?? 40);
                                    $captureWidth = max(10, 200 - $matrixBoxMm);
                                @endphp
                                <div id="capture-wrap" class="js-capture-root" style="background:#fff; display:inline-block; width: {{ $captureWidth }}mm; height: 92mm; overflow: hidden; border: 1px solid #e5e5e5; position: relative;">
                                    <div id="capture" style="width: 200mm; height: 92mm; position: relative; overflow: hidden; right: {{ $matrixBoxMm }}mm;">
                                        {!! $html !!}
                                    </div>
                                </div>
                            </div>

                            <small class="text-muted d-block mt-2">
                                Consejo: si el diseño usa imágenes, deben ser URLs accesibles desde el navegador para que salgan en la captura.
                            </small>
                        </div>
                        <div class="col-lg-4 partilot-page-panel__col-side">
                            <h5 class="mb-2">Qué hace esto</h5>
                            <p class="text-muted mb-0">
                                Esta pantalla renderiza el HTML de la participación digital y permite descargar una imagen (PNG) desde el navegador.
                                Para sets físicos, la descarga se gestiona por PDF.
                            </p>
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
document.getElementById('btn-download')?.addEventListener('click', async function () {
    const el = document.getElementById('capture-wrap');
    if (!el || !window.PartilotCaptureParticipationImage) return;

    const btn = this;
    btn.disabled = true;
    const old = btn.innerHTML;
    btn.innerHTML = '<i class="ri-loader-4-line"></i> Generando…';
    try {
        const widthMm = {{ (float) max(10, 200 - (float) ($design->matrix_box ?? 40)) }};
        await window.PartilotCaptureParticipationImage.download(el, {
            widthMm: widthMm,
            heightMm: 92,
            mime: 'image/jpeg',
            quality: 0.85,
            filename: 'participacion-digital-design-{{ (int) $design->id }}.jpg',
        });
    } catch (err) {
        console.error(err);
        alert('No se pudo generar la imagen. Prueba de nuevo.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = old;
    }
});
</script>
@endsection

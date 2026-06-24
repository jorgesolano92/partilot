@extends('layouts.layout')

@section('title', 'Imagen para redes (sin QR)')

@section('content')
<style>
    #capture-wrap .edit-btn,
    #capture-wrap button {
        display: none !important;
    }
    #capture-wrap .margen-izquierdo,
    #capture-wrap .margen-arriba,
    #capture-wrap .margen-derecho,
    #capture-wrap .margen-abajo,
    #capture-wrap .caja-matriz {
        display: none !important;
    }
    #capture-wrap .elements.qr,
    #capture-wrap .elements.participation,
    #capture-wrap .elements.reference {
        display: none !important;
    }
</style>

<div class="container-fluid">
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

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">{{ $design->design_name ?: ($set?->set_name ?? ('Diseño #' . $design->id)) }}</h5>
                            <p class="text-muted small mb-0">Para carteles y redes sociales. Oculta QR, referencia y número de participación.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-primary" id="btn-download-png">
                                <i class="ri-download-2-line"></i> Descargar PNG
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-download-jpg">
                                <i class="ri-image-line"></i> Descargar JPG
                            </button>
                        </div>
                    </div>
                    <hr>
                    <div class="bg-light rounded p-3" style="overflow:auto;">
                        @php
                            $matrixBoxMm = (float) ($design->matrix_box ?? 40);
                            $captureWidth = max(10, 200 - $matrixBoxMm);
                        @endphp
                        <div id="capture-wrap" style="background:#fff; display:inline-block; width: {{ $captureWidth }}mm; height: 92mm; overflow: hidden; border: 1px solid #e5e5e5; position: relative;">
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
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
(function () {
    async function downloadImage(mime, ext) {
        const el = document.getElementById('capture-wrap');
        if (!el) return;
        const canvas = await html2canvas(el, { backgroundColor: '#ffffff', scale: 2, useCORS: true });
        const link = document.createElement('a');
        link.download = 'diseno-marketing-{{ (int) $design->id }}.' + ext;
        link.href = canvas.toDataURL(mime, mime === 'image/jpeg' ? 0.92 : undefined);
        link.click();
    }
    document.getElementById('btn-download-png')?.addEventListener('click', function () {
        this.disabled = true;
        downloadImage('image/png', 'png').finally(() => { this.disabled = false; });
    });
    document.getElementById('btn-download-jpg')?.addEventListener('click', function () {
        this.disabled = true;
        downloadImage('image/jpeg', 'jpg').finally(() => { this.disabled = false; });
    });
})();
</script>
@endsection

@extends('layouts.layout')

@section('title', 'Vista previa del diseño')

@section('content')
<style>
    @include('design.partials.design_canvas_styles')
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
    #capture-wrap .ui-draggable-handle {
        cursor: default;
    }
    #capture-wrap [id*="containment-wrapper"] {
        position: relative;
        background-size: cover !important;
        background-repeat: no-repeat !important;
        background-position: center center !important;
    }
    #capture-wrap .elements {
        position: absolute !important;
    }
    #capture-wrap .elements.images img {
        max-width: 100% !important;
        max-height: 100% !important;
        width: auto !important;
        height: auto !important;
        display: block;
    }
</style>

<div class="container-fluid partilot-page-shell">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('design.index') }}">Diseño e Impresión</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('design.summary', $design->id) }}">Resumen</a></li>
                        <li class="breadcrumb-item active">Vista previa</li>
                    </ol>
                </div>
                <h4 class="page-title">Vista previa del diseño</h4>
            </div>
        </div>
    </div>

    <div class="row partilot-page-panel-row">
        <div class="col-12">
            <div class="card partilot-page-panel">
                <div class="card-body">
                    @if(!empty($printOrderLock['completed']))
                        <div class="alert alert-success">
                            <i class="ri-checkbox-circle-line me-1"></i>
                            La imprenta completó el pedido
                            @if(!empty($latestPrintOrder?->order_code))
                                <strong>{{ $latestPrintOrder->order_code }}</strong>
                            @endif
                            (enviado).
                        </div>
                    @elseif(!empty($printOrderLock['locked']))
                        <div class="alert alert-info">
                            <i class="ri-printer-line me-1"></i>
                            Este diseño está en imprenta
                            @if(!empty($latestPrintOrder?->order_code))
                                (orden <strong>{{ $latestPrintOrder->order_code }}</strong>)
                            @endif
                            y no puede editarse mientras la orden esté activa.
                        </div>
                    @endif

                    <div class="row g-4">
                        <div class="col-lg-8">
                            <h5 class="mb-1">Participación</h5>
                            <p class="text-muted small mb-3">Vista de solo lectura del diseño guardado.</p>
                            <div class="bg-light rounded p-3" style="overflow:auto;">
                                @if(!empty(trim(strip_tags($html ?? ''))))
                                    @php
                                        $matrixBoxMm = (float) ($design->matrix_box ?? 40);
                                        $captureWidth = max(10, 200 - $matrixBoxMm);
                                    @endphp
                                    <div id="capture-wrap" style="background:#fff; display:inline-block; width: {{ $captureWidth }}mm; height: 92mm; overflow: hidden; border: 1px solid #e5e5e5; position: relative;">
                                        <div id="capture" style="width: 200mm; height: 92mm; position: relative; overflow: hidden; right: {{ $matrixBoxMm }}mm;">
                                            {!! $html !!}
                                        </div>
                                    </div>
                                @else
                                    <p class="text-muted mb-0">Sin contenido de participación.</p>
                                @endif
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <h5 class="mb-3">Datos del set</h5>
                            <p class="mb-1"><strong>Entidad:</strong> {{ $design->entity->name ?? '—' }}</p>
                            <p class="mb-1"><strong>Set:</strong> {{ $design->set->set_name ?? ('#'.$design->set_id) }}</p>
                            <p class="mb-3"><strong>Participaciones:</strong> {{ number_format((int) ($design->set->total_participations ?? 0), 0, ',', '.') }}</p>

                            @if($latestPrintOrder)
                                <p class="mb-1"><strong>Orden imprenta:</strong> {{ $latestPrintOrder->order_code }}</p>
                                <p class="mb-3"><strong>Estado:</strong> {{ \App\Models\PrintOrder::statusLabel((string) $latestPrintOrder->status) }}</p>
                            @endif

                            <a href="{{ route('design.summary', $design->id) }}" class="btn btn-dark w-100">
                                <i class="ri-arrow-left-line me-1"></i> Volver al resumen
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

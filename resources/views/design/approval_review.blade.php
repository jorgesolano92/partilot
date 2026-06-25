@extends('layouts.layout')

@section('title', 'Revisar diseño')

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
                        <li class="breadcrumb-item"><a href="{{ route('design.approvals.index') }}">Aprobaciones</a></li>
                        <li class="breadcrumb-item active">Revisar</li>
                    </ol>
                </div>
                <h4 class="page-title">Aprobar diseño de participación</h4>
            </div>
        </div>
    </div>

    <div class="row partilot-page-panel-row">
        <div class="col-12">
            <div class="card partilot-page-panel">
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="partilot-page-panel__section">
                                <h5 class="mb-1">Vista previa</h5>
                                <p class="text-muted small mb-3">Revisa el diseño tal como se imprimirá en la participación.</p>
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
                        </div>
                        <div class="col-lg-4">
                            <div class="partilot-page-panel__section partilot-page-panel__aside h-100">
                                <h5 class="mb-3">Datos del set</h5>
                                <p class="mb-1"><strong>Entidad:</strong> {{ $design->entity->name ?? '—' }}</p>
                                <p class="mb-1"><strong>Set:</strong> {{ $design->set->set_name ?? ('#'.$design->set_id) }}</p>
                                <p class="mb-3"><strong>Participaciones:</strong> {{ number_format((int) ($design->set->total_participations ?? 0), 0, ',', '.') }}</p>

                                <p class="text-muted small">
                                    Al aprobar el diseño se habilitará el cobro de la cuota de gestión PARTILOT al pagador configurado en la entidad.
                                </p>

                                <form action="{{ route('design.approve', $design->id) }}" method="POST" class="mb-2" onsubmit="return confirm('¿Confirmar que aprueba este diseño?');">
                                    @csrf
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="ri-check-line me-1"></i> Aprobar diseño
                                    </button>
                                </form>

                                <form action="{{ route('design.reject', $design->id) }}" method="POST" onsubmit="return confirm('¿Rechazar este diseño?');">
                                    @csrf
                                    <div class="mb-2">
                                        <label class="form-label small">Motivo del rechazo (opcional)</label>
                                        <textarea name="reason" class="form-control form-control-sm" rows="3" placeholder="Indique qué debe corregir la administración"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-outline-danger w-100">
                                        <i class="ri-close-line me-1"></i> Rechazar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

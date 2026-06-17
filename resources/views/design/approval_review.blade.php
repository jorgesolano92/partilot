@extends('layouts.layout')

@section('title', 'Revisar diseño')

@section('content')
<div class="container-fluid">
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

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="mb-3">Vista previa</h5>
                    <div class="border rounded p-3 bg-light overflow-auto" style="max-height: 520px;">
                        {!! $design->participation_html ?? '<p class="text-muted mb-0">Sin contenido de participación.</p>' !!}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
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
@endsection

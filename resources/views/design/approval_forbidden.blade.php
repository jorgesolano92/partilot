@extends('layouts.layout')

@section('title', 'Sin permisos para aprobar')

@section('content')
<div class="container-fluid partilot-page-shell">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Aprobación de diseño</h4>
            </div>
        </div>
    </div>

    <div class="row partilot-page-panel-row">
        <div class="col-12 col-lg-8 col-xl-6">
            <div class="card partilot-page-panel">
                <div class="card-body">
                    <div class="alert alert-warning mb-3" role="alert">
                        <h5 class="alert-heading mb-2">
                            <i class="ri-lock-2-line me-1"></i> No tiene permisos para aprobar este diseño
                        </h5>
                        <p class="mb-0">
                            Solo el <strong>gestor responsable</strong> de la entidad puede revisar y aprobar
                            (o rechazar) el diseño de participaciones.
                        </p>
                    </div>

                    @if(!empty($isEntityPanelAccount))
                        <p>
                            Ha iniciado sesión con la <strong>cuenta de la entidad</strong>.
                            Esa cuenta es de consulta y no puede aprobar diseños.
                        </p>
                        <p class="mb-3">
                            Cierre sesión e inicie sesión con la cuenta del <strong>gestor responsable</strong>
                            @if(!empty($managerEmail))
                                (<span class="text-muted">({{ $managerEmail }})</span>
                            @endif
                            y vuelva a abrir el enlace del correo de aprobación.
                        </p>
                    @else
                        <p class="mb-3">
                            Si es el gestor responsable, compruebe que está usando su cuenta de gestor
                            (no la de entidad ni otra distinta) y que la invitación del rol está aceptada.
                        </p>
                    @endif

                    @if($design ?? null)
                        <p class="text-muted small mb-3">
                            Diseño:
                            <strong>{{ $design->design_name ?: ('#'.$design->id) }}</strong>
                            @if($design->entity)
                                — entidad <strong>{{ $design->entity->name }}</strong>
                            @endif
                        </p>
                    @endif

                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ url('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-logout-box-r-line me-1"></i> Cerrar sesión
                            </button>
                        </form>
                        <a href="{{ url('/') }}" class="btn btn-outline-secondary">Ir al inicio</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

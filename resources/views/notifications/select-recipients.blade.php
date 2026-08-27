@extends('layouts.layout')

@section('title','Notificaciones')

@section('content')

<style>
.notification-wizard-card {
    min-height: 658px;
    display: flex;
    flex-direction: column;
}
.notification-wizard-card > form {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
}
.notification-wizard-body {
    flex: 1 1 auto;
}
.notification-wizard-actions {
    margin-top: auto;
    padding-top: 1.25rem;
    text-align: right;
}
</style>

<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Notificaciones</a></li>
                        <li class="breadcrumb-item active">Nueva</li>
                        <li class="breadcrumb-item active">Destinatarios</li>
                    </ol>
                </div>
                <h4 class="page-title">Notificaciones</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">Selección</h4>
                    <br>

                    <div class="row">
                        <div class="col-md-3" style="position: relative;">
                            <div class="form-card bs mb-3">
                                <div class="form-wizard-element">
                                    <span>1</span>
                                    <img src="{{url('assets/entidad.svg')}}" alt="">
                                    <label>Selección Tipo</label>
                                </div>
                                <div class="form-wizard-element">
                                    <span>2</span>
                                    <img width="26px" src="{{url('icons_/selec_sorteo.svg')}}" alt="">
                                    <label>Selección Destino</label>
                                </div>
                                <div class="form-wizard-element active">
                                    <span>3</span>
                                    <img src="{{url('assets/entidad.svg')}}" alt="">
                                    <label>Destinatarios</label>
                                </div>
                                <div class="form-wizard-element">
                                    <span>4</span>
                                    <img src="{{url('assets/entidad.svg')}}" alt="">
                                    <label>Mensaje</label>
                                </div>
                            </div>

                            <div class="mt-3">
                                <h6>Entidades:</h6>
                                @foreach($selectedEntities as $entity)
                                    <div class="mb-1"><small>{{ $entity->name }}</small></div>
                                @endforeach
                            </div>

                            <a href="javascript:history.back()" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                        </div>
                        <div class="col-md-9">
                            <div class="form-card bs notification-wizard-card">
                                <form action="{{ route('notifications.store-recipients') }}" method="POST" id="recipients-form">
                                    @csrf
                                    <div class="notification-wizard-body">
                                    <h4 class="mb-0 mt-1">Destinatarios del push</h4>
                                    <small><i>Elige a quién se envía dentro de la(s) entidad(es)</i></small>

                                    <br><br>

                                    @if ($errors->any())
                                        <div class="alert alert-danger">
                                            <ul class="mb-0">
                                                @foreach ($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    <div class="mb-3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input recipient-mode" type="radio" name="recipient_mode" id="mode_entity" value="entity" {{ old('recipient_mode') === 'entity' ? 'checked' : '' }} required>
                                            <label class="form-check-label" for="mode_entity">
                                                <strong>La entidad</strong> — cuenta panel de la(s) entidad(es)
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input recipient-mode" type="radio" name="recipient_mode" id="mode_managers" value="managers" {{ old('recipient_mode') === 'managers' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="mode_managers">
                                                <strong>Gestores / responsables</strong> — elige con checks
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input recipient-mode" type="radio" name="recipient_mode" id="mode_all" value="all_involved" {{ old('recipient_mode') === 'all_involved' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="mode_all">
                                                <strong>Todos los involucrados</strong> — administración (panel y gestor), entidad, gestores y responsables
                                            </label>
                                        </div>
                                    </div>

                                    <div id="managers-box" class="border rounded p-3 mb-3" style="display: none;">
                                        <h5 class="mb-3">Gestores de la(s) entidad(es)</h5>
                                        @if($managers->isEmpty())
                                            <p class="text-muted mb-0">No hay gestores con usuario vinculados a estas entidades.</p>
                                        @else
                                            @foreach($managers as $manager)
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input manager-checkbox" type="checkbox" name="manager_ids[]" value="{{ $manager->id }}" id="manager_{{ $manager->id }}"
                                                        {{ in_array($manager->id, old('manager_ids', [])) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="manager_{{ $manager->id }}">
                                                        {{ $manager->user->name ?? 'Sin nombre' }}
                                                        <small class="text-muted">({{ $manager->entity->name ?? 'Entidad' }})</small>
                                                        @if($manager->is_primary)
                                                            <span class="badge bg-primary">Responsable</span>
                                                        @else
                                                            <span class="badge bg-secondary">Gestor</span>
                                                        @endif
                                                    </label>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                    </div>

                                    <div class="notification-wizard-actions">
                                        <button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light">
                                            Continuar
                                            <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i>
                                        </button>
                                    </div>
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

@section('scripts')
<script>
$(document).ready(function() {
    function syncManagersBox() {
        var mode = $('input[name="recipient_mode"]:checked').val();
        if (mode === 'managers') {
            $('#managers-box').show();
        } else {
            $('#managers-box').hide();
        }
    }

    $('input[name="recipient_mode"]').on('change', syncManagersBox);
    syncManagersBox();

    $('#recipients-form').on('submit', function(e) {
        var mode = $('input[name="recipient_mode"]:checked').val();
        if (mode === 'managers' && $('.manager-checkbox:checked').length === 0) {
            e.preventDefault();
            alert('Selecciona al menos un gestor o responsable');
        }
    });
});
</script>
@endsection

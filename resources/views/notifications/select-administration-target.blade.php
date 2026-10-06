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
                        <li class="breadcrumb-item active">Destino administración</li>
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
                                    <label>Selecc. Administración</label>
                                </div>
                                <div class="form-wizard-element active">
                                    <span>3</span>
                                    <img src="{{url('assets/entidad.svg')}}" alt="">
                                    <label>Destino</label>
                                </div>
                                <div class="form-wizard-element">
                                    <span>4</span>
                                    <img src="{{url('assets/entidad.svg')}}" alt="">
                                    <label>Mensaje</label>
                                </div>
                            </div>

                            <a href="{{route('notifications.select-administration')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                        </div>
                        <div class="col-md-9">
                            <div class="form-card bs notification-wizard-card">
                                <form action="{{ route('notifications.store-administration-target') }}" method="POST" id="admin-target-form">
                                    @csrf
                                    <div class="notification-wizard-body">
                                        <h4 class="mb-0 mt-1">Destino del push</h4>
                                        <small><i>Administración: {{ $administration->name }}</i></small>

                                        <br><br>

                                        <input type="hidden" name="admin_target" id="admin_target" value="">

                                        <div class="d-flex flex-wrap gap-3 justify-content-center mt-3">
                                            <button type="button" class="btn btn-light btn-xl text-center m-2 bs admin-target-btn" data-target="administration" style="border: 1px solid #f0f0f0; padding: 20px; width: 240px; border-radius: 16px;">
                                                <h4 class="mb-1">La administración</h4>
                                                <small class="text-muted">Panel y/o gestor de esta administración</small>
                                            </button>
                                            <button type="button" class="btn btn-light btn-xl text-center m-2 bs admin-target-btn" data-target="entities" style="border: 1px solid #f0f0f0; padding: 20px; width: 240px; border-radius: 16px;">
                                                <h4 class="mb-1">Sus entidades</h4>
                                                <small class="text-muted">Elegir entidades y destinatarios</small>
                                            </button>
                                        </div>

                                        <div id="admin-recipient-box" class="border rounded p-3 mt-3 mx-auto" style="max-width: 520px; display: none;">
                                            <h5 class="mb-3">¿A quién de la administración?</h5>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="admin_recipient_mode" id="admin_mode_panel" value="panel" checked>
                                                <label class="form-check-label" for="admin_mode_panel">
                                                    <strong>Cuenta panel</strong> de la administración
                                                </label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="admin_recipient_mode" id="admin_mode_manager" value="manager">
                                                <label class="form-check-label" for="admin_mode_manager">
                                                    <strong>Gestor</strong> de la administración
                                                </label>
                                            </div>
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="radio" name="admin_recipient_mode" id="admin_mode_both" value="both">
                                                <label class="form-check-label" for="admin_mode_both">
                                                    <strong>Ambos</strong> — panel y gestor
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="notification-wizard-actions">
                                        <button type="submit" id="submit-btn" disabled style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light">
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
    function syncAdminRecipientBox() {
        var target = $('#admin_target').val();
        if (target === 'administration') {
            $('#admin-recipient-box').show();
        } else {
            $('#admin-recipient-box').hide();
        }
    }

    $('.admin-target-btn').on('click', function() {
        $('.admin-target-btn').removeClass('btn-primary').addClass('btn-light');
        $(this).removeClass('btn-light').addClass('btn-primary');
        $('#admin_target').val($(this).data('target'));
        $('#submit-btn').prop('disabled', false);
        syncAdminRecipientBox();
    });

    $('#admin-target-form').on('submit', function(e) {
        var target = $('#admin_target').val();
        if (!target) {
            e.preventDefault();
            alert('Selecciona un destino');
            return;
        }
        if (target === 'administration' && !$('input[name="admin_recipient_mode"]:checked').val()) {
            e.preventDefault();
            alert('Elige si envías a la cuenta panel, al gestor o a ambos');
        }
    });
});
</script>
@endsection

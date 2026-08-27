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

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Notificaciones</a></li>
                        <li class="breadcrumb-item active">Nueva</li>
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

                    <h4 class="header-title">
                        Selección
                    </h4>

                    <br>

                    <div class="row">
                        <div class="col-md-3" style="position: relative;">
                            <div class="form-card bs mb-3">

                                <div class="form-wizard-element active">
                                    
                                    <span>
                                        1
                                    </span>

                                    <img src="{{url('assets/entidad.svg')}}" alt="">

                                    <label>
                                        Selección Tipo
                                    </label>

                                </div>

                                <div class="form-wizard-element">
                                    
                                    <span>
                                        2
                                    </span>

                                    <img width="26px" src="{{url('icons_/selec_sorteo.svg')}}" alt="">

                                    <label>
                                        Selección Destino
                                    </label>

                                </div>

                                <div class="form-wizard-element">
                                    
                                    <span>
                                        3
                                    </span>

                                    <img src="{{url('assets/entidad.svg')}}" alt="">

                                    <label>
                                        Mensaje
                                    </label>

                                </div>
                                
                            </div>

                            <a href="{{route('notifications.index')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                        </div>
                        <div class="col-md-9">
                            <div class="form-card bs notification-wizard-card">
                                <form action="{{ route('notifications.store-type') }}" method="POST">
                                    @csrf
                                    <div class="notification-wizard-body">
                                        <h4 class="mb-0 mt-1">
                                            Administración / Entidad
                                        </h4>
                                        <small><i>Selecciona una Opción</i></small>

                                        <br>
                                        <br>

                                        <div id="all-options-notification">
                                            <div class="mt-4 text-center">
                                                <div id="notification-type-buttons" class="d-flex flex-wrap justify-content-center gap-2">
                                                    <button type="button" class="btn btn-light btn-xl text-center m-2 bs notification-type-btn" id="btn-notificacion-administracion" style="border: 1px solid #f0f0f0; padding: 16px; width: 180px; border-radius: 16px; position: relative;">
                                                        <img class="mt-2 mb-1" src="{{url('assets/vendedor.svg')}}" alt="" width="60%">
                                                        <h4 class="mb-0">Administración</h4>
                                                    </button>

                                                    <button type="button" class="btn btn-light btn-xl text-center m-2 bs notification-type-btn" id="btn-notificacion-entidad" style="border: 1px solid #f0f0f0; padding: 16px; width: 180px; border-radius: 16px;">
                                                        <img class="mt-2 mb-1" src="{{url('assets/vendedor.svg')}}" alt="" width="60%">
                                                        <h4 class="mb-0">Entidad</h4>
                                                    </button>

                                                    <button type="button" class="btn btn-light btn-xl text-center m-2 bs notification-type-btn" id="btn-notificacion-usuario" style="border: 1px solid #f0f0f0; padding: 16px; width: 200px; border-radius: 16px;">
                                                        <img class="mt-2 mb-1" src="{{url('assets/vendedor.svg')}}" alt="" width="60%">
                                                        <h4 class="mb-0">Usuario (push)</h4>
                                                        <small class="text-muted d-block mt-1">Un solo destinatario</small>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <input type="hidden" name="notification_type" id="notification_type" value="">
                                    </div>

                                    <div class="notification-wizard-actions">
                                        <button type="submit" id="submit-btn" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light" disabled>
                                            Aceptar
                                            <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>

                    
                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div>
    <!-- end row-->

</div> <!-- container -->

@endsection

@section('scripts')

<script>
$(document).ready(function() {
    function selectNotificationType(val) {
        $('#notification-type-buttons .notification-type-btn').removeClass('btn-primary').addClass('btn-light');
        if (val === 'administration') {
            $('#btn-notificacion-administracion').removeClass('btn-light').addClass('btn-primary');
        } else if (val === 'entity') {
            $('#btn-notificacion-entidad').removeClass('btn-light').addClass('btn-primary');
        } else if (val === 'user') {
            $('#btn-notificacion-usuario').removeClass('btn-light').addClass('btn-primary');
        }
        $('#notification_type').val(val);
        $('#submit-btn').prop('disabled', false);
    }

    $('#btn-notificacion-administracion').click(function() {
        selectNotificationType('administration');
    });

    $('#btn-notificacion-entidad').click(function() {
        selectNotificationType('entity');
    });

    $('#btn-notificacion-usuario').click(function() {
        selectNotificationType('user');
    });

    $('form').submit(function(e) {
        if ($('#notification_type').val() === '') {
            e.preventDefault();
            alert('Por favor selecciona un tipo de notificación');
        }
    });
});
</script>

@endsection

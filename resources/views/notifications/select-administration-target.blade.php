@extends('layouts.layout')

@section('title','Notificaciones')

@section('content')

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
                            <div class="form-card bs" style="min-height: 400px;">
                                <form action="{{ route('notifications.store-administration-target') }}" method="POST" id="admin-target-form">
                                    @csrf
                                    <h4 class="mb-0 mt-1">Destino del push</h4>
                                    <small><i>Administración: {{ $administration->name }}</i></small>

                                    <br><br>

                                    <input type="hidden" name="admin_target" id="admin_target" value="">

                                    <div class="d-flex flex-wrap gap-3 justify-content-center mt-4">
                                        <button type="button" class="btn btn-light btn-xl text-center m-2 bs admin-target-btn" data-target="administration" style="border: 1px solid #f0f0f0; padding: 20px; width: 240px; border-radius: 16px;">
                                            <h4 class="mb-1">La administración</h4>
                                            <small class="text-muted">Cuenta panel de esta administración</small>
                                        </button>
                                        <button type="button" class="btn btn-light btn-xl text-center m-2 bs admin-target-btn" data-target="entities" style="border: 1px solid #f0f0f0; padding: 20px; width: 240px; border-radius: 16px;">
                                            <h4 class="mb-1">Sus entidades</h4>
                                            <small class="text-muted">Elegir entidades y destinatarios</small>
                                        </button>
                                    </div>

                                    <div class="row mt-4">
                                        <div class="col-12 text-end">
                                            <button type="submit" id="submit-btn" disabled style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">
                                                Continuar
                                                <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i>
                                            </button>
                                        </div>
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
    $('.admin-target-btn').on('click', function() {
        $('.admin-target-btn').removeClass('btn-primary').addClass('btn-light');
        $(this).removeClass('btn-light').addClass('btn-primary');
        $('#admin_target').val($(this).data('target'));
        $('#submit-btn').prop('disabled', false);
    });

    $('#admin-target-form').on('submit', function(e) {
        if (!$('#admin_target').val()) {
            e.preventDefault();
            alert('Selecciona un destino');
        }
    });
});
</script>
@endsection

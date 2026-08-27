@extends('layouts.layout')

@section('title','Notificaciones')

@section('content')

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

                                <div class="form-wizard-element">
                                    
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

                                <div class="form-wizard-element active">
                                    
                                    <span>
                                        3
                                    </span>

                                    <img src="{{url('assets/entidad.svg')}}" alt="">

                                    <label>
                                        Mensaje
                                    </label>

                                </div>
                                
                            </div>

                            @if(!empty($recipientSummary))
                            <div class="mt-3">
                                <h6>Destinatarios:</h6>
                                <small class="text-muted">{{ $recipientSummary }}</small>
                            </div>
                            @endif

                            @if(($pushScope ?? '') === 'administration' && !empty($administration))
                            <div class="mt-3">
                                <h6>Administración:</h6>
                                <div class="card mb-2" style="padding: 10px;">
                                    <h6 class="mb-0">{{ $administration->name }}</h6>
                                </div>
                            </div>
                            @endif

                            @if($selectedEntities && count($selectedEntities) > 0)
                            <div class="mt-3">
                                <h6>Entidades seleccionadas:</h6>
                                @foreach($selectedEntities as $entity)
                                <div class="card mb-2" style="padding: 10px;">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-2">
                                            <div class="avatar-title rounded-circle bg-light text-dark">
                                                <i class="ri-user-line"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="mb-0">{{ $entity->name }}</h6>
                                            <small class="text-muted">{{ $entity->province ?? 'Sin provincia' }}</small>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @endif

                            <a href="javascript:history.back()" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                        </div>
                        <div class="col-md-9">
                            <div class="form-card bs" style="min-height: 658px;">
                                <form action="{{ route('notifications.store') }}" method="POST">
                                    @csrf
                                    <h4 class="mb-0 mt-1">
                                        Mensaje
                                    </h4>
                                    <small><i>Escribe el mensaje a comunicar</i></small>

                                    <br>
                                    <br>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for="title" class="form-label">Título</label>
                                                <input type="text" class="form-control" id="title" name="title" placeholder="Título" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for="message" class="form-label">Mensaje</label>
                                                <textarea class="form-control" id="message" name="message" rows="8" placeholder="Añade tu texto aquí" required></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-12 text-end">
                                            <button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">Enviar
                                                <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-send-plane-line"></i></button>
                                        </div>
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
    // Validar formulario antes de enviar
    $('form').submit(function(e) {
        var title = $('#title').val().trim();
        var message = $('#message').val().trim();
        
        if (title === '') {
            e.preventDefault();
            alert('Por favor ingresa un título para la notificación');
            $('#title').focus();
            return false;
        }
        
        if (message === '') {
            e.preventDefault();
            alert('Por favor ingresa un mensaje para la notificación');
            $('#message').focus();
            return false;
        }
    });
});
</script>

@endsection

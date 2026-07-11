@extends('layouts.layout')

@section('title','Detalle Reserva')

@section('content')
<!-- Start Content-->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{url('reserves')}}">Reservas</a></li>
                        <li class="breadcrumb-item active">Detalle</li>
                    </ol>
                </div>
                <h4 class="page-title">Detalle Reserva</h4>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">Datos de la Reserva</h4>
                    <br>
                    <div class="row">
                        <div class="col-md-3" style="position: relative;">
                            <div class="form-card bs mb-3">
                                {{-- <div class="form-wizard-element">
                                    <span>1</span>
                                    <img src="{{url('assets/entidad.svg')}}" alt="">
                                    <label>Entidad</label>
                                </div>
                                <div class="form-wizard-element">
                                    <span>2</span>
                                    <img src="{{url('icons_/sorteos.svg')}}" alt="">
                                    <label>Sorteo</label>
                                </div> --}}
                                <div class="form-wizard-element active">
                                    <span>3</span>
                                    <img src="{{url('icons_/reservas.svg')}}" alt="">
                                    <label>Datos Reserva</label>
                                </div>
                            </div>
                            <div class="form-card">
                                <div class="row">
                                    <div class="col-4">
                                        <div class="photo-preview-3 logo-round" @if($reserve->entity->image ?? null) style="background-image: url('{{ asset('uploads/' . $reserve->entity->image) }}');" @endif>
                                            @if(!($reserve->entity->image ?? null))
                                                <i class="ri-account-circle-fill"></i>
                                            @endif
                                        </div>
                                        <div style="clear: both;"></div>
                                    </div>
                                    <div class="col-8 text-center mt-2">
                                        <h3 class="mt-2 mb-0">{{$reserve->entity->name ?? 'Entidad'}}</h3>
                                        <i style="position: relative; top: 3px; font-size: 16px; color: #333" class="ri-computer-line"></i> {{$reserve->entity->province ?? 'Sin provincia'}}
                                    </div>
                                </div>
                            </div>
                            <a href="{{url('reserves')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                        </div>
                        <div class="col-md-9 show-content">
                            <div class="form-card bs" style="min-height: 658px;">
                                <h4 class="mb-0 mt-1 d-flex align-items-center justify-content-between">
                                    <span>Datos del Sorteo</span>
                                    <a href="{{ url('reserves/edit/' . $reserve->id) }}" class="btn btn-light float-end" style="border: 1px solid silver; border-radius: 30px;">
                                        <img src="{{url('assets/form-groups/edit.svg')}}" alt="">
                                        Editar
                                    </a>
                                </h4>
                                <small><i>Información del sorteo asociado</i></small>
                                <br>
                                <div class="row show-content">
                                    <div class="col-3 offset-2">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Número del Sorteo</label>
                                            <div class="input-group input-group-merge group-form">
                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                    <img src="{{url('assets/form-groups/admin/16.svg')}}" alt="">
                                                </div>
                                                <input class="form-control" readonly type="text" value="{{$reserve->lottery->name ?? ''}}" placeholder="Número" style="border-radius: 0 30px 30px 0;">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-7">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Nombre del Sorteo</label>
                                            <div class="input-group input-group-merge group-form">
                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                    <img src="{{url('assets/form-groups/admin/17.svg')}}" alt="">
                                                </div>
                                                <input class="form-control" readonly type="text" value="{{$reserve->lottery->description ?? ''}}" placeholder="Nombre del Sorteo" style="border-radius: 0 30px 30px 0;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row show-content">
                                    <div class="col-4 offset-2">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Tipo de Sorteo</label>
                                            <div class="input-group input-group-merge group-form">
                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                    <img src="{{url('assets/form-groups/admin/14.svg')}}" alt="">
                                                </div>
                                                <input class="form-control" readonly type="text" value="{{$reserve->lottery->lotteryType->name ?? 'Sin tipo'}}" placeholder="Tipo de Sorteo" style="border-radius: 0 30px 30px 0;">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Precio décimo</label>
                                            <div class="input-group input-group-merge group-form">
                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                    <img src="{{url('assets/form-groups/admin/15.svg')}}" alt="">
                                                </div>
                                                <input class="form-control" readonly type="number" value="{{$reserve->lottery->ticket_price ?? 0}}" step="0.01" placeholder="0.00€" style="border-radius: 0 30px 30px 0;">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Fecha Sorteo</label>
                                            <div class="input-group input-group-merge group-form">
                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                    <img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
                                                </div>
                                                <input class="form-control" readonly type="text" value="{{$reserve->lottery->draw_date ? \Carbon\Carbon::parse($reserve->lottery->draw_date)->format('d/m/Y') : 'No definida'}}" placeholder="Fecha" style="border-radius: 0 30px 30px 0;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <h4 class="mb-0 mt-1">Configuración de la Reserva</h4>
                                <small><i>Datos de la reserva</i></small>
                                <br><br>
                                <div style="min-height: 256px;">
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="row" id="numbers">
                                                @if(is_array($reserve->reservation_numbers))
                                                    @foreach($reserve->reservation_numbers as $num)
                                                        <div class="col-3">
                                                            <div class="form-group mt-2 mb-3">
                                                                <label class="label-control">Número</label>
                                                                <div class="input-group input-group-merge group-form">
                                                                    <input class="form-control reservation-number" type="text" value="{{$num}}" readonly style="border-radius: 30px;">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-3">
                                            <div class="form-group mt-2 mb-3">
                                                <label class="label-control">Importe a Reservar</label>
                                                <div class="input-group input-group-merge group-form">
                                                    <input class="form-control" type="number" step="0.01" value="{{$reserve->reservation_amount}}" readonly style="border-radius: 30px;">
                                                </div>
                                                <small class="text-muted"><i>Por cada número seleccionado</i></small>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="form-group mt-2 mb-3">
                                                <label class="label-control">Cantidad de décimos</label>
                                                <div class="input-group input-group-merge group-form">
                                                    <input class="form-control" type="number" value="{{$reserve->reservation_tickets}}" readonly style="border-radius: 30px;">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="form-group mt-2 mb-3">
                                                <label class="label-control">Fecha límite</label>
                                                <div class="input-group input-group-merge group-form">
                                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                        <img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
                                                    </div>
                                                    <input class="form-control" readonly type="text" value="{{ $reserve->expiration_date ? \Carbon\Carbon::parse($reserve->expiration_date)->format('d/m/Y') : 'No definida' }}" style="border-radius: 0 30px 30px 0;">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="form-group mt-2 mb-3">
                                                <label class="label-control">Total</label>
                                                <div class="input-group input-group-merge group-form">
                                                    <input class="form-control" type="text" value="{{ number_format((float)($reserve->total_amount ?? 0), 2, ',', '.') }} €" readonly style="border-radius: 30px;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
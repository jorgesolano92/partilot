@extends('layouts.layout')

@section('title','Detalle Set de Participaciones')

@section('content')

<style>
    
    .form-wizard-element, .form-wizard-element label {
        cursor: pointer;
    }
</style>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ url('sets') }}">Sets</a></li>
                        <li class="breadcrumb-item active">Detalle</li>
                    </ol>
                </div>
                <h4 class="page-title">Detalle Set de Participaciones</h4>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">Detalle del Set</h4>
                    <br>
                    <div class="row">
                        <div class="col-md-3" style="position: relative;">
                            <ul class="form-card bs mb-3 nav">
                                <li class="nav-item">
                                    <div class="form-wizard-element active" data-bs-toggle="tab" data-bs-target="#informacion">
                                        <span>&nbsp;&nbsp;</span>
                                        <img src="{{url('icons_/sets.svg')}}" alt="" width="26px">
                                        <label>Config. Set</label>
                                    </div>
                                </li>

                                @if(auth()->user()?->isSuperAdmin())
                                <li class="nav-item">
                                    <div class="form-wizard-element" data-bs-toggle="tab" data-bs-target="#participaciones">
                                        <span>&nbsp;&nbsp;</span>
                                        <img src="{{url('icons_/sets.svg')}}" alt="" width="26px">
                                        <label>Participaciones</label>
                                    </div>
                                </li>
                                @endif
                            </ul>
                            <a href="{{url('sets')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span>
                            </a>
                        </div>
                        <div class="col-md-9">
                            <div class="form-card bs">

                                <div class="tabbable show-content">
                                
                                    <div class="tab-content p-0">
                                        
                                        <div class="tab-pane fade active show" id="informacion">

                                            <div style="min-height: 658px;">
                                                <h4 class="mb-0 mt-1 d-flex align-items-center justify-content-between">
                                                    <span>Reserva en la que se generó el Set</span>
                                                    <a href="{{ url('sets/edit/' . $set->id) }}" class="btn btn-light float-end" style="border: 1px solid silver; border-radius: 30px;">
                                                        <img src="{{url('assets/form-groups/edit.svg')}}" alt="">
                                                        Editar
                                                    </a>
                                                </h4>
                                                <small><i>Datos de la reserva asociada</i></small>
                                                <br>
                                                <div class="row show-content">
                                                    <div class="col-3 offset-2">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Número del Sorteo</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                    <img src="{{url('assets/form-groups/admin/16.svg')}}" alt="">
                                                                </div>
                                                                <input class="form-control" readonly type="text" value="{{$set->reserve && $set->reserve->lottery ? $set->reserve->lottery->name : 'Sin número'}}" placeholder="46/25" style="border-radius: 0 30px 30px 0;">
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
                                                                <input class="form-control" readonly type="text" value="{{$set->reserve && $set->reserve->lottery ? $set->reserve->lottery->description : 'Sin nombre'}}" placeholder="Nombre del Sorteo" style="border-radius: 0 30px 30px 0;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row show-content">
                                                    <div class="col-3">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Fecha Sorteo</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                    <img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
                                                                </div>
                                                                <input class="form-control" readonly type="text" value="{{$set->reserve && $set->reserve->lottery ? \Carbon\Carbon::parse($set->reserve->lottery->draw_date)->format('d-m-Y') : 'Sin fecha'}}" style="border-radius: 0 30px 30px 0;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-5">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Números</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                    <img src="{{url('assets/form-groups/admin/14.svg')}}" alt="">
                                                                </div>
                                                                <input class="form-control" readonly type="text" value="{{is_array($set->reserve->reservation_numbers ?? null) ? implode(' - ', $set->reserve->reservation_numbers) : ''}}" style="border-radius: 0 30px 30px 0;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-2">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Décimos TOTALES</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <input class="form-control" readonly type="number" value="{{$set->reserve->reservation_tickets ?? ''}}" style="border-radius: 30px;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-2">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Importe TOTAL</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                    <img src="{{url('assets/form-groups/admin/15.svg')}}" alt="">
                                                                </div>
                                                                <input class="form-control" readonly type="number" step="0.01" value="{{$set->reserve->reservation_amount ?? ''}}" style="border-radius: 0 30px 30px 0;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <h4 class="mb-0 mt-1">Configuración del Set</h4>
                                                <small><i>Datos del set creado</i></small>
                                                <br>
                                                <div class="row show-content">
                                                    <div class="col-6">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Nombre del Set</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                    <img src="{{url('assets/form-groups/admin/19.svg')}}" alt="">
                                                                </div>
                                                                <input class="form-control" type="text" value="{{$set->set_name}}" readonly style="border-radius: 0 30px 30px 0;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Importe Jugado (Número)</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                    <img src="{{url('assets/form-groups/admin/15.svg')}}" alt="">
                                                                </div>
                                                                <input class="form-control" type="number" value="{{$set->played_amount}}" readonly style="border-radius: 0 30px 30px 0;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Importe Donativo</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                    <img src="{{url('assets/form-groups/admin/15.svg')}}" alt="">
                                                                </div>
                                                                <input class="form-control" type="number" value="{{$set->donation_amount}}" readonly style="border-radius: 0 30px 30px 0;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Importe Total Participación</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                    <img src="{{url('assets/form-groups/admin/15.svg')}}" alt="">
                                                                </div>
                                                                <input class="form-control" type="number" value="{{$set->total_participation_amount}}" readonly style="border-radius: 0 30px 30px 0;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Participaciones Totales</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                    <img src="{{url('assets/form-groups/admin/20.svg')}}" alt="">
                                                                </div>
                                                                <input class="form-control" type="number" value="{{$set->total_participations}}" readonly style="border-radius: 0 30px 30px 0;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Importe TOTAL</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                    <img src="{{url('assets/form-groups/admin/15.svg')}}" alt="">
                                                                </div>
                                                                <input class="form-control" type="number" value="{{$set->total_amount}}" readonly style="border-radius: 0 30px 30px 0;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Fecha Límite</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                    <img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
                                                                </div>
                                                                <input class="form-control" type="text" value="{{$set->deadline_date ? \Carbon\Carbon::parse($set->deadline_date)->format('d-m-Y') : ''}}" readonly style="border-radius: 0 30px 30px 0;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <h4 class="mb-0 mt-1">Tipo Participaciones</h4>
                                                <small><i>Cantidad de participaciones físicas o digitales</i></small>
                                                <br>
                                                <div class="row show-content">
                                                    <div class="col-3">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Participaciones Físicas</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                    <img src="{{url('assets/form-groups/admin/20.svg')}}" alt="">
                                                                </div>
                                                                <input class="form-control" type="number" value="{{$set->physical_participations}}" readonly style="border-radius: 0 30px 30px 0;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Participaciones Digitales</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                    <img src="{{url('assets/form-groups/admin/2.svg')}}" alt="">
                                                                </div>
                                                                <input class="form-control" type="number" value="{{$set->digital_participations}}" readonly style="border-radius: 0 30px 30px 0;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                {{-- <div class="row">
                                                    <div class="col-12 text-end">
                                                        <a href="{{ url('sets') }}" class="btn btn-secondary" style="border-radius: 30px;">Volver</a>
                                                    </div>
                                                </div> --}}
                                            </div>

                                        </div>

                                        <div class="tab-pane fade" id="participaciones">

                                            @if(auth()->user()?->isSuperAdmin())
                                            @php
                                                $participaciones = $ticketCodes ?? [];
                                            @endphp

                                            <h4 class="mb-0 mt-1 d-flex align-items-center justify-content-between">
                                                <span>Participaciones de este set</span>
                                            </h4>
                                            <small><i>Referencias del set (solo superadministrador)</i></small>
                                            <br>

                                            <div>
                                                <table class="table table-bordered mt-3">
                                                    <thead>
                                                        <tr>
                                                            <th>Número</th>
                                                            <th>Referencia</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($participaciones as $p)
                                                            <tr>
                                                                <td>{{ $p['n'] }}</td>
                                                                <td><a target="_blank" href="{{ url('comprobar-participacion?ref='.$p['r']) }}" title="Ver ticket">{{$p['r']}}</a></td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            @endif

                                        </div>


                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 
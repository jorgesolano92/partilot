@extends('layouts.layout')

@section('title','Editar Set de Participaciones')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ url('sets') }}">Sets</a></li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </div>
                <h4 class="page-title">Editar Set de Participaciones</h4>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">Editar Set</h4>
                    <br>
                    <div class="row">
                        <div class="col-md-3" style="position: relative;">
                            <div class="form-card bs mb-3">
                                <div class="form-wizard-element active">
                                    <span>3</span>
                                    <img src="{{url('icons_/sets.svg')}}" alt="" width="26px">
                                    <label>Config. Set</label>
                                </div>
                            </div>
                            <a href="{{url('sets/view',$set->id)}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span>
                            </a>
                        </div>
                        <div class="col-md-9">
                            <div class="form-card bs">
                                <div style="min-height: 658px;">
                                    {{-- Sin importación XML: las referencias se generan al crear el set --}}
                                    <h4 class="mb-0 mt-1">Reserva en la que se generó el Set</h4>
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
                                    <form action="{{ url('sets/update/' . $set->id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="alert alert-info mb-3" role="alert">
                                            <strong>Nota:</strong> Los sets creados no se pueden modificar, excepto la <strong>fecha límite de cierre de venta</strong>. El resto de datos son solo consulta.
                                        </div>
                                        <h4 class="mb-0 mt-1">Configuración del Set</h4>
                                        <small><i>Solo la fecha límite es editable</i></small>
                                        <br>
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Nombre del Set</label>
                                                    <div class="input-group input-group-merge group-form">
                                                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                            <img src="{{url('assets/form-groups/admin/19.svg')}}" alt="">
                                                        </div>
                                                        <input class="form-control" type="text" value="{{$set->set_name}}" style="border-radius: 0 30px 30px 0;" readonly>
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
                                                        <input class="form-control" type="number" step="0.01" value="{{$set->played_amount}}" style="border-radius: 0 30px 30px 0;" readonly>
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
                                                        <input class="form-control" type="number" step="0.01" value="{{$set->donation_amount}}" style="border-radius: 0 30px 30px 0;" readonly>
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
                                                        <input class="form-control" type="number" step="0.01" value="{{$set->total_participation_amount}}" style="border-radius: 0 30px 30px 0;" readonly>
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
                                                        <input class="form-control" type="number" value="{{$set->total_participations}}" style="border-radius: 0 30px 30px 0;" readonly>
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
                                                        <input class="form-control" type="number" step="0.01" value="{{$set->total_amount}}" style="border-radius: 0 30px 30px 0;" readonly>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Fecha Límite de cierre de venta</label>
                                                    <div class="input-group input-group-merge group-form">
                                                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                            <img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
                                                        </div>
                                                        <input class="form-control" name="deadline_date" type="date" value="{{$set->deadline_date ? \Carbon\Carbon::parse($set->deadline_date)->format('Y-m-d') : ''}}" style="border-radius: 0 30px 30px 0;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <h4 class="mb-0 mt-1">Tipo Participaciones</h4>
                                        <small><i>Cantidad de participaciones físicas o digitales</i></small>
                                        <br>
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Tipo de Participación</label>
                                                    
                                                    <div class="form-check mt-3">
                                                        <input class="form-check-input" type="radio" name="participation_type" id="participation_type_physical" value="physical" {{ $set->physical_participations > 0 ? 'checked' : '' }} disabled>
                                                        <label class="form-check-label" for="participation_type_physical">
                                                            <strong>Participaciones Físicas</strong> ({{ $set->physical_participations }})
                                                        </label>
                                                    </div>
                                                    
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" type="radio" name="participation_type" id="participation_type_digital" value="digital" {{ $set->digital_participations > 0 ? 'checked' : '' }} disabled>
                                                        <label class="form-check-label" for="participation_type_digital">
                                                            <strong>Participaciones Digitales</strong> ({{ $set->digital_participations }})
                                                        </label>
                                                    </div>
                                                    
                                                    <input type="hidden" name="physical_participations" value="{{ $set->physical_participations }}">
                                                    <input type="hidden" name="digital_participations" value="{{ $set->digital_participations }}">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 text-end">
                                                <button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">Guardar
                                                    <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i></button>
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
</div>
@endsection

@section('scripts')

<script>

$(document).ready(function() {
    // Fecha límite: máximo el día anterior al sorteo
    const lotteryDate = @json($set->reserve->lottery->draw_date ?? null);
    if (lotteryDate) {
        const lotteryDateObj = new Date(lotteryDate);
        lotteryDateObj.setDate(lotteryDateObj.getDate() - 1);
        const maxDate = lotteryDateObj.toISOString().split('T')[0];
        $('input[name="deadline_date"]').attr('max', maxDate);
        $('input[name="deadline_date"]').on('change', function() {
            const selectedDate = new Date($(this).val());
            const maxDateObj = new Date(maxDate);
            if (selectedDate > maxDateObj) {
                alert('La fecha límite debe ser como máximo el día anterior al sorteo.');
                $(this).val('');
            }
        });
    }
});

</script>

@endsection
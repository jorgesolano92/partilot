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
                                        <div class="alert {{ !empty($canEditConfig) ? 'alert-warning' : 'alert-info' }} mb-3" role="alert">
                                            @if(!empty($canEditConfig))
                                                <strong>Editable:</strong> todavía no se ha empezado a diseñar la participación. Puede corregir la configuración del set o borrarlo desde el listado.
                                            @else
                                                <strong>Nota:</strong> El diseño ya se ha empezado. Solo se puede modificar la <strong>fecha límite de cierre de venta</strong>. El resto de datos son solo consulta.
                                            @endif
                                        </div>
                                        <h4 class="mb-0 mt-1">Configuración del Set</h4>
                                        <small><i>{{ !empty($canEditConfig) ? 'Puede modificar los datos del set' : 'Solo la fecha límite es editable' }}</i></small>
                                        <br>
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Nombre del Set</label>
                                                    <div class="input-group input-group-merge group-form">
                                                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                            <img src="{{url('assets/form-groups/admin/19.svg')}}" alt="">
                                                        </div>
                                                        <input class="form-control" type="text" name="set_name" value="{{ old('set_name', $set->set_name) }}" style="border-radius: 0 30px 30px 0;" {{ !empty($canEditConfig) ? 'required' : 'readonly' }}>
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
                                                        <input class="form-control" type="number" step="0.01" name="played_amount" id="played_amount" value="{{ old('played_amount', $set->played_amount) }}" style="border-radius: 0 30px 30px 0;" {{ !empty($canEditConfig) ? '' : 'readonly' }}>
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
                                                        <input class="form-control" type="number" step="0.01" name="donation_amount" id="donation_amount" value="{{ old('donation_amount', $set->donation_amount) }}" style="border-radius: 0 30px 30px 0;" {{ !empty($canEditConfig) ? '' : 'readonly' }}>
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
                                                        <input class="form-control" type="number" step="0.01" name="total_participation_amount" id="total_participation_amount" value="{{ old('total_participation_amount', $set->total_participation_amount) }}" style="border-radius: 0 30px 30px 0;" {{ !empty($canEditConfig) ? '' : 'readonly' }}>
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
                                                        <input class="form-control" type="number" name="total_participations" id="total_participations" value="{{ old('total_participations', $set->getAttributes()['total_participations'] ?? $set->total_participations) }}" style="border-radius: 0 30px 30px 0;" {{ !empty($canEditConfig) ? 'required min=1' : 'readonly' }}>
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
                                                        <input class="form-control" type="number" step="0.01" name="total_amount" id="total_amount" value="{{ old('total_amount', $set->getAttributes()['total_amount'] ?? $set->total_amount) }}" style="border-radius: 0 30px 30px 0;" {{ !empty($canEditConfig) ? 'required' : 'readonly' }}>
                                                    </div>
                                                    @if(!empty($canEditConfig))
                                                        <small class="text-muted">Disponible en reserva: {{ number_format($availableAmount, 2, ',', '.') }} €</small>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Fecha Límite de cierre de venta</label>
                                                    <div class="input-group input-group-merge group-form">
                                                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                            <img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
                                                        </div>
                                                        <input class="form-control" name="deadline_date" type="date" value="{{ old('deadline_date', $set->deadline_date ? \Carbon\Carbon::parse($set->deadline_date)->format('Y-m-d') : '') }}" style="border-radius: 0 30px 30px 0;">
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
                                                        <input class="form-check-input" type="radio" name="participation_type" id="participation_type_physical" value="physical" {{ (old('participation_type', $set->physical_participations > 0 ? 'physical' : 'digital') === 'physical') ? 'checked' : '' }} {{ !empty($canEditConfig) ? '' : 'disabled' }}>
                                                        <label class="form-check-label" for="participation_type_physical">
                                                            <strong>Participaciones Físicas</strong>
                                                        </label>
                                                    </div>
                                                    
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" type="radio" name="participation_type" id="participation_type_digital" value="digital" {{ (old('participation_type', $set->digital_participations > 0 ? 'digital' : 'physical') === 'digital') ? 'checked' : '' }} {{ !empty($canEditConfig) ? '' : 'disabled' }}>
                                                        <label class="form-check-label" for="participation_type_digital">
                                                            <strong>Participaciones Digitales</strong>
                                                        </label>
                                                    </div>
                                                    
                                                    <input type="hidden" name="physical_participations" id="physical_participations" value="{{ old('physical_participations', $set->physical_participations) }}">
                                                    <input type="hidden" name="digital_participations" id="digital_participations" value="{{ old('digital_participations', $set->digital_participations) }}">
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

    @if(!empty($canEditConfig))
    function syncParticipationType() {
        var total = parseInt($('#total_participations').val(), 10) || 0;
        var type = $('input[name="participation_type"]:checked').val() || 'physical';
        if (type === 'digital') {
            $('#physical_participations').val(0);
            $('#digital_participations').val(total);
        } else {
            $('#physical_participations').val(total);
            $('#digital_participations').val(0);
        }
    }
    function recalcTotals() {
        var played = parseFloat($('#played_amount').val()) || 0;
        var donation = parseFloat($('#donation_amount').val()) || 0;
        var totalPart = Math.round((played + donation) * 100) / 100;
        $('#total_participation_amount').val(totalPart.toFixed(2));
        var qty = parseInt($('#total_participations').val(), 10) || 0;
        var totalAmount = Math.round((played * qty) * 100) / 100;
        $('#total_amount').val(totalAmount.toFixed(2));
        syncParticipationType();
    }
    $('#played_amount, #donation_amount, #total_participations').on('input change', recalcTotals);
    $('input[name="participation_type"]').on('change', syncParticipationType);
    syncParticipationType();
    @endif
});

</script>

@endsection
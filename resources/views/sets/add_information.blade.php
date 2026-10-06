@extends('layouts.layout')

@section('title','Set Participaciones')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
            	<div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Set Participaciones</a></li>
                        <li class="breadcrumb-item active">Añadir</li>
                    </ol>
                </div>
                <h4 class="page-title">Set Participaciones</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

            		<h4 class="header-title">

                    	Configurar Set

                    </h4>

                    <br>

                    <div class="row">
                    	
                    	<div class="col-md-3" style="position: relative;">
                    		<div class="form-card bs mb-3">

                    			<div class="form-wizard-element">
                    				
                    				<span>
                    					1
                    				</span>

                    				<img src="{{url('assets/entidad.svg')}}" alt="" width="26px">

                    				<label>
                    					Selec. Entidad
                    				</label>

                    			</div>

                    			<div class="form-wizard-element">
                    				
                    				<span>
                    					2
                    				</span>

                    				<img src="{{url('icons_/reservas.svg')}}" alt="" width="18px" style="margin: 0 12px;">

                    				<label>
                    					Selec. Reserva
                    				</label>

                    			</div>

                    			<div class="form-wizard-element active">
                    				
                    				<span>
                    					3
                    				</span>

                    				<img src="{{url('icons_/sets.svg')}}" alt="" width="26px">

                    				<label>
                    					Config. Set
                    				</label>

                    			</div>
                    			
                    		</div>

                    		<div class="form-card">
                    			
                    			<div class="row">
                					<div class="col-4">
                						
	                    				<div class="photo-preview-3 logo-round" @if($entity->image ?? null) style="background-image: url('{{ asset('uploads/' . $entity->image) }}');" @endif>
	                    					@if(!($entity->image ?? null))
	                    						<i class="ri-account-circle-fill"></i>
	                    					@endif
	                    				</div>
	                    				
	                    				<div style="clear: both;"></div>
                					</div>

                					<div class="col-8 text-center mt-2">

                						<h3 class="mt-2 mb-0">{{$entity->name ?? 'Entidad'}}</h3>

                						<i style="position: relative; top: 3px; font-size: 16px; color: #333" class="ri-computer-line"></i> {{$entity->province ?? 'Sin provincia'}}
                						
                					</div>
                				</div>

                    		</div>

                    		<a href="{{url('sets/add')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9">
                    		<div class="form-card bs">
                    			<form action="{{url('sets/store-information')}}" method="POST">
                    				@csrf
                    			<div style="min-height: 658px;">
                    				
	                    			<h4 class="mb-0 mt-1">
	                    				Reserva en la que generar el Set
	                    			</h4>
	                    			<small><i>Revisa que los datos de la reserva sean los correctos</i></small>

	                    			<br>

	                    			<div class="row show-content">
	                                    
	                                    <div class="col-3 offset-2">
	                                        <div class="form-group mt-2 mb-3">
	                                            <label class="label-control">Número del Sorteo</label>

	                                            <div class="input-group input-group-merge group-form">

	                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
	                                                    <img src="{{url('assets/form-groups/admin/16.svg')}}" alt="">
	                                                </div>

	                                                <input class="form-control" readonly type="text" value="{{$reserve->lottery ? $reserve->lottery->name : 'Sin número'}}" placeholder="46/25" style="border-radius: 0 30px 30px 0;">
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

	                                                <input class="form-control" readonly type="text" value="{{$reserve->lottery ? $reserve->lottery->description : 'Sin nombre'}}" placeholder="Nombre del Sorteo" style="border-radius: 0 30px 30px 0;">
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

	                                                <input class="form-control" readonly type="text" value="{{$reserve->lottery ? $reserve->lottery->draw_date->format('d-m-Y') : 'Sin fecha'}}" style="border-radius: 0 30px 30px 0;">
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

	                                                <input class="form-control" readonly type="text" value="{{implode(' - ', $reserve->reservation_numbers ?? [])}}" style="border-radius: 0 30px 30px 0;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-2">
	                                        <div class="form-group mt-2 mb-3">
	                                            <label class="label-control">Décimos TOTALES</label>

	                                            <div class="input-group input-group-merge group-form">

	                                                <input class="form-control" readonly type="number" value="{{$reserve->reservation_tickets}}" style="border-radius: 30px;">
	                                            </div>
	                                        </div>
	                                    </div>

	                                    <div class="col-2">
	                                        <div class="form-group mt-2 mb-3">
	                                            <label class="label-control">Importe por número</label>

	                                            <div class="input-group input-group-merge group-form">

	                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
	                                                    <img src="{{url('assets/form-groups/admin/15.svg')}}" alt="">
	                                                </div>

	                                                <input class="form-control" readonly type="number" step="0.01" value="{{$reserve->reservation_amount}}" style="border-radius: 0 30px 30px 0;">
	                                            </div>
	                                            <small class="text-muted"><i>Total reserva: {{ number_format($reserve->total_amount ?? ($reserve->reservation_amount * count($reserve->reservation_numbers ?? [])), 2) }} €</i></small>
	                                        </div>
	                                    </div>
	                                </div>

	                    			<h4 class="mb-0 mt-1">
	                    				Configuración del Set
	                    			</h4>
	                    			<small><i>Todos los campos son obligatorios</i></small>

	                    			<br>

	                    			<div class="row">
	                    				<div class="col-6">
	                                        <div class="form-group mt-2 mb-3">
	                                            <label class="label-control">Nombre del Set</label>

	                                            <div class="input-group input-group-merge group-form">

	                                            	<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
	                                                    <img src="{{url('assets/form-groups/admin/19.svg')}}" alt="">
	                                                </div>

	                                                <input class="form-control" name="set_name" type="text" placeholder="Set de ejemplo" style="border-radius: 0 30px 30px 0;" required value="{{ old('set_name') }}">
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

	                                                <input class="form-control decimal-input" id="played_amount" name="played_amount" type="text" inputmode="decimal" autocomplete="off" placeholder="6,00€" style="border-radius: 0 30px 30px 0;" required value="{{ old('played_amount') }}">
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

	                                                <input class="form-control decimal-input" id="donation_amount" name="donation_amount" type="text" inputmode="decimal" autocomplete="off" placeholder="0,50€" style="border-radius: 0 30px 30px 0;" value="{{ old('donation_amount') }}">
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

	                                                <input class="form-control" id="total_participation_amount" name="total_participation_amount" type="number" step="0.01" placeholder="6.00€" style="border-radius: 0 30px 30px 0;" readonly value="{{ old('total_participation_amount') }}">
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

	                                                <input class="form-control" id="total_participations" name="total_participations" type="number" min="1" step="1" placeholder="0" style="border-radius: 0 30px 30px 0;" required value="{{ old('total_participations') }}">
	                                            </div>
	                                            <small class="text-muted d-block" id="total_participations_hint"></small>
	                                        </div>
	                                    </div>

	                                    <div class="col-3">
	                                        <div class="form-group mt-2 mb-3">
	                                            <label class="label-control">Importe TOTAL</label>

	                                            <div class="input-group input-group-merge group-form">

	                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
	                                                    <img src="{{url('assets/form-groups/admin/15.svg')}}" alt="">
	                                                </div>

<input class="form-control" id="total_amount" name="total_amount" type="number" step="0.01" placeholder="6.00€" style="border-radius: 0 30px 30px 0;" readonly required max="{{ $availableAmount ?? 0 }}" value="{{ old('total_amount') }}">
                                            </div>
	                                            <small class="text-muted"><i>Máximo disponible para esta reserva: {{ number_format($availableAmount ?? 0, 2) }} €</i></small>
	                                        </div>
	                                    </div>

	                                    <div class="col-3">
	                                        <div class="form-group mt-2 mb-3">
	                                            <label class="label-control">Fecha Límite</label>

	                                            <div class="input-group input-group-merge group-form">

	                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
	                                                    <img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
	                                                </div>

	                                                <input class="form-control" name="deadline_date" type="date" value="{{ old('deadline_date') }}" style="border-radius: 0 30px 30px 0;">
	                                            </div>
	                                        </div>
	                                    </div>
	                    			</div>

	                    			<h4 class="mb-0 mt-1">
	                    				Tipo Participaciones
	                    			</h4>
	                    			<small><i>Elige el tipo de participaciones a realizar</i></small>

	                    			<br>

	                    			<div class="row">
	                    				<div class="col-6">
	                                        <div class="form-group mt-2 mb-3">
	                                            <label class="label-control">Tipo de Participación</label>

	                                            <div class="form-check mt-3">
	                                                <input class="form-check-input" type="radio" name="participation_type" id="participation_type_physical" value="physical" checked>
	                                                <label class="form-check-label" for="participation_type_physical">
	                                                    <strong>Participaciones Físicas</strong>
	                                                </label>
	                                            </div>
	                                            
	                                            <div class="form-check mt-2">
	                                                <input class="form-check-input" type="radio" name="participation_type" id="participation_type_digital" value="digital">
	                                                <label class="form-check-label" for="participation_type_digital">
	                                                    <strong>Participaciones Digitales</strong>
	                                                </label>
	                                            </div>
	                                        </div>
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

                    
                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div>
    <!-- end row-->

</div> <!-- container -->

@endsection

@section('scripts')

<script>

// Acepta "0,50", "0.50" y "1.234,56"; devuelve NaN si el formato es ambiguo o inválido.
function parseDecimalInput(raw) {
    var s = String(raw == null ? '' : raw).trim().replace(/\s|€/g, '');
    if (s === '') return NaN;
    if (s.indexOf(',') !== -1 && s.indexOf('.') !== -1) {
        s = s.replace(/\./g, '').replace(',', '.');
    } else {
        s = s.replace(',', '.');
    }
    return /^\d+(\.\d{1,2})?$/.test(s) ? parseFloat(s) : NaN;
}

function decimalFieldValue(selector) {
    var v = parseDecimalInput($(selector).val());
    return isNaN(v) ? 0 : v;
}

const SET_AVAILABLE_AMOUNT = {{ json_encode((float) ($availableAmount ?? 0)) }};
const SET_MAX_PLAYED = {{ json_encode((float) ($reserve->reservation_amount ?? 0)) }};

function playedPerParticipation() {
    const reservedNumbers = @json($reserve->reservation_numbers ?? []);
    const numbersCount = reservedNumbers.length;
    const playedAmount = decimalFieldValue('#played_amount');
    return numbersCount <= 1 ? playedAmount : (playedAmount * numbersCount);
}

// Máximo de participaciones que caben en el saldo libre de la reserva con el importe jugado actual.
function updateMaxParticipations() {
    const perParticipation = playedPerParticipation();
    const $total = $('#total_participations');
    const $hint = $('#total_participations_hint');
    if (perParticipation <= 0) {
        $total.removeAttr('max');
        $hint.removeClass('text-danger').addClass('text-muted').text('');
        return;
    }
    const maxParticipations = Math.floor((SET_AVAILABLE_AMOUNT + 0.0001) / perParticipation);
    $total.attr('max', maxParticipations);
    const current = parseInt($total.val(), 10) || 0;
    if (current > maxParticipations) {
        $hint.removeClass('text-muted').addClass('text-danger')
            .text('Máximo ' + maxParticipations + ' participaciones con el saldo disponible de la reserva.');
    } else {
        $hint.removeClass('text-danger').addClass('text-muted')
            .text('Máximo: ' + maxParticipations + ' participaciones.');
    }
}

// Función para calcular el Importe Total Participación
function calculateTotalParticipationAmount() {
    const playedAmount = decimalFieldValue('#played_amount');
    const donationAmount = decimalFieldValue('#donation_amount');
    
    // Obtener la cantidad de números reservados
    const reservedNumbers = @json($reserve->reservation_numbers ?? []);
    const numbersCount = reservedNumbers.length;
    
    let totalParticipationAmount;
    
    if (numbersCount <= 1) {
        // Si hay 1 número o menos: Importe Jugado + Importe Donativo
        totalParticipationAmount = playedAmount + donationAmount;
    } else {
        // Si hay 2 o más números: (Importe Jugado × Cantidad de números) + Importe Donativo
        totalParticipationAmount = (playedAmount * numbersCount) + donationAmount;
    }
    
    $('#total_participation_amount').val(totalParticipationAmount.toFixed(2));
}

// Función para calcular el Importe Total
function calculateTotalAmount() {
    const totalParticipations = parseInt($('#total_participations').val()) || 0;
    const totalAmount = totalParticipations * playedPerParticipation();

    $('#total_amount').val(totalAmount.toFixed(2));
    updateMaxParticipations();
}

// Función para calcular participaciones digitales cuando cambian las físicas
function calculateDigitalParticipations() {
    const totalParticipations = parseInt($('#total_participations').val()) || 0;
    const physicalParticipations = parseInt($('#physical_participations').val()) || 0;
    
    if (physicalParticipations > totalParticipations) {
        $('#physical_participations').val(totalParticipations);
        $('#digital_participations').val(0);
    } else {
        const digitalParticipations = totalParticipations - physicalParticipations;
        $('#digital_participations').val(digitalParticipations);
    }
}

// Función para calcular participaciones físicas cuando cambian las digitales
function calculatePhysicalParticipations() {
    const totalParticipations = parseInt($('#total_participations').val()) || 0;
    const digitalParticipations = parseInt($('#digital_participations').val()) || 0;
    
    if (digitalParticipations > totalParticipations) {
        $('#digital_participations').val(totalParticipations);
        $('#physical_participations').val(0);
    } else {
        const physicalParticipations = totalParticipations - digitalParticipations;
        $('#physical_participations').val(physicalParticipations);
    }
}

// Event listeners para los cálculos automáticos
$(document).ready(function() {
    calculateTotalParticipationAmount();
    calculateTotalAmount();

    // Calcular Importe Total Participación cuando cambian Importe Jugado o Importe Donativo
    $('#played_amount, #donation_amount').on('input', function() {
        calculateTotalParticipationAmount();
        calculateTotalAmount();
    });
    
    // Calcular Importe Total cuando cambian Participaciones Totales o Importe Jugado
    $('#total_participations, #played_amount').on('input', function() {
        calculateTotalAmount();
        calculateDigitalParticipations();
        calculatePhysicalParticipations();
    });
    
    // Calcular participaciones digitales cuando cambian las físicas
    $('#physical_participations').on('input', function() {
        calculateDigitalParticipations();
    });
    
    // Calcular participaciones físicas cuando cambian las digitales
    $('#digital_participations').on('input', function() {
        calculatePhysicalParticipations();
    });
    
    // Validación adicional para Participaciones Totales
    $('#total_participations').on('input', function() {
        const totalParticipations = parseInt($(this).val()) || 0;
        const physicalParticipations = parseInt($('#physical_participations').val()) || 0;
        const digitalParticipations = parseInt($('#digital_participations').val()) || 0;
        
        // Si las participaciones físicas o digitales superan el total, ajustarlas
        if (physicalParticipations > totalParticipations) {
            $('#physical_participations').val(totalParticipations);
            $('#digital_participations').val(0);
        }
        if (digitalParticipations > totalParticipations) {
            $('#digital_participations').val(totalParticipations);
            $('#physical_participations').val(0);
        }
    });
    
    // Validación de Importe Jugado (Número) antes de enviar
    $('form').on('submit', function(e) {
        var playedParsed = parseDecimalInput($('#played_amount').val());
        if (isNaN(playedParsed) || playedParsed <= 0) {
            alert('Indica un Importe Jugado válido (por ejemplo 2,00).');
            e.preventDefault();
            return false;
        }
        var donationRaw = ($('#donation_amount').val() || '').trim();
        var donationParsed = donationRaw === '' ? 0 : parseDecimalInput(donationRaw);
        if (isNaN(donationParsed)) {
            alert('El Importe Donativo no tiene un formato válido (por ejemplo 0,50).');
            e.preventDefault();
            return false;
        }
        var maxPlayed = SET_MAX_PLAYED;
        var playedAmount = playedParsed;
        if (playedAmount > maxPlayed) {
            alert('El Importe Jugado (Número) no puede ser mayor al importe por número de la reserva (' + maxPlayed.toFixed(2) + ' €)');
            e.preventDefault();
            return false;
        }
        // Enviar siempre con punto decimal para que el servidor reciba el mismo valor que se ve en pantalla.
        $('#played_amount').val(playedParsed.toFixed(2));
        $('#donation_amount').val(donationParsed.toFixed(2));
        // Validación de importe total existente
        var maxAmount = SET_AVAILABLE_AMOUNT;
        var totalAmount = parseFloat($('#total_amount').val()) || 0;
        if (totalAmount > maxAmount) {
            alert('El importe total supera el disponible para esta reserva (máx: ' + maxAmount.toFixed(2) + ' €)');
            e.preventDefault();
            return false;
        }
        
        // Asignar valores según el tipo de participación seleccionado
        var participationType = $('input[name="participation_type"]:checked').val();
        var totalParticipations = parseInt($('#total_participations').val()) || 0;
        
        if (participationType === 'physical') {
            $('<input>').attr({
                type: 'hidden',
                name: 'physical_participations',
                value: totalParticipations
            }).appendTo('form');
            $('<input>').attr({
                type: 'hidden',
                name: 'digital_participations',
                value: 0
            }).appendTo('form');
        } else {
            $('<input>').attr({
                type: 'hidden',
                name: 'physical_participations',
                value: 0
            }).appendTo('form');
            $('<input>').attr({
                type: 'hidden',
                name: 'digital_participations',
                value: totalParticipations
            }).appendTo('form');
        }
    });
    
    // Fecha límite: máximo el día anterior al sorteo (23:59)
    const lotteryDate = @json($reserve->lottery->draw_date ?? null);
    if (lotteryDate) {
        const lotteryDateObj = new Date(lotteryDate);
        lotteryDateObj.setDate(lotteryDateObj.getDate() - 1);
        const maxDate = lotteryDateObj.toISOString().split('T')[0];
        $('input[name="deadline_date"]').attr('max', maxDate);
        
        $('input[name="deadline_date"]').on('change', function() {
            const selectedDate = new Date($(this).val());
            const maxDateObj = new Date(maxDate);
            if (selectedDate > maxDateObj) {
                alert('La fecha límite debe ser como máximo el día anterior al sorteo (23:59).');
                $(this).val('');
            }
        });
    }
    
});

</script>

@endsection
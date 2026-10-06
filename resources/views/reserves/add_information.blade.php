@extends('layouts.layout')

@section('title','Añadir Reserva')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{url('reserves')}}">Reservas</a></li>
                        <li class="breadcrumb-item active">Añadir</li>
                    </ol>
                </div>
                <h4 class="page-title">Añadir Reserva</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">

                        Datos de la Reserva

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
                                        Selec. Entidad
                                    </label>

                                </div>

                                <div class="form-wizard-element">
                                    
                                    <span>
                                        2
                                    </span>

                                    <img src="{{url('icons_/sorteos.svg')}}" alt="">

                                    <label>
                                        Selec. Sorteo
                                    </label>

                                </div>

                                <div class="form-wizard-element active">
                                    
                                    <span>
                                        3
                                    </span>

                                    <img src="{{url('icons_/reservas.svg')}}" alt="">

                                    <label>
                                        Datos Reserva
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

                                        <h3 class="mt-2 mb-0">{{ $entity->name ?? 'Entidad' }}</h3>

                                        <i style="position: relative; top: 3px; font-size: 16px; color: #333" class="ri-computer-line"></i> {{ $entity->province ?? 'Sin provincia' }}
                                        
                                    </div>
                                </div>

                            </div>

                            <form action="{{url('reserves/store-entity')}}" method="POST" style="position: absolute; bottom: 16px;">
                                @csrf
                                <input type="hidden" name="entity_id" value="{{ $entity->id }}">
                                <button type="submit" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder;" class="btn btn-md btn-light mt-2">
                                    <i style="top: 18px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span>
                                </button>
                            </form>
                        </div>
                        <div class="col-md-9">
                            <div class="form-card bs" style="min-height: 0px;">
                                <h4 class="mb-0 mt-1">
                                    Datos del Sorteo
                                </h4>
                                <small><i>Comprueba que el sorteo es el correcto</i></small>

                                <br>

                                <div class="row show-content">
                                    
                                    <div class="col-3 offset-2">
                                        <div class="form-group mt-2 mb-3">
                                            <label class="label-control">Número del Sorteo</label>

                                            <div class="input-group input-group-merge group-form">

                                                <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                    <img src="{{url('assets/form-groups/admin/16.svg')}}" alt="">
                                                </div>

                                                <input class="form-control" readonly type="text" value="{{$lottery->name ?? ''}}" placeholder="Número" style="border-radius: 0 30px 30px 0;">
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

                                                <input class="form-control" readonly type="text" value="{{$lottery->description ?? ''}}" placeholder="Nombre del Sorteo" style="border-radius: 0 30px 30px 0;">
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

                                                <input class="form-control" readonly type="text" value="{{$lottery->lotteryType->name ?? 'Sin tipo'}}" placeholder="Tipo de Sorteo" style="border-radius: 0 30px 30px 0;">
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

                                                <input class="form-control" readonly type="number" value="{{$lottery->ticket_price ?? 0}}" step="0.01" placeholder="0.00€" style="border-radius: 0 30px 30px 0;">
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

                                                <input class="form-control" readonly type="text" value="{{$lottery->draw_date ? \Carbon\Carbon::parse($lottery->draw_date)->format('d/m/Y') : 'No definida'}}" placeholder="Fecha" style="border-radius: 0 30px 30px 0;">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <h4 class="mb-0 mt-1">
                                    Configuración de la Reserva
                                </h4>
                                <small><i>Introduce los datos de la reserva</i></small>

                                <br>
                                <br>

                                <div style="min-height: 256px;">

                                    <form action="{{url('reserves/store-information')}}" method="POST">
                                        @csrf



                                        <div class="row">

                                            <div class="col-1">

                                                <button type="button" style="border-radius: 30px; width: 46px; height: 46px; background-color: #333; color: #333; padding: 8px; font-weight: bolder;" class="btn btn-md btn-light mt-3 add-number">
                                                <i style="top: 6px; font-size: 18px; color: #fff" class="ri-add-line"></i></button>
                                                
                                            </div>

                                            <div class="col-11">
                                                <div class="row" id="numbers">
                                                    @php
                                                        $oldNumbers = old('reservation_numbers', []);
                                                    @endphp
                                                    @if(count($oldNumbers) > 0)
                                                        @foreach($oldNumbers as $num)
                                                        <div class="col-3 number-input-group">
                                                            <div class="form-group mt-2 mb-3">
                                                                <label class="label-control">Número</label>
                                                                <div class="input-group input-group-merge group-form">
                                                                    <input class="form-control reservation-number" type="text" name="reservation_numbers[]" inputmode="numeric" pattern="[0-9]{1,5}" maxlength="5" title="Hasta 5 dígitos, solo números" placeholder="Número" style="border-radius: 30px 0 0 30px;" value="{{ $num }}" required>
                                                                    <div class="input-group-text remove-number" style="border-radius: 0 30px 30px 0; cursor:pointer;">
                                                                        <i class="ri-close-line"></i>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        @endforeach
                                                    @else
                                                        <div class="col-3 number-input-group">
                                                            <div class="form-group mt-2 mb-3">
                                                                <label class="label-control">Número</label>
                                                                <div class="input-group input-group-merge group-form">
                                                                    <input class="form-control reservation-number" type="text" name="reservation_numbers[]" inputmode="numeric" pattern="[0-9]{1,5}" maxlength="5" title="Hasta 5 dígitos, solo números" placeholder="Número" style="border-radius: 30px 0 0 30px;" required>
                                                                    <div class="input-group-text remove-number" style="border-radius: 0 30px 30px 0; cursor:pointer;">
                                                                        <i class="ri-close-line"></i>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>  
                                                @if($errors->has('reservation_numbers.*'))
                                                    <div class="text-danger small">{{ $errors->first('reservation_numbers.*') }}</div>
                                                @endif

                                            </div>

                                        </div>

                                        <div class="row">

                                            <div class="col-3">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Importe a Reservar</label>

                                                    <div class="input-group input-group-merge group-form">

                                                        <input class="form-control @error('reservation_amount') is-invalid @enderror" id="reservation_amount" type="number" step="0.01" name="reservation_amount" value="{{old('reservation_amount')}}" placeholder="0.00" style="border-radius: 30px;" required>
                                                    </div>
                                                    <small class="text-muted"><i>Por cada número seleccionado</i></small>
                                                    @error('reservation_amount')
                                                        <div class="text-danger small">{{$message}}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-3">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Cantidad de décimos</label>

                                                    <div class="input-group input-group-merge group-form">

                                                        <input class="form-control @error('reservation_tickets') is-invalid @enderror" id="reservation_tickets" type="number" name="reservation_tickets" value="{{old('reservation_tickets')}}" placeholder="0" style="border-radius: 30px;" required>
                                                    </div>
                                                    @error('reservation_tickets')
                                                        <div class="text-danger small">{{$message}}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                            <div class="col-3 offset-3">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Total</label>

                                                    <div class="input-group input-group-merge group-form">

                                                        <input class="form-control" id="total_amount" type="number" step="0.01" placeholder="0.00" style="border-radius: 30px;" readonly>
                                                    </div>
                                                    @error('total')
                                                        <div class="text-danger small">{{$message}}</div>
                                                    @enderror
                                                </div>
                                            </div>

                                        </div>



                                        <div class="row">

                                            <div class="col-12 text-end">
                                                <button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative; top: calc(100% - 51px);" class="btn btn-md btn-light mt-2">Guardar
                                                    <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i></button>
                                            </div>

                                        </div>

                                    </form>

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

@section('scripts')

<script>

function addRemoveListeners() {
    document.querySelectorAll('.remove-number').forEach(function(btn) {
        btn.onclick = function() {
            const numbersDiv = document.getElementById('numbers');
            if(numbersDiv.querySelectorAll('.number-input-group').length > 1) {
                const col = btn.closest('.number-input-group');
                if(col) col.remove();
            }
        };
    });
}

$('.add-number').click(function (e) {
    e.preventDefault();
    const numbersDiv = document.getElementById('numbers');
    const newCol = document.createElement('div');
    newCol.className = 'col-3 number-input-group';
    newCol.innerHTML = `
        <div class=\"form-group mt-2 mb-3\">
            <label class=\"label-control\">Número</label>
            <div class=\"input-group input-group-merge group-form\">
                <input class=\"form-control reservation-number\" type=\"text\" name=\"reservation_numbers[]\" inputmode=\"numeric\" pattern=\"[0-9]{1,5}\" maxlength=\"5\" title=\"Hasta 5 dígitos, solo números\" placeholder=\"Número\" style=\"border-radius: 30px 0 0 30px;\" required>
                <div class=\"input-group-text remove-number\" style=\"border-radius: 0 30px 30px 0; cursor:pointer;\"><i class=\"ri-close-line\"></i></div>
            </div>
        </div>
    `;
    numbersDiv.appendChild(newCol);
    addRemoveListeners();
});

addRemoveListeners();

// Calcular total automáticamente
$('.reservation-number').on('input', function() {
    calculateTotal();
});

$(document).on('input', '.reservation-number', function() {
    const limpio = this.value.replace(/\D/g, '').slice(0, 5);
    if (this.value !== limpio) this.value = limpio;
    calculateTotal();
});

// Importe: escribir libremente; recálculo (décimos e importe correcto) solo al salir del campo (blur)
$('#reservation_amount').on('input', function() {
    calculateTotal();
});
$('#reservation_amount').on('blur', function() {
    calculateTicketsFromAmount();
    calculateTotal();
});

// Calcular importe cuando cambien los décimos
$('#reservation_tickets').on('input', function() {
    calculateAmountFromTickets();
    calculateTotal();
});

// Función para calcular décimos basado en el importe (siempre redondear al alza y ajustar importe al múltiplo)
function calculateTicketsFromAmount() {
    const reservationAmount = parseFloat($('#reservation_amount').val()) || 0;
    const ticketPrice = {{$lottery->ticket_price ?? 0}};
    
    if (ticketPrice > 0 && reservationAmount > 0) {
        const tickets = Math.ceil(reservationAmount / ticketPrice); // Siempre al alza: no fracciones de décimo
        const amountRounded = tickets * ticketPrice; // Importe = múltiplo del precio
        $('#reservation_tickets').val(tickets);
        $('#reservation_amount').val(amountRounded.toFixed(2));
    }
}

// Función para calcular importe basado en los décimos
function calculateAmountFromTickets() {
    const tickets = parseInt($('#reservation_tickets').val()) || 0;
    const ticketPrice = {{$lottery->ticket_price ?? 0}};
    
    const amount = tickets * ticketPrice;
    $('#reservation_amount').val(amount.toFixed(2));
}

function calculateTotal() {
    const numbers = $('.reservation-number').map(function() {
        return $(this).val().trim();
    }).get().filter(val => val !== '');

    const reservationAmount = parseFloat($('#reservation_amount').val()) || 0;
    const totalNumbers = numbers.length;
    const totalAmount = totalNumbers * reservationAmount;

    // Actualizar campo total
    $('#total_amount').val(totalAmount.toFixed(2));
}

</script>

@endsection
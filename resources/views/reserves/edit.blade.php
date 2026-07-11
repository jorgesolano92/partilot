@extends('layouts.layout')

@section('title','Editar Reserva')

@section('content')
<!-- Start Content-->
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{url('reserves')}}">Reservas</a></li>
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </div>
                <h4 class="page-title">Editar Reserva</h4>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">Editar datos de la Reserva</h4>
                    <br>
                    <div class="row">
                        <div class="col-md-3" style="position: relative;">
                            <div class="form-card bs mb-3">
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
                            <a href="{{ route('reserves.index') }}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                        </div>
                        <div class="col-md-9">
                            <div class="form-card bs" style="min-height: 0px;">
                                <form action="{{ url('reserves/update/' . $reserve->id) }}" method="POST" id="reserve-edit-form">
                                    @csrf
                                    @method('PUT')
                                    <h4 class="mb-0 mt-1 d-flex align-items-center justify-content-between">
                                        <span>Datos del Sorteo</span>
                                    </h4>
                                    <small><i>El sorteo y los números no se pueden modificar</i></small>
                                    <br>
                                    <div class="row show-content">
                                        <div class="col-3 offset-2">
                                            <div class="form-group mt-2 mb-3">
                                                <label class="label-control">Número del Sorteo</label>
                                                <div class="input-group input-group-merge group-form">
                                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                        <img src="{{url('assets/form-groups/admin/16.svg')}}" alt="">
                                                    </div>
                                                    <input class="form-control" readonly type="text" value="{{$reserve->lottery->name ?? ''}}" style="border-radius: 0 30px 30px 0;">
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
                                                    <input class="form-control" readonly type="text" value="{{$reserve->lottery->description ?? ''}}" style="border-radius: 0 30px 30px 0;">
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
                                                    <input class="form-control" readonly type="text" value="{{$reserve->lottery->lotteryType->name ?? 'Sin tipo'}}" style="border-radius: 0 30px 30px 0;">
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
                                                    <input class="form-control" readonly type="number" value="{{$reserve->lottery->ticket_price ?? 0}}" step="0.01" style="border-radius: 0 30px 30px 0;">
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
                                                    <input class="form-control" readonly type="text" value="{{$reserve->lottery->draw_date ? \Carbon\Carbon::parse($reserve->lottery->draw_date)->format('d/m/Y') : 'No definida'}}" style="border-radius: 0 30px 30px 0;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <h4 class="mb-0 mt-1">Configuración de la Reserva</h4>
                                    <small><i>Solo puedes editar el importe y la fecha límite</i></small>
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
                                                                        <input class="form-control" type="text" value="{{ $num }}" readonly style="border-radius: 30px;">
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
                                                        <input class="form-control @error('reservation_amount') is-invalid @enderror" id="reservation_amount" type="number" step="0.01" name="reservation_amount" value="{{ old('reservation_amount', $reserve->reservation_amount) }}" style="border-radius: 30px;">
                                                    </div>
                                                    <small class="text-muted"><i>Por cada número seleccionado</i></small>
                                                    @error('reservation_amount')
                                                        <small class="text-danger d-block">{{ $message }}</small>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Cantidad de décimos</label>
                                                    <div class="input-group input-group-merge group-form">
                                                        <input class="form-control" id="reservation_tickets" type="number" name="reservation_tickets" value="{{ old('reservation_tickets', $reserve->reservation_tickets) }}" style="border-radius: 30px;">
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
                                                        <input class="form-control @error('expiration_date') is-invalid @enderror" id="expiration_date" type="date" name="expiration_date" value="{{ old('expiration_date', $reserve->expiration_date ? \Carbon\Carbon::parse($reserve->expiration_date)->format('Y-m-d') : '') }}" style="border-radius: 0 30px 30px 0;">
                                                    </div>
                                                    @error('expiration_date')
                                                        <small class="text-danger d-block">{{ $message }}</small>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Total</label>
                                                    <div class="input-group input-group-merge group-form">
                                                        <input class="form-control" id="total_amount" type="number" step="0.01" style="border-radius: 30px;" readonly>
                                                    </div>
                                                    <small class="text-muted d-block mt-1"><i>Total en sets: {{ number_format($setsUsedAmount ?? 0, 2, ',', '.') }} €</i></small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-12 text-end">
                                                <button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative; top: calc(100% - 51px);" class="btn btn-md btn-light mt-2">Guardar
                                                    <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i></button>
                                            </div>
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
    const ticketPrice = {{ (float) ($reserve->lottery->ticket_price ?? 0) }};
    const numbersCount = {{ count($reserve->reservation_numbers ?? []) }};
    const minTotalAmount = {{ (float) ($minTotalAmount ?? 0) }};
    const originalAmount = {{ (float) old('reservation_amount', $reserve->reservation_amount) }};
    const originalTickets = {{ (int) old('reservation_tickets', $reserve->reservation_tickets) }};

    function calculateTotal() {
        const reservationAmount = parseFloat(document.getElementById('reservation_amount').value) || 0;
        const total = numbersCount * reservationAmount;
        document.getElementById('total_amount').value = total.toFixed(2);
        return total;
    }

    function calculateTicketsFromAmount() {
        const amountInput = document.getElementById('reservation_amount');
        const reservationAmount = parseFloat(amountInput.value) || 0;

        if (ticketPrice > 0 && reservationAmount > 0) {
            const tickets = Math.ceil(reservationAmount / ticketPrice);
            const amountRounded = tickets * ticketPrice;
            document.getElementById('reservation_tickets').value = tickets;
            amountInput.value = amountRounded.toFixed(2);
        }
    }

    function calculateAmountFromTickets() {
        const tickets = parseInt(document.getElementById('reservation_tickets').value) || 0;
        document.getElementById('reservation_amount').value = (tickets * ticketPrice).toFixed(2);
    }

    function validateMinimumTotal(restoreOnFail) {
        const total = calculateTotal();
        if (minTotalAmount > 0 && total < minTotalAmount) {
            alert('La reserva mínima es ' + minTotalAmount.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €');
            if (restoreOnFail) {
                document.getElementById('reservation_amount').value = originalAmount.toFixed(2);
                document.getElementById('reservation_tickets').value = originalTickets;
                calculateTotal();
            }
            return false;
        }
        return true;
    }

    document.getElementById('reservation_amount').addEventListener('input', calculateTotal);
    document.getElementById('reservation_amount').addEventListener('blur', function() {
        calculateTicketsFromAmount();
        if (!validateMinimumTotal(true)) {
            return;
        }
        calculateTotal();
    });

    document.getElementById('reservation_tickets').addEventListener('input', function() {
        calculateAmountFromTickets();
        calculateTotal();
    });
    document.getElementById('reservation_tickets').addEventListener('blur', function() {
        calculateAmountFromTickets();
        validateMinimumTotal(true);
        calculateTotal();
    });

    document.getElementById('reserve-edit-form').addEventListener('submit', function(e) {
        calculateTicketsFromAmount();
        if (!validateMinimumTotal(false)) {
            e.preventDefault();
        }
    });

    calculateTotal();
</script>
@endsection

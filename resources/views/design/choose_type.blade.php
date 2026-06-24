@extends('layouts.layout')

@section('title', 'Diseño e Impresión')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('design.index') }}">Diseño e Impresión</a></li>
                        <li class="breadcrumb-item active">Elegir tipo</li>
                    </ol>
                </div>
                <h4 class="page-title">Diseño e Impresión</h4>
            </div>
        </div>
    </div>

    @php
        $reservationNumbers = ($set && $set->reserve && $set->reserve->reservation_numbers)
            ? (is_array($set->reserve->reservation_numbers) ? $set->reserve->reservation_numbers : [$set->reserve->reservation_numbers])
            : [];
        $numbersText = !empty($reservationNumbers) ? implode(' - ', array_filter($reservationNumbers)) : '—';
    @endphp

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

    <img width="26px" src="{{url('icons_/selec_sorteo.svg')}}" alt="">

    <label>
        Selec. Sorteo
    </label>

</div>

<div class="form-wizard-element">
    
    <span>
        3
    </span>

    <img src="{{url('assets/entidad.svg')}}" alt="">

    <label>
        Selec. Set
    </label>

</div>

<div class="form-wizard-element active">
    
    <span>
        4
    </span>

    <img src="{{url('assets/entidad.svg')}}" alt="">

    <label>
        Diseño Particip.
    </label>

</div>

</div>
            <div class="form-card bs text-center mb-3">
                <div class="photo-preview-3 logo-round mx-auto mb-2"
                    @if($entity->image ?? null)
                        style="background-image: url('{{ asset('uploads/' . $entity->image) }}'); background-size: cover; background-position: center;"
                    @endif>
                    @if(!($entity->image ?? null))
                        <i class="ri-account-circle-fill"></i>
                    @endif
                </div>
                <h4 class="mb-0 mt-1">{{ strtoupper($entity->name ?? 'ENTIDAD') }}</h4>
                <small>{{ $entity->province ?? '' }}</small>
            </div>
            <a href="{{ route('design.selectSet') }}" style="border-radius: 30px; width: 200px; background-color: #1f2530; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                <i style="top: 6px; left: 28%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i>
                <span style="display: block; margin-left: 16px;">Atrás</span>
            </a>
        </div>

        <div class="col-md-9">
            <div class="form-card bs" style="min-height: 658px;">
                <div class="card border mb-4">
                    <div class="card-body">
                        <h5 class="mb-1">Set seleccionado</h5>
                        <p class="text-muted small mb-3">Comprueba que los datos del set sean correctos</p>
                        @php
                            $participations = (int) ($set->total_participations ?? 0);
                            $playedPerParticipation = (float) ($set->played_amount ?? 0);
                            $donationPerParticipation = (float) ($set->donation_amount ?? 0);
                            $amountPerParticipation = (float) ($set->total_participation_amount ?? ($playedPerParticipation + $donationPerParticipation));
                            $lotteryTotal = $playedPerParticipation * $participations;
                            $donationTotal = $donationPerParticipation * $participations;
                            $grandTotal = $lotteryTotal + $donationTotal;
                        @endphp
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nombre Set</th>
                                        <th>N.Sorteo</th>
                                        <th>Número/s</th>
                                        <th>Importe Jugado (Número)</th>
                                        <th>Importe Donativo</th>
                                        <th>Importe por Participación</th>
                                        <th>Participaciones TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{ $set->set_name ?? ('Set #'.$set->id) }}</td>
                                        <td>{{ $lottery->name ?? '—' }}</td>
                                        <td>{{ $numbersText }}</td>
                                        <td>{{ number_format((float) ($set->played_amount ?? 0), 2, ',', '.') }}€</td>
                                        <td>{{ number_format((float) ($set->donation_amount ?? 0), 2, ',', '.') }}€</td>
                                        <td>{{ number_format($amountPerParticipation, 2, ',', '.') }}€</td>
                                        <td>{{ number_format((int) ($set->total_participations ?? 0), 0, ',', '.') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="row g-2 mt-2">
                            <div class="col-md-2"><div class="small text-muted">Participaciones físicas</div><div class="fw-semibold">{{ (int) ($set->physical_participations ?? 0) }}</div></div>
                            <div class="col-md-2"><div class="small text-muted">Participaciones digitales</div><div class="fw-semibold">{{ (int) ($set->digital_participations ?? 0) }}</div></div>
                            <div class="col-md-3"><div class="small text-muted">Importe lotería TOTAL</div><div class="fw-semibold">{{ number_format($lotteryTotal, 2, ',', '.') }}€</div></div>
                            <div class="col-md-2"><div class="small text-muted">Importe Donativo TOTAL</div><div class="fw-semibold">{{ number_format($donationTotal, 2, ',', '.') }}€</div></div>
                            <div class="col-md-3"><div class="small text-muted">Lotería + Donativo TOTAL</div><div class="fw-semibold">{{ number_format($grandTotal, 2, ',', '.') }}€</div></div>
                        </div>
                    </div>
                </div>

                <h4 class="mb-0 mt-1">Selecciona una opción</h4>
                <small><i>Selecciona la opción que mas se adapte</i></small>

                <div class="d-flex justify-content-center align-items-center gap-3 mt-4 mb-4">
                    <div class="mock-option-card active" data-mode="self">Diseño</div>
                    <div class="mock-option-card" data-mode="external">Diseño e Impresión Externo</div>
                    <div class="mock-option-card" data-mode="partilot">Diseño e Impresión PARTILOT</div>
                </div>

                <div id="self-suboptions" class="text-center mb-3">
                    <button type="button" class="btn btn-outline-dark btn-sm rounded-pill px-3 me-2 subopt active" data-submode="new">Diseño libre</button>
                    <button type="button" class="btn btn-outline-dark btn-sm rounded-pill px-3 subopt" data-submode="existing">Usar diseño existente</button>
                </div>

                @if(!empty($designLock['locked']))
                    <div class="alert alert-warning mb-3">
                        <strong>Diseño bloqueado.</strong> {{ $designLock['message'] ?? '' }}
                    </div>
                @endif

                <form id="choose-type-submit-form" method="POST" action="{{ route('design.format') }}">
                    @csrf
                    <input type="hidden" name="set_id" value="{{ $set->id }}">
                    <input type="hidden" id="choose-mode" value="self">
                    <input type="hidden" id="choose-submode" value="new">
                    <div class="text-end mt-5">
                        <button type="submit" id="choose-submit-btn" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light">
                            Seleccionar
                            <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var modeInput = document.getElementById('choose-mode');
    var submodeInput = document.getElementById('choose-submode');
    var form = document.getElementById('choose-type-submit-form');
    var optionCards = Array.from(document.querySelectorAll('.mock-option-card'));
    var subOptions = Array.from(document.querySelectorAll('.subopt'));
    var selfSuboptions = document.getElementById('self-suboptions');

    optionCards.forEach(function(card) {
        card.addEventListener('click', function() {
            optionCards.forEach(function(c) { c.classList.remove('active'); });
            card.classList.add('active');
            modeInput.value = card.getAttribute('data-mode');
            selfSuboptions.style.display = modeInput.value === 'self' ? 'block' : 'none';
        });
    });

    subOptions.forEach(function(btn) {
        btn.addEventListener('click', function() {
            subOptions.forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            submodeInput.value = btn.getAttribute('data-submode');
        });
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        @if(!empty($designLock['locked']))
            return;
        @endif
        var mode = modeInput.value;
        var submode = submodeInput.value;
        var setId = {{ (int) $set->id }};

        if (mode === 'self') {
            if (submode === 'existing') {
                window.location.href = "{{ route('design.listFormats', ['set_id' => '__SET_ID__']) }}".replace('__SET_ID__', setId);
                return;
            }
            var selfForm = document.createElement('form');
            selfForm.method = 'POST';
            selfForm.action = "{{ route('design.format') }}";
            selfForm.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                '<input type="hidden" name="set_id" value="' + setId + '">' +
                '<input type="hidden" name="new_design" value="1">';
            document.body.appendChild(selfForm);
            selfForm.submit();
            return;
        }

        var url = "{{ route('design.external.step1') }}?mode=" + (mode === 'partilot' ? 'partilot' : 'external');
        window.location.href = url;
    });
})();
</script>
<style>
.mock-option-card {
    width: 170px;
    min-height: 130px;
    border: 1px solid #d8d8d8;
    border-radius: 14px;
    background: #fff;
    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;
    padding: 14px;
    font-weight: 700;
    font-size: 17px;
    line-height: 1.2;
    cursor: pointer;
    box-shadow: 0 2px 0 rgba(0,0,0,0.06);
}
.mock-option-card.active {
    border-color: #1f2530;
    box-shadow: 0 0 0 2px rgba(31,37,48,0.15);
}
.subopt.active {
    background-color: #1f2530;
    color: #fff;
    border-color: #1f2530;
}
</style>
@endsection

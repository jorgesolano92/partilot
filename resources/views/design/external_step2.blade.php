@extends('layouts.layout')

@section('title', 'Diseño e Impresión - Invitación')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('design.index') }}">Diseño e Impresión</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('design.external.list') }}">Diseño externo</a></li>
                        <li class="breadcrumb-item active">Invitación</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ ($mode ?? 'external') === 'partilot' ? 'Diseño e impresión PARTILOT' : 'Diseño e impresión externo' }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @php($isPartilotMode = (($mode ?? 'external') === 'partilot'))
                    @if($isPartilotMode)
                        <div class="d-flex p-2 mb-3" style="align-items: center; justify-content: center;">
                            <div class="form-wizard-element" style="width: 220px;">
                                <span style="top: -4px; margin-right: 8px;">1</span>
                                <label>Indicaciones <br> / Archivos</label>
                            </div>
                            <div class="form-wizard-element active" style="width: 220px;">
                                <span style="top: -4px; margin-right: 8px;">2</span>
                                <label>{!! ($mode ?? 'external') === 'partilot' ? 'Resumen <br> y pago' : 'Invitación' !!}</label>
                            </div>
                            <div class="form-wizard-element" style="width: 220px;">
                                <span style="top: -4px; margin-right: 8px;">3</span>
                                <label>Pago Diseño <br> e Impresión</label>
                            </div>
                        </div>
                    @else
                        <div class="d-flex p-2 mb-3" style="align-items: center; justify-content: center;">
                            <div class="form-wizard-element" style="width: 220px;">
                                <span style="top: -4px; margin-right: 8px;">1</span>
                                <label>Indicaciones <br> / Archivos</label>
                            </div>
                            <div class="form-wizard-element active" style="width: 220px;">
                                <span style="top: -4px; margin-right: 8px;">2</span>
                                <label>Invitación</label>
                            </div>
                        </div>
                    @endif

                    <div class="form-card {{ $isPartilotMode ? 'show-content' : '' }} bs">
                        @if($isPartilotMode)
                            <h4 class="mb-0 mt-1">Resumen</h4>
                            <small><i>Comprueba que los datos sean correctos</i></small>
                        @else
                            <h4 class="mb-0 mt-1">Configurar salida</h4>
                            <small><i>Configura el formato de salida de las participaciones</i></small>
                        @endif

                        @if($isPartilotMode)
                            <form action="{{ route('design.external.acceptSummary') }}" method="POST" id="partilotSummaryForm" class="mt-4">
                                @csrf
                                <div class="">
                                    <div class="row g-3">
                                        <div class="col-lg-2 col-md-4 partilot-field">
                                            <div class="partilot-field-label">Número Set</div>
                                            <div class="partilot-field-value"><i class="ri-price-tag-3-line"></i>{{ $set->id }}</div>
                                        </div>
                                        <div class="col-lg-3 col-md-4 partilot-field">
                                            <div class="partilot-field-label">Nombre del Set</div>
                                            <div class="partilot-field-value"><i class="ri-price-tag-3-line"></i>{{ $set->set_name }}</div>
                                        </div>
                                        <div class="col-lg-2 col-md-4 partilot-field">
                                            <div class="partilot-field-label">Número de Sorteo</div>
                                            <div class="partilot-field-value"><i class="ri-calendar-event-line"></i>{{ $lottery->name ?? '—' }}</div>
                                        </div>
                                        <div class="col-lg-2 col-md-4 partilot-field">
                                            <div class="partilot-field-label">Fecha Sorteo</div>
                                            <div class="partilot-field-value"><i class="ri-calendar-line"></i>{{ optional($lottery->draw_date)->format('d/m/Y') ?? '—' }}</div>
                                        </div>
                                        <div class="col-lg-3 col-md-8 partilot-field">
                                            <div class="partilot-field-label">Número/s Jugado/s</div>
                                            <div class="partilot-field-value"><i class="ri-hashtag"></i>{{ is_array(optional($set->reserve)->reservation_numbers) ? implode(' - ', optional($set->reserve)->reservation_numbers) : (optional($set->reserve)->reservation_numbers ?? '—') }}</div>
                                        </div>
                                    </div>

                                    <div class="row g-3 mt-2">
                                        <div class="col-lg-2 col-md-4 partilot-field">
                                            <div class="partilot-field-label">Importe Jugado (por Número)</div>
                                            <div class="partilot-field-value"><i class="ri-money-euro-circle-line"></i>{{ number_format((float)($set->played_amount ?? 0), 2, ',', '.') }}€</div>
                                        </div>
                                        <div class="col-lg-2 col-md-4 partilot-field">
                                            <div class="partilot-field-label">Importe Donativo</div>
                                            <div class="partilot-field-value"><i class="ri-money-euro-circle-line"></i>{{ number_format((float)($set->donation_amount ?? 0), 2, ',', '.') }}€</div>
                                        </div>
                                        <div class="col-lg-2 col-md-4 partilot-field">
                                            <div class="partilot-field-label">Importe Total Participación</div>
                                            <div class="partilot-field-value"><i class="ri-money-euro-circle-line"></i>{{ number_format((float)($set->total_participation_amount ?? (($set->played_amount ?? 0) + ($set->donation_amount ?? 0))), 2, ',', '.') }}€</div>
                                        </div>
                                        <div class="col-lg-2 col-md-4 partilot-field">
                                            <div class="partilot-field-label">Cantidad Participaciones</div>
                                            <div class="partilot-field-value"><i class="ri-file-list-3-line"></i>{{ (int)($set->total_participations ?? 0) }}</div>
                                        </div>
                                        <div class="col-lg-2 col-md-4 partilot-field">
                                            <div class="partilot-field-label">Número Participaciones</div>
                                            <div class="partilot-field-value"><i class="ri-list-ordered-2"></i>1/{{ str_pad((string)($set->total_participations ?? 0), 5, '0', STR_PAD_LEFT) }}</div>
                                        </div>
                                        <div class="col-lg-2 col-md-4 partilot-field">
                                            <div class="partilot-field-label">Cantidad Talonarios</div>
                                            <div class="partilot-field-value"><i class="ri-booklet-line"></i>{{ $quote['books'] ?? 0 }}</div>
                                        </div>
                                    </div>

                                    <div class="row g-3 mt-4">
                                        <div class="col-lg-6">
                                            <label class="partilot-field-label mb-2">Imprenta (diseño e impresión)</label>
                                            <input type="hidden" name="print_configuration_id" value="{{ $selectedPrintShop->id }}">
                                            <div class="partilot-field-value border-0 pb-0">
                                                <i class="ri-printer-line"></i>
                                                <span id="quote-shop-name">{{ $selectedPrintShop->displayName() }}</span>
                                            </div>
                                            <div class="form-text small">La imprenta por defecto elaborará el diseño e imprimirá el pedido.</div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div id="external-stripe-hint" class="alert alert-warning small py-2 mb-0 d-none">
                                                Stripe no está configurado para esta imprenta. Revisa Ajustes → Imprenta.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end align-items-end gap-2 flex-wrap" style="margin-top: 48px;">
                                        <div class="partilot-meta-box">
                                            <div><b>Participaciones:</b> <span id="quote-participations-count">{{ (int)($set->total_participations ?? 0) }}</span></div>
                                            <div><b>Tacos:</b> <span id="quote-books-count">{{ $quote['books'] ?? 0 }}</span></div>
                                            <div><b>Traseras:</b> {{ ($invitation->back_mode ?? 'bw') === 'color' ? 'Color' : 'B/N' }}</div>
                                            <div class="mt-1"><b>Imprenta:</b> <span id="quote-shop-name-inline">{{ $quote['print_configuration_name'] ?? ($selectedPrintShop->displayName() ?? '') }}</span></div>
                                        </div>
                                        <div class="partilot-total-box">
                                            <span class="partilot-total-label">IMPORTE TOTAL:</span>
                                            <span class="partilot-total-value" id="quote-total-display">{{ number_format(($quote['total'] ?? 0), 2, ',', '.') }}€</span>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        @else
                            <div class="row justify-content-center mt-4">
                                <div class="col-md-4" style="margin: 80px 0">
                                    <div class="card border shadow-sm" style="border-radius: 12px;">
                                        <div class="card-body p-2 pt-3 invite-card-compact">
                                            <h5 class="card-title mb-4 invite-title" color="#000">¡Invitar Diseño!</h5>
                                            <form action="{{ route('design.external.sendInvitation') }}" method="POST" id="inviteForm">
                                                @csrf
                                                <div class="mb-3">
                                                    <div class="input-group invite-input-group">
                                                        <span class="input-group-text invite-input-icon"><i class="ri-mail-line"></i></span>
                                                        <input type="email" name="email" class="form-control invite-input" placeholder="email@example.com" value="{{ old('email', $invitation->email ?? '') }}" required>
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn w-100 rounded-pill text-dark fw-semibold invite-submit-btn">Invitar</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>

                    @if($isPartilotMode)
                        <div class="d-flex justify-content-between align-items-end mt-2">
                            <a href="{{ route('design.external.step1', ['mode' => ($mode ?? 'external')]) }}" class="btn btn-dark rounded-pill">
                                <i class="ri-arrow-left-line me-1"></i> Atrás
                            </a>
                            <div class="d-flex align-items-center gap-3">
                                <button type="submit" form="partilotSummaryForm" class="btn btn-warning rounded-pill px-4 text-dark fw-semibold">Aceptar</button>
                            </div>
                        </div>
                    @else
                        <div class="mt-2">
                            <a href="{{ route('design.external.step1', ['mode' => ($mode ?? 'external')]) }}" class="btn btn-dark rounded-pill">
                                <i class="ri-arrow-left-line me-1"></i> Atrás
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
.invite-card-compact {
    max-width: 360px;
    margin: 0 auto;
}
.invite-title {
    text-align: center;
    font-size: 1.6rem;
    font-weight: 800;
    margin-top: 4px;
}
.invite-input-group {
    background: #f0f1f4;
    border-radius: 999px;
    overflow: hidden;
    min-height: 44px;
}
.invite-input-icon {
    background: transparent;
    border: 0;
    padding-left: 14px;
    color: #1f2329;
    font-size: 0.8rem;
}
.invite-input {
    border: 0;
    background: transparent;
    font-size: 0.8rem;
    color: #5d636c;
}
.invite-input:focus {
    box-shadow: none;
    background: transparent;
}
.invite-submit-btn {
    background-color: #f2c57d;
    min-height: 44px;
    font-size: 1.1rem;
}
.invite-submit-btn:hover {
    background-color: #e9ba6f;
}
.partilot-summary-panel {
    background: #f2f3f5;
    border: 1px solid #e3e5e8;
    border-radius: 12px;
    padding: 18px 20px 16px 20px;
    min-height: 420px;
}
.partilot-field-label {
    font-size: 0.8rem;
    color: #616975;
    margin-bottom: 4px;
    line-height: 1.2;
}
.partilot-field-value {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 700;
    font-size: 0.95rem;
    color: #1f2329;
    padding-bottom: 6px;
    border-bottom: 1px solid #adb5bd;
    min-height: 34px;
}
.partilot-field-value i {
    color: #4d5561;
    font-size: 0.95rem;
}
.partilot-meta-box {
    background: #fff;
    border: 1px solid #e3e5e8;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 0.8rem;
    color: #343a40;
    min-width: 150px;
}
.partilot-total-box {
    border: 1px solid #e3e5e8;
    background: #fff;
    border-radius: 8px;
    padding: 10px 16px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.partilot-total-label {
    font-size: 0.78rem;
    color: #495057;
    font-weight: 700;
}
.partilot-total-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #1f2329;
    line-height: 1;
}
</style>
@endsection

@if(($mode ?? 'external') === 'partilot')
@section('scripts')
<script>
(() => {
    const form = document.getElementById('partilotSummaryForm');
    const quoteUrl = @json(route('design.external.previewQuote'));
    const stripeHint = document.getElementById('external-stripe-hint');
    if (!form) return;

    const fmtMoney = (n) => (Number(n) || 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '€';
    let quoteRefreshTimer = null;

    function updateQuoteDisplay(data) {
        const quote = data.quote || {};
        const totalEl = document.getElementById('quote-total-display');
        const booksEl = document.getElementById('quote-books-count');
        const shopInline = document.getElementById('quote-shop-name-inline');
        if (totalEl) totalEl.textContent = fmtMoney(quote.total);
        if (booksEl) booksEl.textContent = quote.books ?? 0;
        if (shopInline && quote.print_configuration_name) {
            shopInline.textContent = quote.print_configuration_name;
        }
        if (stripeHint) {
            stripeHint.classList.toggle('d-none', !!data.stripe_payment_enabled);
        }
    }

    async function refreshQuote() {
        const formData = new FormData(form);
        const res = await fetch(quoteUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value || '',
                'Accept': 'application/json',
            },
            body: formData,
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            throw new Error(data.message || 'No se pudo calcular el presupuesto.');
        }
        updateQuoteDisplay(data);
    }

    refreshQuote().catch(() => {});
})();
</script>
@endsection
@endif

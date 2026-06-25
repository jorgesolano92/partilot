@extends('layouts.layout')

@section('title', 'Enviar a imprenta')

@section('content')
<div class="container-fluid partilot-page-shell">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('design.index') }}">Diseño e Impresión</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('design.summary', $design->id) }}">Resumen</a></li>
                        <li class="breadcrumb-item active">Enviar a imprenta</li>
                    </ol>
                </div>
                <h4 class="page-title">Enviar a imprenta</h4>
            </div>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row partilot-page-panel-row">
        <div class="col-12">
            <div class="card partilot-page-panel">
                <div class="card-body">
    <form method="POST" action="{{ route('design.submitPrintOrder', $design->id) }}" id="sendToPrintForm">
        @csrf
        <input type="hidden" name="payment_method" id="payment_method" value="{{ !empty($printPayment['can_queue_remittance']) ? 'remittance' : 'stripe' }}">
        <input type="hidden" name="stripe_payment_intent_id" id="stripe_payment_intent_id" value="">
        <div class="row g-4">
            <div class="col-lg-7 partilot-page-panel__col-main">
                        <h5 class="mb-2">Configuración del envío</h5>
                        <p class="text-muted small mb-3">La misma imprenta diseña e imprime el pedido. El presupuesto se calcula con sus tarifas.</p>

                        <input type="hidden" name="print_configuration_id" value="{{ $selectedPrintShop->id }}">
                        <div class="alert alert-light border small mb-3 py-2">
                            <i class="ri-printer-line me-1"></i> Imprenta: <strong>{{ $selectedPrintShop->displayName() }}</strong>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Formato impresión</label>
                                <select name="print_size" class="form-select quote-input">
                                    <option value="a3_6" {{ ($defaults['print_size'] ?? '') === 'a3_6' ? 'selected' : '' }}>A3 - 6 participaciones</option>
                                    <option value="a3_8" {{ ($defaults['print_size'] ?? '') === 'a3_8' ? 'selected' : '' }}>A3 - 8 participaciones</option>
                                    <option value="custom" {{ ($defaults['print_size'] ?? '') === 'custom' ? 'selected' : '' }}>Personalizado</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Participaciones por taco</label>
                                <input type="number" min="1" max="1000" name="participations_per_book" class="form-control quote-input" value="{{ old('participations_per_book', $defaults['participations_per_book'] ?? 50) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Trasera</label>
                                <select name="back_mode" class="form-select quote-input">
                                    <option value="bw" {{ old('back_mode', $defaults['back_mode'] ?? 'bw') === 'bw' ? 'selected' : '' }}>Blanco y negro</option>
                                    <option value="color" {{ old('back_mode', $defaults['back_mode'] ?? '') === 'color' ? 'selected' : '' }}>Color</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Observaciones para imprenta</label>
                                <textarea name="notes" class="form-control" rows="4" placeholder="Indicaciones de entrega, cortes, empaquetado, etc.">{{ old('notes') }}</textarea>
                            </div>
                        </div>
            </div>
            <div class="col-lg-5 partilot-page-panel__col-side d-flex flex-column">
                        <h5 class="mb-1">Resumen de presupuesto</h5>
                        <p class="text-muted small mb-3" id="quote-shop-name">{{ $quote['print_configuration_name'] ?? ($selectedPrintShop->displayName() ?? '') }}</p>
                        <div class="d-flex justify-content-between small mb-2">
                            <span>Pagador</span>
                            <strong id="quote-payer">{{ $printPayment['payer_label'] ?? '—' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-2">
                            <span>Modo de pago</span>
                            <strong id="quote-payment-mode">{{ $printPayment['payment_mode_label'] ?? '—' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-2">
                            <span>Set</span>
                            <strong>{{ $design->set->set_name ?? ('#'.$design->set_id) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-2">
                            <span>Participaciones</span>
                            <strong id="quote-participations">{{ number_format($quote['total_participations'] ?? 0, 0, ',', '.') }}</strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-2">
                            <span>Tacos estimados</span>
                            <strong id="quote-books">{{ $quote['books'] ?? 0 }}</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between small mb-2 align-items-start">
                            <span>Diseño @if(!empty($quote['design_fee_waived']))<span class="d-block text-muted fw-normal" style="font-size:0.85em;">Sin cargo (realizado en PARTILOT)</span>@endif</span>
                            <strong id="quote-design">{{ number_format(($quote['subtotal']['design'] ?? 0), 2, ',', '.') }}€</strong>
                        </div>
                        <div class="d-flex justify-content-between small mb-2"><span>Participaciones</span><strong id="quote-participation">{{ number_format(($quote['subtotal']['participation'] ?? 0), 2, ',', '.') }}€</strong></div>
                        <div class="d-flex justify-content-between small mb-2"><span>Trasera</span><strong id="quote-back">{{ number_format(($quote['subtotal']['back'] ?? 0), 2, ',', '.') }}€</strong></div>
                        <div class="d-flex justify-content-between small mb-2"><span>Tacos</span><strong id="quote-book">{{ number_format(($quote['subtotal']['book'] ?? 0), 2, ',', '.') }}€</strong></div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="fw-semibold">TOTAL</span>
                            <strong class="fs-5" id="quote-total-display">{{ number_format(($quote['total'] ?? 0), 2, ',', '.') }}€</strong>
                        </div>

                        <div id="remittance-payment-block" class="{{ !empty($printPayment['can_queue_remittance']) ? '' : 'd-none' }}">
                            <div class="alert alert-info small mb-3">
                                Esta administración paga por <strong>remesa {{ strtolower($printPayment['remittance_frequency_label'] ?? 'periódica') }}</strong>.
                                El importe se adeudará en el próximo ciclo de cobro.
                            </div>
                        </div>

                        <div id="stripe-unconfigured-alert" class="alert alert-warning small mb-3 {{ ($stripePaymentEnabled ?? false) ? 'd-none' : '' }}">
                            Stripe no está configurado para esta imprenta. Añade las claves en <strong>Ajustes → Imprenta</strong>.
                        </div>
                        <div id="stripe-payment-block" class="{{ ($stripePaymentEnabled ?? false) ? '' : 'd-none' }}">
                            <div class="payment-card-form border rounded p-3 mb-3">
                                <h6 class="mb-2">Pago con tarjeta</h6>
                                <div id="stripe-card-element" class="form-control" style="padding-top: 12px; min-height: 46px;"></div>
                                <div id="stripe-card-errors" class="text-danger small mt-2 d-none"></div>
                                <p class="form-text small mb-0 mt-2">El importe se actualiza al cambiar las opciones del formulario.</p>
                            </div>
                        </div>

                        <div class="mt-auto d-flex justify-content-between">
                            <a href="{{ route('design.summary', $design->id) }}" class="btn btn-dark">
                                <i class="ri-arrow-left-line me-1"></i> Volver
                            </a>
                            <button type="button" id="btn-remittance-submit" class="btn btn-warning text-dark fw-semibold {{ !empty($printPayment['can_queue_remittance']) ? '' : 'd-none' }}" onclick="return confirmRemittanceSubmit();">
                                <i class="ri-bank-line me-1"></i> Confirmar en remesa y enviar
                            </button>
                            <button type="button" id="btn-stripe-pay" class="btn btn-warning text-dark fw-semibold {{ ($stripePaymentEnabled ?? false) ? '' : 'd-none' }}">
                                <i class="ri-bank-card-line me-1"></i> Pagar y enviar a imprenta
                            </button>
                            <button type="button" id="btn-stripe-disabled" class="btn btn-warning text-dark fw-semibold {{ ($stripePaymentEnabled ?? false) || !empty($printPayment['can_queue_remittance']) ? 'd-none' : '' }}" disabled title="Configura Stripe en la imprenta">
                                <i class="ri-send-plane-line me-1"></i> Enviar a imprenta
                            </button>
                        </div>
            </div>
        </div>
    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://js.stripe.com/v3/"></script>
<script>
function confirmRemittanceSubmit() {
    if (!confirm('¿Confirmar el envío a imprenta y registrar el importe en la próxima remesa?')) {
        return false;
    }
    document.getElementById('payment_method').value = 'remittance';
    document.getElementById('sendToPrintForm').submit();
    return false;
}

(() => {
    const payBtn = document.getElementById('btn-stripe-pay');
    const remittanceBtn = document.getElementById('btn-remittance-submit');
    const disabledBtn = document.getElementById('btn-stripe-disabled');
    const errorBox = document.getElementById('stripe-card-errors');
    const paymentIntentInput = document.getElementById('stripe_payment_intent_id');
    const paymentMethodInput = document.getElementById('payment_method');
    const form = document.getElementById('sendToPrintForm');
    const cardContainer = document.getElementById('stripe-card-element');
    const quoteUrl = @json(route('design.previewPrintOrderQuote', $design->id));
    if (!form) return;

    let stripe = null;
    let card = null;
    let publishableKey = @json($stripePublishableKey ?? '');
    let quoteRefreshTimer = null;
    let usesRemittance = @json(!empty($printPayment['can_queue_remittance']));

    const fmtMoney = (n) => (Number(n) || 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '€';
    const fmtInt = (n) => (Number(n) || 0).toLocaleString('es-ES');

    function showError(msg) {
        if (!errorBox) return;
        errorBox.textContent = msg || 'Error procesando el pago.';
        errorBox.classList.remove('d-none');
    }

    function clearError() {
        if (!errorBox) return;
        errorBox.textContent = '';
        errorBox.classList.add('d-none');
    }

    function setPaymentUi(printPayment, stripeEnabled, stripeKey) {
        usesRemittance = !!(printPayment && printPayment.can_queue_remittance);
        const remittanceBlock = document.getElementById('remittance-payment-block');
        const alertEl = document.getElementById('stripe-unconfigured-alert');
        const blockEl = document.getElementById('stripe-payment-block');

        if (usesRemittance) {
            remittanceBlock?.classList.remove('d-none');
            alertEl?.classList.add('d-none');
            blockEl?.classList.add('d-none');
            remittanceBtn?.classList.remove('d-none');
            payBtn?.classList.add('d-none');
            disabledBtn?.classList.add('d-none');
            paymentMethodInput.value = 'remittance';
            if (card) {
                try { card.unmount(); } catch (e) {}
                card = null;
                stripe = null;
            }
            clearError();
            return;
        }

        remittanceBlock?.classList.add('d-none');
        remittanceBtn?.classList.add('d-none');
        paymentMethodInput.value = 'stripe';

        if (stripeEnabled) {
            alertEl?.classList.add('d-none');
            blockEl?.classList.remove('d-none');
            payBtn?.classList.remove('d-none');
            disabledBtn?.classList.add('d-none');
            if (stripeKey) {
                publishableKey = stripeKey;
                mountCard().catch((e) => showError(e.message));
            }
        } else {
            alertEl?.classList.remove('d-none');
            blockEl?.classList.add('d-none');
            payBtn?.classList.add('d-none');
            disabledBtn?.classList.remove('d-none');
            publishableKey = '';
            if (card) {
                try { card.unmount(); } catch (e) {}
                card = null;
                stripe = null;
            }
            clearError();
        }
    }

    function updateQuoteDisplay(quote, printPayment, stripeEnabled, stripeKey) {
        document.getElementById('quote-shop-name').textContent = quote.print_configuration_name || '';
        document.getElementById('quote-payer').textContent = printPayment?.payer_label || '—';
        const paymentModeEl = document.getElementById('quote-payment-mode');
        if (paymentModeEl) {
            paymentModeEl.textContent = printPayment?.payment_mode_label || '—';
        }
        document.getElementById('quote-participations').textContent = fmtInt(quote.total_participations);
        document.getElementById('quote-books').textContent = quote.books ?? 0;
        document.getElementById('quote-design').textContent = fmtMoney(quote.subtotal?.design);
        document.getElementById('quote-participation').textContent = fmtMoney(quote.subtotal?.participation);
        document.getElementById('quote-back').textContent = fmtMoney(quote.subtotal?.back);
        document.getElementById('quote-book').textContent = fmtMoney(quote.subtotal?.book);
        document.getElementById('quote-total-display').textContent = fmtMoney(quote.total);
        setPaymentUi(printPayment, !!stripeEnabled, stripeKey || '');
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
        updateQuoteDisplay(data.quote, data.print_payment, data.stripe_payment_enabled, data.stripe_publishable_key);
    }

    function scheduleQuoteRefresh() {
        clearTimeout(quoteRefreshTimer);
        quoteRefreshTimer = setTimeout(() => {
            refreshQuote().catch((e) => showError(e.message));
        }, 350);
    }

    async function mountCard() {
        if (!publishableKey || !cardContainer) {
            throw new Error('Stripe no configurado.');
        }
        stripe = Stripe(publishableKey);
        const elements = stripe.elements();
        if (card) {
            try { card.unmount(); } catch (e) {}
        }
        card = elements.create('card', { hidePostalCode: true });
        card.mount('#stripe-card-element');
    }

    async function createPaymentIntent() {
        const formData = new FormData(form);
        const res = await fetch(@json(route('design.createPrintOrderPaymentIntent', $design->id)), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value || '',
                'Accept': 'application/json',
            },
            body: formData,
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            throw new Error(data.message || 'No se pudo iniciar el pago.');
        }
        return data;
    }

    form.querySelectorAll('.quote-input').forEach((el) => {
        el.addEventListener('change', scheduleQuoteRefresh);
        el.addEventListener('input', scheduleQuoteRefresh);
    });
    payBtn?.addEventListener('click', async () => {
        if (usesRemittance) {
            return;
        }
        try {
            clearError();
            payBtn.disabled = true;
            paymentMethodInput.value = 'stripe';

            if (!stripe || !card) {
                await mountCard();
            }

            const intentData = await createPaymentIntent();
            if (intentData.publishable_key) {
                publishableKey = intentData.publishable_key;
            }

            const result = await stripe.confirmCardPayment(intentData.client_secret, {
                payment_method: { card },
            });

            if (result.error) {
                showError(result.error.message || 'Pago rechazado.');
                payBtn.disabled = false;
                return;
            }

            if (!result.paymentIntent || result.paymentIntent.status !== 'succeeded') {
                showError('El pago no se confirmó correctamente.');
                payBtn.disabled = false;
                return;
            }

            paymentIntentInput.value = result.paymentIntent.id;
            form.submit();
        } catch (e) {
            showError(e.message || 'Error al procesar el pago.');
            payBtn.disabled = false;
        }
    });

    refreshQuote().catch(() => {});
})();
</script>
@endsection

@extends('layouts.layout')

@section('title', 'Diseño e Impresión - Pago')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('design.index') }}">Diseño e Impresión</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('design.external.list') }}">Diseño externo</a></li>
                        <li class="breadcrumb-item active">Pago</li>
                    </ol>
                </div>
                <h4 class="page-title">Diseño e impresión PARTILOT</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex p-2 mb-3" style="align-items: center; justify-content: center;">
                        <div class="form-wizard-element" style="width: 220px;">
                            <span style="top: -4px; margin-right: 8px;">1</span>
                            <label>Indicaciones <br> / Archivos</label>
                        </div>
                        <div class="form-wizard-element" style="width: 220px;">
                            <span style="top: -4px; margin-right: 8px;">2</span>
                            <label>Resumen</label>
                        </div>
                        <div class="form-wizard-element active" style="width: 220px;">
                            <span style="top: -4px; margin-right: 8px;">3</span>
                            <label>Pago Diseño <br> e Impresión</label>
                        </div>
                    </div>

                    <div class="form-card bs">
                        <h4 class="mb-0 mt-1">Pago Diseño e Impresión</h4>
                        <small><i>Pago con la imprenta seleccionada en el resumen.</i></small>

                        <form action="{{ route('design.external.sendInvitation') }}" method="POST" id="partilotPaymentForm" class="mt-4">
                            @csrf
                            <input type="hidden" name="payment_method" id="payment_method" value="{{ !empty($printPayment['can_queue_remittance']) ? 'remittance' : 'stripe' }}">
                            <input type="hidden" name="stripe_payment_intent_id" id="stripe_payment_intent_id" value="">

                            <div class="payment-mock-wrap">
                                <div class="row g-4">
                                    <div class="col-lg-5">
                                        <div class="payment-info-card">
                                            <div class="payment-amount-box">
                                                <div class="payment-amount-label">Importe</div>
                                                <div class="payment-amount-value">{{ number_format(($quote['total'] ?? 0), 2, ',', '.') }}€</div>
                                            </div>
                                            <div class="payment-info-line"><span>Pagador:</span> <strong>{{ $printPayment['payer_label'] ?? '—' }}</strong></div>
                                            <div class="payment-info-line"><span>Modo de pago:</span> <strong>{{ $printPayment['payment_mode_label'] ?? '—' }}</strong></div>
                                            <div class="payment-info-line"><span>Imprenta:</span> <strong>{{ $selectedPrintShop->displayName() }}</strong></div>
                                            <div class="payment-info-line"><span>Pedido:</span> <strong>{{ $invitation->orden_id ?? ('ORD-' . $set->id) }}</strong></div>
                                            <div class="payment-info-line"><span>Fecha:</span> <strong>{{ now()->format('d/m/Y H:i') }}</strong></div>
                                            <div class="payment-info-line"><span>Descripción:</span> <strong>Diseño e impresión PARTILOT</strong></div>
                                            @if(!empty($quote['is_digital_set']))
                                                <div class="payment-info-line"><span>Tipo de set:</span> <strong>Digital (solo diseño)</strong></div>
                                            @endif
                                            @if(!empty($quote['subtotal']['design']))
                                                <div class="payment-info-line"><span>Incl. diseño:</span> <strong>{{ number_format($quote['subtotal']['design'], 2, ',', '.') }}€</strong></div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-lg-7">
                                        <div id="remittance-payment-block" class="{{ !empty($printPayment['can_queue_remittance']) ? '' : 'd-none' }}">
                                            <div class="alert alert-info small mb-0">
                                                Esta administración paga por <strong>remesa {{ strtolower($printPayment['remittance_frequency_label'] ?? 'periódica') }}</strong>.
                                                El importe se adeudará en el próximo ciclo de cobro.
                                            </div>
                                        </div>

                                        <div id="stripe-payment-block" class="{{ ($stripePaymentEnabled ?? false) ? '' : 'd-none' }}">
                                            <div class="payment-card-form">
                                                <h5 class="mb-3">PAGAR CON TARJETA</h5>
                                                <div id="stripe-card-element" class="form-control" style="padding-top: 12px; min-height: 46px;"></div>
                                                <div id="stripe-card-errors" class="text-danger small mt-2 d-none"></div>
                                                <div class="form-text mt-3">
                                                    Usa tarjeta de prueba: 4242 4242 4242 4242, fecha futura, CVC cualquiera.
                                                </div>
                                            </div>
                                        </div>

                                        <div id="stripe-unconfigured-alert" class="alert alert-warning mb-0 {{ ($stripePaymentEnabled ?? false) || !empty($printPayment['can_queue_remittance']) ? 'd-none' : '' }}">
                                            Stripe no está configurado para <strong>{{ $selectedPrintShop->displayName() }}</strong>.
                                            Configura las claves en Ajustes → Imprenta.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="d-flex justify-content-between align-items-end mt-4">
                        <a href="{{ route('design.external.step2') }}" class="btn btn-dark rounded-pill">
                            <i class="ri-arrow-left-line me-1"></i> Atrás
                        </a>
                        @if(!empty($printPayment['can_queue_remittance']))
                            <button type="button" id="btn-remittance-submit" class="btn btn-warning rounded-pill px-4 text-dark fw-semibold" onclick="return confirmExternalRemittanceSubmit();">
                                Confirmar en remesa
                            </button>
                        @elseif($stripePaymentEnabled ?? false)
                            <button type="button" id="btn-stripe-pay" class="btn btn-warning rounded-pill px-4 text-dark fw-semibold">Pagar</button>
                        @else
                            <button type="button" class="btn btn-warning rounded-pill px-4 text-dark fw-semibold" disabled>Pagar</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
.payment-mock-wrap {
    background: #f2f3f5;
    border: 1px solid #e3e5e8;
    border-radius: 12px;
    padding: 18px;
}
.payment-info-card,
.payment-card-form {
    background: #fff;
    border: 1px solid #e3e5e8;
    border-radius: 10px;
    padding: 16px;
    min-height: 100%;
}
.payment-amount-box {
    background: #18a0db;
    border-radius: 8px;
    padding: 14px;
    color: #fff;
    margin-bottom: 14px;
}
.payment-amount-label {
    font-size: 0.9rem;
    opacity: 0.95;
}
.payment-amount-value {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1;
}
.payment-info-line {
    display: flex;
    justify-content: space-between;
    border-bottom: 1px solid #eef0f2;
    padding: 8px 0;
    font-size: 0.95rem;
}
</style>
@endsection

@if(!empty($printPayment['can_queue_remittance']) || ($stripePaymentEnabled ?? false))
@section('scripts')
@if($stripePaymentEnabled ?? false)
<script src="https://js.stripe.com/v3/"></script>
@endif
<script>
function confirmExternalRemittanceSubmit() {
    if (!confirm('¿Confirmar el envío a imprenta y registrar el importe en la próxima remesa?')) {
        return false;
    }
    document.getElementById('payment_method').value = 'remittance';
    document.getElementById('partilotPaymentForm').submit();
    return false;
}
</script>
@if($stripePaymentEnabled ?? false)
<script>
(() => {
    const payBtn = document.getElementById('btn-stripe-pay');
    const errorBox = document.getElementById('stripe-card-errors');
    const paymentIntentInput = document.getElementById('stripe_payment_intent_id');
    const paymentMethodInput = document.getElementById('payment_method');
    const form = document.getElementById('partilotPaymentForm');
    const cardContainer = document.getElementById('stripe-card-element');
    if (!payBtn || !errorBox || !paymentIntentInput || !form || !cardContainer) return;

    let stripe = null;
    let card = null;
    let clientSecret = null;

    function showError(msg) {
        errorBox.textContent = msg || 'Error procesando pago.';
        errorBox.classList.remove('d-none');
    }

    function clearError() {
        errorBox.textContent = '';
        errorBox.classList.add('d-none');
    }

    async function initStripe() {
        const res = await fetch('{{ route('design.external.createPaymentIntent') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (!res.ok || !data.ok) {
            throw new Error(data.message || 'No se pudo iniciar pago con Stripe.');
        }

        stripe = Stripe(data.publishable_key);
        clientSecret = data.client_secret;

        const elements = stripe.elements();
        card = elements.create('card', {hidePostalCode: true});
        card.mount('#stripe-card-element');
    }

    payBtn.addEventListener('click', async () => {
        try {
            clearError();
            payBtn.disabled = true;
            paymentMethodInput.value = 'stripe';
            if (!stripe || !card || !clientSecret) {
                await initStripe();
            }

            const result = await stripe.confirmCardPayment(clientSecret, {
                payment_method: { card }
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
            showError(e.message || 'Error iniciando Stripe.');
            payBtn.disabled = false;
        }
    });

    initStripe().catch((e) => {
        showError(e.message || 'No se pudo inicializar Stripe.');
    });
})();
</script>
@endif
@endsection
@endif

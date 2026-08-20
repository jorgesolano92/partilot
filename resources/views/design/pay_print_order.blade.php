@extends('layouts.layout')

@section('title', 'Pagar pedido de imprenta')

@section('content')
@php
    $quote = is_array($printOrder->quote_breakdown ?? null) ? $printOrder->quote_breakdown : [];
    $includeBackInPrint = ($printOrder->back_mode ?? 'none') !== 'none';
@endphp
<div class="container-fluid partilot-page-shell">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('design.index') }}">Diseño e Impresión</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('design.summary', $design->id) }}">Resumen</a></li>
                        <li class="breadcrumb-item active">Pagar pedido</li>
                    </ol>
                </div>
                <h4 class="page-title">Pagar pedido {{ $printOrder->order_code }}</h4>
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
                    <div class="row g-4">
                        <div class="col-lg-7 partilot-page-panel__col-main">
                            <h5 class="mb-2">Detalle del pedido</h5>
                            <div class="alert alert-success small mb-3">
                                La imprenta ha <strong>aceptado</strong> el pedido. Complete el pago para que pueda iniciar la producción.
                            </div>
                            <div class="row g-3 small">
                                <div class="col-md-6">
                                    <div class="text-muted">Entidad</div>
                                    <div class="fw-semibold">{{ $printOrder->entity->name ?? '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted">Set</div>
                                    <div class="fw-semibold">{{ $design->set->set_name ?? ('#'.$design->set_id) }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted">Imprenta</div>
                                    <div class="fw-semibold">{{ $cfg->displayName() }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted">Sorteo</div>
                                    <div class="fw-semibold">{{ $printOrder->lottery->name ?? ($design->lottery->name ?? '—') }}</div>
                                </div>
                            </div>

                            <hr class="my-4">

                            @include('design.partials.print_order_quote_summary', [
                                'summaryTitle' => 'Resumen de factura',
                                'quote' => $quote,
                                'printPayment' => $printPayment,
                                'printOrder' => $printOrder,
                                'design' => $design,
                                'cfg' => $cfg,
                                'includeBackInPrint' => $includeBackInPrint,
                            ])
                        </div>

                        <div class="col-lg-5 partilot-page-panel__col-side d-flex flex-column">
                            <h5 class="mb-3">Importe a pagar</h5>
                            <div class="fs-3 fw-semibold mb-4">{{ number_format((float) $printOrder->quoted_amount, 2, ',', '.') }}€</div>

                            <form method="POST" action="{{ route('design.submitPrintOrderPayment', $printOrder->id) }}" id="payPrintOrderForm" class="mt-auto">
                                @csrf
                                <input type="hidden" name="stripe_payment_intent_id" id="stripe_payment_intent_id" value="">

                                @if($canPayViaRemittance ?? false)
                                    <div class="alert alert-info small mb-3">
                                        Este pedido se registrará en la próxima <strong>remesa {{ strtolower($printPayment['remittance_frequency_label'] ?? 'periódica') }}</strong>.
                                    </div>
                                    <button type="submit" class="btn btn-warning text-dark w-100 fw-semibold" onclick="return confirm('¿Confirmar el pago en remesa e iniciar producción?');">
                                        <i class="ri-bank-line me-1"></i> Confirmar en remesa y pagar
                                    </button>
                                @elseif($stripePaymentEnabled ?? false)
                                    <div class="payment-card-form border rounded p-3 mb-3">
                                        <h6 class="mb-2">Pago con tarjeta</h6>
                                        <div id="stripe-card-element" class="form-control" style="padding-top: 12px; min-height: 46px;"></div>
                                        <div id="stripe-card-errors" class="text-danger small mt-2 d-none"></div>
                                    </div>
                                    <button type="button" id="btn-stripe-pay" class="btn btn-warning text-dark w-100 fw-semibold">
                                        <i class="ri-bank-card-line me-1"></i> Pagar con tarjeta
                                    </button>
                                @else
                                    <div class="alert alert-warning small mb-0">No hay un medio de pago disponible para su perfil.</div>
                                @endif
                            </form>

                            <a href="{{ route('design.summary', $design->id) }}" class="btn btn-dark w-100 mt-3">
                                <i class="ri-arrow-left-line me-1"></i> Volver al resumen
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
@if($stripePaymentEnabled ?? false)
<script src="https://js.stripe.com/v3/"></script>
<script>
(() => {
    const payBtn = document.getElementById('btn-stripe-pay');
    const errorBox = document.getElementById('stripe-card-errors');
    const paymentIntentInput = document.getElementById('stripe_payment_intent_id');
    const form = document.getElementById('payPrintOrderForm');
    if (!payBtn || !form) return;

    let stripe = null;
    let card = null;

    async function initStripe() {
        const res = await fetch(@json(route('design.payPrintOrder.paymentIntent', $printOrder->id)), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': @json(csrf_token()),
                'Accept': 'application/json',
            },
            body: JSON.stringify({}),
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.message || 'No se pudo iniciar el pago.');
        stripe = Stripe(data.publishable_key);
        const elements = stripe.elements();
        card = elements.create('card', { hidePostalCode: true });
        card.mount('#stripe-card-element');
        return data.client_secret;
    }

    let clientSecretPromise = initStripe();

    payBtn.addEventListener('click', async () => {
        payBtn.disabled = true;
        errorBox.classList.add('d-none');
        try {
            const clientSecret = await clientSecretPromise;
            const result = await stripe.confirmCardPayment(clientSecret, {
                payment_method: { card },
            });
            if (result.error) {
                errorBox.textContent = result.error.message || 'Error en el pago.';
                errorBox.classList.remove('d-none');
                payBtn.disabled = false;
                return;
            }
            paymentIntentInput.value = result.paymentIntent.id;
            form.submit();
        } catch (e) {
            errorBox.textContent = e.message || 'Error procesando el pago.';
            errorBox.classList.remove('d-none');
            payBtn.disabled = false;
        }
    });
})();
</script>
@endif
@endsection

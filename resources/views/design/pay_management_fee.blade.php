@extends('layouts.layout')



@section('title', 'Pagar cuota de gestión')



@section('content')

<div class="container-fluid">

    <div class="row">

        <div class="col-12">

            <div class="page-title-box">

                <div class="page-title-right">

                    <ol class="breadcrumb m-0">

                        <li class="breadcrumb-item"><a href="{{ route('design.index') }}">Diseño e Impresión</a></li>

                        <li class="breadcrumb-item active">Cuota gestión</li>

                    </ol>

                </div>

                <h4 class="page-title">Cuota de gestión PARTILOT</h4>

            </div>

        </div>

    </div>



    @if(session('info'))

        <div class="alert alert-info">{{ session('info') }}</div>

    @endif

    @if(session('error'))

        <div class="alert alert-danger">{{ session('error') }}</div>

    @endif



    <div class="row justify-content-center">

        <div class="col-lg-6">

            <div class="card">

                <div class="card-body">

                    <h5 class="mb-3">Resumen del cobro</h5>

                    <div class="d-flex justify-content-between mb-2">

                        <span>Set</span>

                        <strong>{{ $set->set_name ?? ('#'.$set->id) }}</strong>

                    </div>

                    <div class="d-flex justify-content-between mb-2">

                        <span>Pagador</span>

                        <strong>{{ $managementFee['payer_label'] ?? '—' }}</strong>

                    </div>

                    <div class="d-flex justify-content-between mb-2">

                        <span>Participaciones</span>

                        <strong>{{ number_format($managementFee['participation_count'] ?? 0, 0, ',', '.') }}</strong>

                    </div>

                    <div class="d-flex justify-content-between mb-2">

                        <span>Importe unitario</span>

                        <strong>{{ number_format($managementFee['unit_price'] ?? 0, 4, ',', '.') }}€</strong>

                    </div>

                    <hr>

                    <div class="d-flex justify-content-between mb-4">

                        <span class="fw-semibold">Total</span>

                        <strong class="fs-4">{{ number_format($managementFee['amount'] ?? 0, 2, ',', '.') }}€</strong>

                    </div>



                    @if(!empty($managementFee['can_queue_remittance']))

                        <div class="alert alert-info small">

                            Esta administración paga por <strong>remesa {{ strtolower($managementFee['remittance_frequency_label'] ?? 'periódica') }}</strong>.

                            Al confirmar, el importe quedará pendiente de adeudo en el próximo ciclo de facturación.

                        </div>

                        <form method="POST" action="{{ route('design.managementFee.confirmRemittance', $set->id) }}" onsubmit="return confirm('¿Confirmar el cargo de cuota de gestión en la próxima remesa?');">

                            @csrf

                            <button type="submit" class="btn btn-success w-100">

                                <i class="ri-bank-line me-1"></i> Confirmar cargo en remesa y continuar

                            </button>

                        </form>

                    @elseif(!($stripePaymentEnabled ?? false))

                        <div class="alert alert-warning small">

                            Stripe no está configurado en <strong>Ajustes → Config. factura auto</strong>.

                            @if(!empty($managementFee['can_mark_paid']))

                                Puede confirmar el pago manualmente desde el resumen del diseño (solo entorno de pruebas).

                            @endif

                        </div>

                    @else

                        <form method="POST" action="{{ route('design.managementFee.confirmStripe', $set->id) }}" id="management-fee-pay-form">

                            @csrf

                            <input type="hidden" name="stripe_payment_intent_id" id="stripe_payment_intent_id" value="">

                            <div class="payment-card-form border rounded p-3 mb-3">

                                <h6 class="mb-2">Pago con tarjeta</h6>

                                <div id="stripe-card-element" class="form-control" style="padding-top: 12px; min-height: 46px;"></div>

                                <div id="stripe-card-errors" class="text-danger small mt-2 d-none"></div>

                            </div>

                            <button type="button" id="btn-pay-management-fee" class="btn btn-success w-100">

                                <i class="ri-bank-card-line me-1"></i> Pagar cuota y continuar

                            </button>

                        </form>

                    @endif



                    @if($design)

                        <a href="{{ route('design.summary', $design->id) }}" class="btn btn-link mt-3 px-0">Volver al resumen</a>

                    @else

                        <a href="{{ route('design.index') }}" class="btn btn-link mt-3 px-0">Volver al listado</a>

                    @endif

                </div>

            </div>

        </div>

    </div>

</div>

@endsection



@section('scripts')

@if(($stripePaymentEnabled ?? false) && empty($managementFee['can_queue_remittance']))

<script src="https://js.stripe.com/v3/"></script>

<script>

(function () {

    const publishableKey = @json($stripePublishableKey ?? '');

    const paymentIntentUrl = @json(route('design.managementFee.paymentIntent', $set->id));

    const csrf = @json(csrf_token());

    let stripe, elements, card, clientSecret;



    async function initStripe() {

        const res = await fetch(paymentIntentUrl, {

            method: 'POST',

            headers: {

                'Content-Type': 'application/json',

                'Accept': 'application/json',

                'X-CSRF-TOKEN': csrf,

            },

            body: '{}',

        });

        const data = await res.json();

        if (!data.ok) {

            throw new Error(data.message || 'No se pudo iniciar el pago.');

        }

        if (data.already_paid) {

            window.location.href = @json($design ? route('design.summary', $design->id) : route('design.index'));

            return;

        }

        stripe = Stripe(data.publishable_key || publishableKey);

        elements = stripe.elements();

        card = elements.create('card', { hidePostalCode: true });

        card.mount('#stripe-card-element');

        clientSecret = data.client_secret;

    }



    document.getElementById('btn-pay-management-fee').addEventListener('click', async function () {

        const errEl = document.getElementById('stripe-card-errors');

        errEl.classList.add('d-none');

        try {

            const result = await stripe.confirmCardPayment(clientSecret, {

                payment_method: { card },

            });

            if (result.error) {

                errEl.textContent = result.error.message || 'Error en el pago.';

                errEl.classList.remove('d-none');

                return;

            }

            if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {

                document.getElementById('stripe_payment_intent_id').value = result.paymentIntent.id;

                document.getElementById('management-fee-pay-form').submit();

            }

        } catch (e) {

            errEl.textContent = e.message || 'Error procesando el pago.';

            errEl.classList.remove('d-none');

        }

    });



    initStripe().catch(function (e) {

        const errEl = document.getElementById('stripe-card-errors');

        errEl.textContent = e.message || 'No se pudo inicializar Stripe.';

        errEl.classList.remove('d-none');

    });

})();

</script>

@endif

@endsection


@php
    use App\Services\AdministrationBillingService;

    $billingService = app(AdministrationBillingService::class);
    $paymentMode = $administration->billing_payment_mode ?? AdministrationBillingService::MODE_CARD;
    $frequency = $administration->billing_remittance_frequency ?? AdministrationBillingService::FREQUENCY_MONTHLY;
    $hasIban = $billingService->hasValidBillingIban($administration);
    $pendingCharges = $pendingBillingCharges ?? collect();
    $pendingTotal = $pendingBillingTotal ?? $pendingCharges->sum('amount');
@endphp

<div class="form-card bs mt-4">
    <h4 class="mb-0 mt-1">Modalidad de cobro PARTILOT</h4>
    <small><i>Solo Super Admin. Define cómo se cobran las cuotas cuando la administración es pagadora.</i></small>

    @if(session('success'))
        <div class="alert alert-success mt-3 mb-0">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mt-3 mb-0">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('administrations.update-billing-payment', $administration->id) }}" class="mt-3" id="admin-billing-payment-form">
        @csrf

        <div class="row">
            <div class="col-md-6">
                <label class="label-control d-block mb-2">Forma de pago</label>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="billing_payment_mode" id="billing_mode_card" value="card" @checked($paymentMode === AdministrationBillingService::MODE_CARD)>
                    <label class="form-check-label" for="billing_mode_card">
                        <strong>Tarjeta (Stripe)</strong>
                        <br><small class="text-muted">Cobro puntual con TPV al confirmar cada cuota.</small>
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="billing_payment_mode" id="billing_mode_remittance" value="remittance" @checked($paymentMode === AdministrationBillingService::MODE_REMITTANCE) @disabled(! $hasIban)>
                    <label class="form-check-label" for="billing_mode_remittance">
                        <strong>Remesa periódica</strong>
                        <br><small class="text-muted">Los cargos se acumulan y se adeudan por domiciliación.</small>
                    </label>
                </div>
                @unless($hasIban)
                    <p class="small text-warning mb-0">Configure un IBAN válido en datos legales para habilitar remesa.</p>
                @endunless
            </div>

            <div class="col-md-6" id="billing-remittance-frequency-wrap" style="{{ $paymentMode === AdministrationBillingService::MODE_REMITTANCE ? '' : 'display:none;' }}">
                <label class="label-control d-block mb-2" for="billing_remittance_frequency">Periodicidad de remesa</label>
                <select class="form-select" name="billing_remittance_frequency" id="billing_remittance_frequency">
                    <option value="{{ AdministrationBillingService::FREQUENCY_MONTHLY }}" @selected($frequency === AdministrationBillingService::FREQUENCY_MONTHLY)>Mensual</option>
                    <option value="{{ AdministrationBillingService::FREQUENCY_BIWEEKLY }}" @selected($frequency === AdministrationBillingService::FREQUENCY_BIWEEKLY)>Quincenal</option>
                </select>
            </div>
        </div>

        <div class="text-end mt-3">
            <button type="submit" class="btn btn-warning text-dark" style="border-radius: 30px;">
                Guardar modalidad de cobro
            </button>
        </div>
    </form>

    @if($pendingCharges->isNotEmpty())
        <hr class="my-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Cargos pendientes de remesa</h5>
            <a href="{{ route('configuration.index', ['section' => 'facturacion-cobros', 'billing_administration_id' => $administration->id]) }}" class="btn btn-sm btn-outline-dark">
                <i class="ri-bank-line me-1"></i> Gestionar cobros en Ajustes
            </a>
        </div>
        <p class="small text-muted mb-2">
            {{ $pendingCharges->count() }} cargo(s) por un total de <strong>{{ number_format($pendingTotal, 2, ',', '.') }}€</strong>.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th>Set</th>
                        <th class="text-end">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingCharges->take(10) as $charge)
                        <tr>
                            <td>{{ $charge->created_at?->format('d/m/Y') }}</td>
                            <td>{{ $charge->conceptLabel() }}</td>
                            <td>#{{ $charge->set_id ?? '—' }}</td>
                            <td class="text-end">{{ number_format($charge->amount, 2, ',', '.') }}€</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($pendingCharges->count() > 10)
            <p class="small text-muted mt-2 mb-0">Mostrando los 10 cargos más recientes.</p>
        @endif
    @endif
</div>

<script>
(function () {
    const form = document.getElementById('admin-billing-payment-form');
    if (!form) return;

    const freqWrap = document.getElementById('billing-remittance-frequency-wrap');
    const radios = form.querySelectorAll('input[name="billing_payment_mode"]');

    function syncFrequencyVisibility() {
        const remittance = form.querySelector('#billing_mode_remittance')?.checked;
        if (freqWrap) {
            freqWrap.style.display = remittance ? '' : 'none';
        }
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', syncFrequencyVisibility);
    });
})();
</script>

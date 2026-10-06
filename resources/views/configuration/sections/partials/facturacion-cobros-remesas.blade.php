@php
    use App\Models\BillingCharge;
    use App\Models\BillingDirectDebitOrder;
    use App\Services\AdministrationBillingService;

    $baseParams = [
        'section' => 'facturacion-cobros',
        'billing_administration_id' => $billingAdministrationId ?? null,
    ];

    $configUrl = fn (array $extra = []) => route('configuration.index', array_filter(
        array_merge($baseParams, $extra),
        fn ($value) => $value !== null && $value !== ''
    ));

    $chargeStatuses = [
        '' => 'Todos los estados',
        BillingCharge::STATUS_PENDING => 'Pendiente de remesa',
        BillingCharge::STATUS_IN_REMITTANCE => 'Incluido en adeudo',
        BillingCharge::STATUS_COLLECTED => 'Cobrado',
        BillingCharge::STATUS_CANCELLED => 'Anulado',
    ];
@endphp

<div class="form-card bs pb-3" style="min-height: 658px;">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <h4 class="mb-0 mt-1">Cobros PARTILOT a administraciones</h4>
    <small><i>Gestión de cargos, estados y órdenes de adeudo SEPA (pain.008)</i></small>

    <form method="get" action="{{ route('configuration.index') }}" class="row g-2 align-items-end mt-3 mb-3">
        <input type="hidden" name="section" value="facturacion-cobros">
        @if(($billingView ?? '') === 'order' && $billingDirectDebitOrder)
            <input type="hidden" name="billing_order_id" value="{{ $billingDirectDebitOrder->id }}">
        @elseif(($billingChargeStatus ?? '') !== '')
            <input type="hidden" name="billing_charge_status" value="{{ $billingChargeStatus }}">
        @endif
        <div class="col-md-6">
            <label class="form-label small mb-1">Administración</label>
            <select name="billing_administration_id" class="form-select" onchange="this.form.submit()">
                <option value="">— Seleccione administración —</option>
                @foreach($billingAdministrations as $admin)
                    <option value="{{ $admin->id }}" @selected((int) ($billingAdministrationId ?? 0) === (int) $admin->id)>
                        {{ $admin->name ?? $admin->society }}
                        @if(($admin->billing_payment_mode ?? '') === AdministrationBillingService::MODE_REMITTANCE)
                            · Remesa {{ strtolower(app(AdministrationBillingService::class)->remittanceFrequencyLabel($admin->billing_remittance_frequency)) }}
                        @else
                            · Tarjeta
                        @endif
                    </option>
                @endforeach
            </select>
        </div>
        @if($billingAdministration)
            <div class="col-md-6 text-md-end">
                <a href="{{ route('administrations.show', $billingAdministration->id) }}" class="btn btn-sm btn-outline-secondary">
                    Ficha administración
                </a>
            </div>
        @endif
    </form>

    @if(!$billingAdministration)
        <div class="alert alert-light border mb-0">
            Seleccione una administración para consultar cargos y gestionar remesas de cobro.
        </div>
    @elseif(($billingView ?? '') === 'order' && $billingDirectDebitOrder)
        <div class="mb-3">
            <a href="{{ $configUrl(array_filter(['billing_charge_status' => $billingChargeStatus ?? ''])) }}" class="btn btn-sm btn-light">
                <i class="ri-arrow-left-line me-1"></i> Volver a cargos
            </a>
        </div>

        <div class="border rounded p-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                <div>
                    <h5 class="mb-1">Orden de adeudo #{{ $billingDirectDebitOrder->id }}</h5>
                    <span class="badge {{ $billingDirectDebitOrder->statusBadgeClass() }}">{{ $billingDirectDebitOrder->statusLabel() }}</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('configuration.billing-remittance.generate-xml', $billingDirectDebitOrder->id) }}" class="btn btn-sm btn-primary">
                        <i class="ri-download-line me-1"></i> Descargar XML pain.008
                    </a>
                    @if($billingDirectDebitOrder->status !== BillingDirectDebitOrder::STATUS_COLLECTED && $billingDirectDebitOrder->status !== BillingDirectDebitOrder::STATUS_CANCELLED)
                        <form method="POST" action="{{ route('configuration.billing-remittance.mark-collected', $billingDirectDebitOrder->id) }}" class="d-inline" onsubmit="return confirm('¿Confirmar que el banco ha cobrado este adeudo? Los cargos pasarán a estado Cobrado.');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success">Marcar como cobrado</button>
                        </form>
                        <form method="POST" action="{{ route('configuration.billing-remittance.cancel', $billingDirectDebitOrder->id) }}" class="d-inline" onsubmit="return confirm('¿Anular la orden? Los cargos volverán a Pendiente de remesa.');">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger">Anular orden</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="row g-3 small mb-4">
                <div class="col-md-3">
                    <div class="text-muted">Fecha de cobro</div>
                    <strong>{{ $billingDirectDebitOrder->collection_date?->format('d/m/Y') }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">Importe total</div>
                    <strong>{{ number_format($billingDirectDebitOrder->control_sum, 2, ',', '.') }}€</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">Transacciones</div>
                    <strong>{{ $billingDirectDebitOrder->number_of_transactions }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">Creada</div>
                    <strong>{{ $billingDirectDebitOrder->creation_date?->format('d/m/Y H:i') }}</strong>
                </div>
                <div class="col-md-6">
                    <div class="text-muted">Message ID</div>
                    <code>{{ $billingDirectDebitOrder->message_id }}</code>
                </div>
                <div class="col-md-6">
                    <div class="text-muted">XML</div>
                    @if($billingDirectDebitOrder->xml_filename)
                        <span class="text-success">{{ $billingDirectDebitOrder->xml_filename }}</span>
                        @if($billingDirectDebitOrder->exported_at)
                            <span class="text-muted">({{ $billingDirectDebitOrder->exported_at->format('d/m/Y H:i') }})</span>
                        @endif
                    @else
                        <span class="text-muted">Sin exportar</span>
                    @endif
                </div>
                <div class="col-md-4">
                    <div class="text-muted">Deudor</div>
                    {{ $billingDirectDebitOrder->debtor_name }}<br>
                    <code>{{ $billingDirectDebitOrder->debtor_iban }}</code>
                </div>
                <div class="col-md-4">
                    <div class="text-muted">Mandato SEPA</div>
                    {{ $billingDirectDebitOrder->debtor_mandate_id }}<br>
                    Firmado: {{ $billingDirectDebitOrder->debtor_mandate_signed_at?->format('d/m/Y') }}
                </div>
                <div class="col-md-4">
                    <div class="text-muted">Secuencia / Cobrado</div>
                    {{ $billingDirectDebitOrder->sequence_type }}
                    @if($billingDirectDebitOrder->collected_at)
                        <br><span class="text-success">Cobrado: {{ $billingDirectDebitOrder->collected_at->format('d/m/Y H:i') }}</span>
                    @endif
                </div>
            </div>

            <h6 class="mb-2">Cargos incluidos en esta remesa</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Concepto</th>
                            <th>Descripción</th>
                            <th>Entidad</th>
                            <th class="text-end">Importe</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($billingDirectDebitOrder->charges as $charge)
                            <tr>
                                <td>{{ $charge->id }}</td>
                                <td>{{ $charge->created_at?->format('d/m/Y') }}</td>
                                <td>{{ $charge->conceptLabel() }}</td>
                                <td>{{ $charge->description }}</td>
                                <td>{{ $charge->entity->name ?? '—' }}</td>
                                <td class="text-end">{{ number_format($charge->amount, 2, ',', '.') }}€</td>
                                <td><span class="badge {{ $charge->statusBadgeClass() }}">{{ $charge->statusLabel() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
            <form method="get" action="{{ route('configuration.index') }}" class="d-flex gap-2 align-items-end flex-wrap">
                <input type="hidden" name="section" value="facturacion-cobros">
                <input type="hidden" name="billing_administration_id" value="{{ $billingAdministrationId }}">
                <div>
                    <label class="form-label small mb-1">Filtrar por estado</label>
                    <select name="billing_charge_status" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach($chargeStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(($billingChargeStatus ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
            @if($billingPendingCharges->isNotEmpty())
                <form method="POST" action="{{ route('configuration.billing-remittance.store', $billingAdministration->id) }}" class="d-flex gap-2 align-items-center flex-wrap" onsubmit="return confirm('¿Crear orden de adeudo con todos los cargos pendientes?');">
                    @csrf
                    @foreach($billingPendingCharges as $charge)
                        <input type="hidden" name="charge_ids[]" value="{{ $charge->id }}">
                    @endforeach
                    <span class="small text-muted">
                        {{ $billingPendingCharges->count() }} pendiente(s) · {{ number_format($billingPendingCharges->sum('amount'), 2, ',', '.') }}€
                    </span>
                    <input type="date" name="collection_date" class="form-control form-control-sm" value="{{ now()->addDays(5)->format('Y-m-d') }}" required style="width: auto;">
                    <button type="submit" class="btn btn-warning text-dark btn-sm">
                        <i class="ri-bank-line me-1"></i> Crear orden de adeudo
                    </button>
                </form>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover table-bordered mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th>Entidad</th>
                        <th>Set</th>
                        <th class="text-end">Importe</th>
                        <th>Estado</th>
                        <th>Remesa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($billingAllCharges as $charge)
                        <tr>
                            <td>{{ $charge->id }}</td>
                            <td>{{ $charge->created_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                <strong>{{ $charge->conceptLabel() }}</strong>
                                @if($charge->description)
                                    <div class="small text-muted">{{ $charge->description }}</div>
                                @endif
                            </td>
                            <td>{{ $charge->entity->name ?? '—' }}</td>
                            <td>{{ $charge->set_id ? '#'.$charge->set_id : '—' }}</td>
                            <td class="text-end">{{ number_format($charge->amount, 2, ',', '.') }}€</td>
                            <td><span class="badge {{ $charge->statusBadgeClass() }}">{{ $charge->statusLabel() }}</span></td>
                            <td>
                                @if($charge->billing_direct_debit_order_id)
                                    <a href="{{ $configUrl(['billing_order_id' => $charge->billing_direct_debit_order_id]) }}">
                                        Adeudo #{{ $charge->billing_direct_debit_order_id }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-muted text-center py-4">No hay cargos para esta administración con el filtro seleccionado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>

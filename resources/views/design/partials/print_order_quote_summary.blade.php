{{-- Resumen de presupuesto / factura de pedido imprenta (solo lectura). --}}
@php
    $quote = is_array($quote ?? null) ? $quote : [];
    $subtotal = is_array($quote['subtotal'] ?? null) ? $quote['subtotal'] : [];
    $shopName = $quote['print_configuration_name'] ?? ($cfg->displayName() ?? ($printOrder->printConfiguration?->displayName() ?? 'Imprenta'));
    $setName = $design->set->set_name ?? ($printOrder->set->set_name ?? ('#'.($printOrder->set_id ?? $design->set_id ?? '—')));
    $includeBack = $includeBackInPrint ?? (($printOrder->back_mode ?? 'none') !== 'none');
@endphp

<h5 class="mb-1">{{ $summaryTitle ?? 'Resumen de presupuesto' }}</h5>
<p class="text-muted small mb-3">{{ $shopName }}</p>

@if(!empty($introAlert))
    <div class="alert alert-success small">{{ $introAlert }}</div>
@endif

<div class="d-flex justify-content-between small mb-2">
    <span>Pagador</span>
    <strong>{{ $printPayment['payer_label'] ?? '—' }}</strong>
</div>
<div class="d-flex justify-content-between small mb-2">
    <span>Modo de pago</span>
    <strong>{{ $printPayment['payment_mode_label'] ?? '—' }}</strong>
</div>
<div class="d-flex justify-content-between small mb-2">
    <span>Orden</span>
    <strong>{{ $printOrder->order_code ?? '—' }}</strong>
</div>
<div class="d-flex justify-content-between small mb-2">
    <span>Set</span>
    <strong>{{ $setName }}</strong>
</div>
<div class="d-flex justify-content-between small mb-2">
    <span>Participaciones</span>
    <strong>{{ number_format((int) ($quote['total_participations'] ?? 0), 0, ',', '.') }}</strong>
</div>
<div class="d-flex justify-content-between small mb-2">
    <span>Tacos estimados</span>
    <strong>{{ $quote['books'] ?? 0 }}</strong>
</div>
@if(!empty($printOrder->print_size))
<div class="d-flex justify-content-between small mb-2">
    <span>Formato</span>
    <strong>{{ strtoupper(str_replace('_', ' ', (string) $printOrder->print_size)) }}</strong>
</div>
@endif
@if(!empty($printOrder->participations_per_book))
<div class="d-flex justify-content-between small mb-2">
    <span>Participaciones por taco</span>
    <strong>{{ $printOrder->participations_per_book }}</strong>
</div>
@endif
<hr>
<div class="d-flex justify-content-between small mb-2 align-items-start">
    <span>
        Diseño
        @if(!empty($quote['design_fee_waived']))
            <span class="d-block text-muted fw-normal" style="font-size:0.85em;">Sin cargo (realizado en PARTILOT)</span>
        @endif
    </span>
    <strong>{{ number_format((float) ($subtotal['design'] ?? 0), 2, ',', '.') }}€</strong>
</div>
<div class="d-flex justify-content-between small mb-2">
    <span>Participaciones</span>
    <strong>{{ number_format((float) ($subtotal['participation'] ?? 0), 2, ',', '.') }}€</strong>
</div>
@if($includeBack)
<div class="d-flex justify-content-between small mb-2">
    <span>Trasera</span>
    <strong>{{ number_format((float) ($subtotal['back'] ?? 0), 2, ',', '.') }}€</strong>
</div>
@endif
<div class="d-flex justify-content-between small mb-2">
    <span>Tacos</span>
    <strong>{{ number_format((float) ($subtotal['book'] ?? 0), 2, ',', '.') }}€</strong>
</div>
<hr>
<div class="d-flex justify-content-between mb-3">
    <span class="fw-semibold">TOTAL</span>
    <strong class="fs-5">{{ number_format((float) ($quote['total'] ?? $printOrder->quoted_amount ?? 0), 2, ',', '.') }}€</strong>
</div>

@if(!empty($printOrder->notes))
    <div class="small text-muted mb-3" style="white-space: pre-wrap;"><strong>Observaciones:</strong><br>{{ $printOrder->notes }}</div>
@endif

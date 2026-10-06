@extends('emails.layouts.base')

@php($title = 'Liquidación Vendedor - Partilot')
@php($heading = $isFullySettled ? 'Liquidación completada' : 'Actualización de liquidación')
@php($concept = in_array($settlement->notes, \App\Services\SellerSettlementRegistrationService::DEFAULT_NOTES, true) ? null : $settlement->notes)

@section('content')
<p>Hola {{ $seller->name ?? 'Vendedor' }},</p>
@if($isFullySettled)
<p>Tu liquidación está completada y el saldo pendiente ha quedado en <strong>0,00 €</strong>.</p>
@else
<p>Se ha registrado una entrega de dinero a cuenta de tus participaciones.</p>
@endif
<div class="info-box">
    @if($settlement->lottery)
    <p><strong>Sorteo:</strong> {{ $settlement->lottery->name }}</p>
    @endif
    <p><strong>Fecha:</strong> {{ $settlement->settlement_date?->format('d/m/Y') }}</p>
    <p><strong>Importe registrado:</strong> {{ number_format((float)$settlement->paid_amount, 2, ',', '.') }} €</p>
    @if($settlement->payments->isNotEmpty())
    <p><strong>Forma de pago:</strong> {{ $settlement->payments->map(fn ($p) => ucfirst($p->payment_method).' '.number_format((float) $p->amount, 2, ',', '.').' €')->implode(', ') }}</p>
    @endif
    @if($concept)
    <p><strong>Concepto:</strong> {{ $concept }}</p>
    @endif
    <p><strong>Pendiente:</strong> {{ number_format(max(0, (float)$settlement->pending_amount), 2, ',', '.') }} €</p>
</div>
@endsection

@extends('emails.layouts.base')

@php
    $quote = is_array($printOrder->quote_breakdown ?? null) ? $printOrder->quote_breakdown : [];
    $subtotal = is_array($quote['subtotal'] ?? null) ? $quote['subtotal'] : [];
    $title = 'Solicitud de pago de impresión - Partilot';
    $heading = 'Solicitud de pago de impresión';
@endphp

@section('content')
<p>Hola,</p>
<p>La imprenta ha <strong>aceptado</strong> el pedido de impresión. Puede revisar el presupuesto y proceder al pago para iniciar la producción.</p>

<div class="info-box">
    <p><strong>Código de orden:</strong> {{ $printOrder->order_code ?? '—' }}</p>
    <p><strong>Entidad:</strong> {{ $printOrder->entity?->name ?? '—' }}</p>
    <p><strong>Set:</strong> {{ $printOrder->set?->set_name ?? ('#'.$printOrder->set_id) }}</p>
    <p><strong>Imprenta:</strong> {{ $printOrder->printConfiguration?->displayName() ?? '—' }}</p>
    <p><strong>Participaciones:</strong> {{ number_format((int) ($quote['total_participations'] ?? 0), 0, ',', '.') }}</p>
    <p><strong>Tacos estimados:</strong> {{ $quote['books'] ?? '—' }}</p>
    <hr style="border:none;border-top:1px solid #ddd;margin:12px 0;">
    <p><strong>Diseño:</strong> {{ number_format((float) ($subtotal['design'] ?? 0), 2, ',', '.') }} €</p>
    <p><strong>Participaciones:</strong> {{ number_format((float) ($subtotal['participation'] ?? 0), 2, ',', '.') }} €</p>
    @if(($printOrder->back_mode ?? 'none') !== 'none')
    <p><strong>Trasera:</strong> {{ number_format((float) ($subtotal['back'] ?? 0), 2, ',', '.') }} €</p>
    @endif
    <p><strong>Tacos:</strong> {{ number_format((float) ($subtotal['book'] ?? 0), 2, ',', '.') }} €</p>
    <p style="margin-top:12px;font-size:18px;"><strong>Total:</strong> {{ number_format((float) ($quote['total'] ?? $printOrder->quoted_amount ?? 0), 2, ',', '.') }} €</p>
    @if(!empty($printOrder->notes))
    <p><strong>Observaciones:</strong><br>{!! nl2br(e($printOrder->notes)) !!}</p>
    @endif
    <p style="margin-top:12px;"><a href="{{ $payUrl }}" style="display:inline-block;padding:10px 18px;background:#333;color:#fff;text-decoration:none;border-radius:8px;">Pagar pedido</a></p>
</div>

<p style="font-size:13px;color:#666;">Hasta completar el pago, la imprenta no iniciará la producción.</p>
@endsection

@extends('emails.layouts.base')

@php($title = 'Nuevo pedido de impresión - Partilot')
@php($heading = 'Nuevo pedido de impresión')

@section('content')
<p>Hola {{ $printOrder->printConfiguration?->displayName() ?? 'Imprenta' }},</p>

@if($heldForManagementFee)
<p>Se ha registrado un nuevo pedido de impresión. <strong>Quedará visible en su panel cuando la entidad abone la cuota de gestión PARTILOT.</strong> Le avisamos desde ya para que pueda prepararse.</p>
@else
<p>Ha recibido un nuevo pedido de impresión en Partilot. Ya puede consultarlo y gestionarlo desde su panel.</p>
@endif

<div class="info-box">
    <p><strong>Código de orden:</strong> {{ $printOrder->order_code ?? '—' }}</p>
    <p><strong>Entidad:</strong> {{ $printOrder->entity?->name ?? '—' }}</p>
    <p><strong>Set:</strong> {{ $printOrder->set?->set_name ?? ('#'.$printOrder->set_id) }}</p>
    <p><strong>Sorteo:</strong> {{ $printOrder->lottery?->name ?? '—' }}</p>
    <p><strong>Importe presupuestado:</strong> {{ number_format((float) ($printOrder->quoted_amount ?? 0), 2, ',', '.') }} €</p>
    <p><strong>Formato:</strong> {{ strtoupper(str_replace('_', ' ', (string) ($printOrder->print_size ?? '—'))) }}</p>
    <p><strong>Participaciones por taco:</strong> {{ $printOrder->participations_per_book ?? '—' }}</p>
    @if(!empty($printOrder->notes))
    <p><strong>Observaciones:</strong><br>{!! nl2br(e($printOrder->notes)) !!}</p>
    @endif
    <p style="margin-top:12px;"><a href="{{ $panelUrl }}" style="display:inline-block;padding:10px 18px;background:#333;color:#fff;text-decoration:none;border-radius:8px;">Ver pedido en el panel</a></p>
</div>

@if($heldForManagementFee)
<p style="font-size:13px;color:#666;">Hasta que se liquide la cuota de gestión, el pedido no aparecerá en su listado de órdenes activas.</p>
@endif

<p style="font-size:13px;color:#666;">Este es un correo automático. Inicie sesión con su cuenta de imprenta si el enlace le pide autenticación.</p>
@endsection

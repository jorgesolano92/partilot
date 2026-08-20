@extends('emails.layouts.base')

@php
    $title = 'Pedido de impresión rechazado - Partilot';
    $heading = 'Pedido de impresión rechazado';
@endphp

@section('content')
<p>Hola,</p>
<p>La imprenta ha <strong>rechazado</strong> el pedido de impresión. Revise el motivo indicado y, si procede, corrija el diseño o los datos del pedido y vuelva a enviarlo.</p>

<div class="info-box">
    <p><strong>Código de orden:</strong> {{ $printOrder->order_code ?? '—' }}</p>
    <p><strong>Entidad:</strong> {{ $printOrder->entity?->name ?? '—' }}</p>
    <p><strong>Set:</strong> {{ $printOrder->set?->set_name ?? ('#'.$printOrder->set_id) }}</p>
    <p><strong>Imprenta:</strong> {{ $printOrder->printConfiguration?->displayName() ?? '—' }}</p>
    @if(filled($printOrder->rejection_reason))
    <p><strong>Motivo del rechazo:</strong><br>{!! nl2br(e($printOrder->rejection_reason)) !!}</p>
    @endif
    @if($summaryUrl !== '')
    <p style="margin-top:12px;"><a href="{{ $summaryUrl }}" style="display:inline-block;padding:10px 18px;background:#333;color:#fff;text-decoration:none;border-radius:8px;">Ver diseño y pedido</a></p>
    @endif
</div>
@endsection

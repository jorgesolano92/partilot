@extends('emails.layouts.base')

@php($title = 'Donación y Código de Recarga - Partilot')
@php($heading = 'Donación y código registrados')

@section('content')
<p>Hola {{ $donation->user->name ?? 'Usuario' }},</p>
<p>Tu gestión se ha registrado correctamente.</p>
<div class="info-box">
    <p><strong>Donación:</strong> {{ number_format((float)$donation->importe_donacion, 2, ',', '.') }} €</p>
    @if((float) ($donation->importe_codigo ?? 0) > 0)
    <p><strong>Importe del código:</strong> {{ number_format((float)$donation->importe_codigo, 2, ',', '.') }} €</p>
    @endif
    <p><strong>Código recarga:</strong> {{ $donation->codigo_recarga ?: 'No aplica' }}</p>
</div>
@endsection

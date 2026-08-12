@extends('emails.layouts.base')

@php($title = 'Acceso panel imprenta - Partilot')
@php($heading = 'Acceso al panel de imprenta')

@section('content')
<p>Hola {{ $printConfiguration->displayName() }},</p>
<p>Su acceso al panel de Partilot para gestionar pedidos de impresión está listo.</p>
<div class="info-box">
    <p><strong>Usuario de acceso al panel:</strong> {{ $user->panel_login_username ?? '—' }}</p>
    <p><strong>Email de acceso:</strong> {{ $user->email }}</p>
    @if(!empty($plainPassword))
    <p><strong>Contraseña de acceso:</strong> {{ $plainPassword }}</p>
    @endif
    <p style="margin-top:12px;"><a href="{{ $loginUrl }}" style="display:inline-block;padding:10px 18px;background:#333;color:#fff;text-decoration:none;border-radius:8px;">Iniciar sesión</a></p>
</div>
@if(!empty($plainPassword))
<p>Guarde esta contraseña en un lugar seguro. Si ya tenía acceso, la contraseña anterior deja de ser válida.</p>
@else
<p>Puede iniciar sesión con su contraseña habitual. Si no la recuerda, contacte con el administrador de Partilot.</p>
@endif
<p style="font-size:13px;color:#666;">No comparta este correo. Tras iniciar sesión accederá al panel de imprenta para ver y gestionar los pedidos.</p>
@endsection

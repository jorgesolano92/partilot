@extends('emails.layouts.base')

@php
    $roleType = $pending->is_primary ? 'gestor_responsable' : 'gestor';
    $role = config("legal_roles.{$roleType}", []);
@endphp
@php($title = 'Invitación gestor - Partilot')
@php($heading = $pending->is_primary ? 'Designación como Gestor Responsable' : 'Invitación como gestor')

@section('content')
<p>Hola,</p>
<p>Has sido invitado/a a ser <strong>{{ $pending->is_primary ? 'gestor responsable' : 'gestor' }}</strong> de la entidad <strong>{{ $entity->name }}</strong> en Partilot.</p>
@if(!empty($role['summary_bullets']))
<ul>
    @foreach($role['summary_bullets'] as $bullet)
        <li>{{ $bullet }}</li>
    @endforeach
</ul>
@endif
<div class="info-box">
    <p><strong>Email de la invitación:</strong> {{ $invitedEmail }}</p>
    <p>Si <strong>aceptas</strong>, completarás un breve registro con ese correo (nombre, DNI, teléfono y contraseña) y quedarás vinculado/a a la entidad.</p>
    <p>Si <strong>rechazas</strong>, la invitación se cancelará y no se creará ninguna cuenta.</p>
</div>
<p style="text-align:center; margin: 24px 0;">
    <a href="{{ $acceptUrl }}" style="display:inline-block;padding:10px 18px;background:#198754;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;margin-right:8px;">Aceptar y registrarme</a>
    <a href="{{ $rejectUrl }}" style="display:inline-block;padding:10px 18px;background:#dc3545;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Rechazar</a>
</p>
<p style="font-size: 13px; color:#666;">Si no esperabas esta invitación, puedes rechazarla o ignorar este mensaje.</p>
@endsection

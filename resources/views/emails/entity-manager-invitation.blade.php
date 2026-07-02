@extends('emails.layouts.base')

@php
    $roleType = $manager->pending_primary || $manager->is_primary ? 'gestor_responsable' : 'gestor';
    $role = config("legal_roles.{$roleType}", []);
    $adminName = $entity->administration->name ?? 'Tu administración';
@endphp
@php($title = 'Invitación - Partilot')
@php($heading = $manager->pending_primary ? 'Designación como Gestor Responsable' : 'Invitación como Gestor')

@section('content')
<p>Hola {{ $managerUser->name ?? 'Gestor' }},</p>
@if($manager->pending_primary)
<p><strong>{{ $adminName }}</strong> te ha designado <strong>Gestor Responsable</strong> de la entidad <strong>{{ $entity->name }}</strong> en PARTILOT.</p>
<p>Este cargo implica responsabilidades importantes. Léelas antes de aceptar:</p>
@else
<p>Te han invitado a colaborar como <strong>Gestor</strong> en la entidad <strong>{{ $entity->name }}</strong> en PARTILOT.</p>
@endif
@if(!empty($role['summary_bullets']))
<ul>
    @foreach($role['summary_bullets'] as $bullet)
        <li>{{ $bullet }}</li>
    @endforeach
</ul>
@endif
<div class="info-box">
    @if($manager->requires_password_setup)
        <p>Para activar tu acceso, abre el enlace <strong>Ver detalles y aceptar</strong>, confirma la solicitud y define tu contraseña.</p>
    @else
        <p>Para activar tu acceso, abre el enlace y confirma o rechaza la solicitud.</p>
    @endif
</div>
<p style="text-align:center; margin: 24px 0;">
    <a href="{{ $acceptUrl }}" style="display:inline-block;padding:10px 18px;background:#198754;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;margin-right:8px;">Ver detalles y aceptar</a>
    <a href="{{ $rejectUrl }}" style="display:inline-block;padding:10px 18px;background:#dc3545;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Rechazar</a>
</p>
<p style="font-size: 13px; color:#666;">Si no reconoces esta invitación, puedes rechazarla o ignorar este correo.</p>
@endsection

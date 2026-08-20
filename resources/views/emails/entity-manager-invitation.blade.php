@extends('emails.layouts.base')

@php
    $roleType = $manager->pending_primary || $manager->is_primary ? 'gestor_responsable' : 'gestor';
    $role = config("legal_roles.{$roleType}", []);
    $adminName = $entity->administration->name ?? 'Tu administración';
    $isResponsible = $manager->pending_primary || $manager->is_primary;
@endphp
@php($title = 'Invitación - Partilot')
@php($heading = $isResponsible ? 'Designación como Gestor Responsable' : 'Invitación como Gestor')

@section('content')
<p>Hola {{ $managerUser->name ?? 'Gestor' }},</p>
@if($isResponsible)
<p><strong>{{ $adminName }}</strong> te ha designado <strong>Gestor Responsable</strong> de la entidad <strong>{{ $entity->name }}</strong> en PARTILOT.</p>
<p>El contrato marco ya ha sido firmado por el representante autorizado. Para activar la entidad debes <strong>aceptar el cargo de Gestor Responsable</strong>.</p>
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
    @if(!empty($provisionalPassword))
        <p><strong>Email de acceso al panel:</strong> {{ $managerUser->email }}</p>
        <p><strong>Contraseña provisional:</strong> {{ $provisionalPassword }}</p>
        <p>Esta contraseña es temporal. Al iniciar sesión podrá cambiarla o posponer el cambio.</p>
        <p>Además, debe <strong>aceptar o rechazar</strong> la invitación como gestor usando los botones de abajo.</p>
    @else
        <p>Para activar tu acceso como gestor, abre el enlace y <strong>confirma o rechaza</strong> la solicitud. No necesitas definir una nueva contraseña: usa la de tu cuenta existente.</p>
    @endif
</div>
<p style="text-align:center; margin: 24px 0;">
    <a href="{{ $acceptUrl }}" style="display:inline-block;padding:10px 18px;background:#198754;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;margin-right:8px;">Ver detalles y aceptar</a>
    <a href="{{ $rejectUrl }}" style="display:inline-block;padding:10px 18px;background:#dc3545;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Rechazar</a>
</p>
<p style="font-size: 13px; color:#666;">Si no reconoces esta invitación, puedes rechazarla o ignorar este correo.</p>
@endsection

@extends('emails.layouts.base')

@php($title = 'Cargo aceptado - Partilot')
@php($heading = 'Gestor Responsable confirmado')

@section('content')
<p>Hola {{ $managerUser->name }},</p>
<p>Has aceptado el cargo de <strong>Gestor Responsable</strong> de <strong>{{ $entity->name }}</strong> correctamente.</p>
<p>Ya puedes empezar a gestionar la entidad desde tu panel (con tu usuario de gestor):</p>
<p style="text-align:center; margin: 24px 0;">
    <a href="{{ route('login') }}" style="display:inline-block;padding:10px 18px;background:#198754;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Acceder al panel</a>
</p>
<p style="font-size: 13px; color:#555; background:#f8f9fa; padding:12px; border-radius:8px;">
    <strong>Acceso de la entidad:</strong> a continuación se enviará (o se ha enviado) un correo aparte
    a la dirección de la entidad con el <strong>usuario y contraseña de acceso al panel</strong> de esa entidad.
</p>
<p style="font-size: 13px; color:#666;">Conserva este correo como confirmación del cargo aceptado.</p>
@endsection

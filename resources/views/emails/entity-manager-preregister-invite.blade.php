@extends('emails.layouts.base')

@php($title = 'Invitación gestor - Partilot')
@php($heading = 'Invitación como gestor')

@section('content')
<p>Hola,</p>
<p>Has sido invitado/a a ser <strong>gestor</strong> de la entidad <strong>{{ $entity->name }}</strong> en Partilot.</p>
<div class="info-box">
    <p><strong>Aún no tenemos una cuenta registrada con el email {{ $invitedEmail }}.</strong></p>
    <p>Para vincularte automáticamente, <strong>regístrate</strong> usando <strong>exactamente este mismo correo</strong>: <strong>{{ $invitedEmail }}</strong>.</p>
    <p style="margin-top:12px;"><a href="{{ $registerHintUrl }}" style="display:inline-block;padding:10px 18px;background:#333;color:#fff;text-decoration:none;border-radius:8px;">Registrarse / acceder</a></p>
</div>
<p>Cuando completes el registro con ese email, recibirás un correo para <strong>aceptar o rechazar</strong> la invitación como gestor (sin definir una nueva contraseña si ya la estableciste al registrarte).</p>
<p style="font-size: 13px; color:#666;">Si no esperabas esta invitación, puedes ignorar este mensaje.</p>
@endsection

@extends('emails.layouts.base')

@php($title = 'Rol aceptado - Partilot')
@php($heading = 'Invitación aceptada')

@section('content')
<p>Hola {{ $managerUser->name }},</p>
<p>Has aceptado la invitación para colaborar como <strong>Gestor</strong> en <strong>{{ $entity->name }}</strong>.</p>
<p>Ya puedes acceder al panel con las funciones que te haya delegado el Gestor Responsable.</p>
<p style="text-align:center; margin: 24px 0;">
    <a href="{{ route('login') }}" style="display:inline-block;padding:10px 18px;background:#198754;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Acceder al panel</a>
</p>
@endsection

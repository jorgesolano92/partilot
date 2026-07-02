@extends('emails.layouts.base')

@php($title = 'Cargo aceptado - Partilot')
@php($heading = 'Gestor Responsable confirmado')

@section('content')
<p>Hola {{ $managerUser->name }},</p>
<p>Has aceptado el cargo de <strong>Gestor Responsable</strong> de <strong>{{ $entity->name }}</strong> correctamente.</p>
<p>Ya puedes empezar a gestionar la entidad desde tu panel:</p>
<p style="text-align:center; margin: 24px 0;">
    <a href="{{ route('login') }}" style="display:inline-block;padding:10px 18px;background:#198754;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Acceder al panel</a>
</p>
<p style="font-size: 13px; color:#666;">Conserva este correo como confirmación del cargo aceptado.</p>
@endsection

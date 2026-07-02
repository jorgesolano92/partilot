@extends('emails.layouts.base')

@php($title = 'Cargo rechazado - Partilot')
@php($heading = 'Designación rechazada')

@section('content')
<p>Hola,</p>
<p><strong>{{ trim(($rejectedUser->name ?? '').' '.($rejectedUser->last_name ?? '')) ?: 'El usuario designado' }}</strong> ha rechazado el cargo de Gestor Responsable de <strong>{{ $entity->name }}</strong>.</p>
<p>Deberás designar a otra persona para que la entidad pueda operar.</p>
<p style="text-align:center; margin: 24px 0;">
    <a href="{{ $designateUrl }}" style="display:inline-block;padding:10px 18px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Designar nuevo gestor responsable</a>
</p>
@endsection

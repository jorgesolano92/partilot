@extends('emails.layouts.base')

@php($title = 'Acceso panel entidad - Partilot')
@php($heading = 'Acceso al panel de su entidad')

@section('content')
<p>Hola {{ $entity->name ?? 'Entidad' }},</p>
<p>Su acceso al panel de Partilot para la entidad <strong>{{ $entity->name }}</strong> está listo.</p>
<div class="info-box">
    <p><strong>Email de acceso al panel:</strong> {{ $user->email }}</p>
    @if(!empty($plainPassword))
    <p><strong>Contraseña provisional:</strong> {{ $plainPassword }}</p>
    @endif
    <p style="margin-top:12px;"><a href="{{ $loginUrl }}" style="display:inline-block;padding:10px 18px;background:#333;color:#fff;text-decoration:none;border-radius:8px;">Iniciar sesión</a></p>
</div>
<p>Esta contraseña es <strong>temporal</strong>. Al iniciar sesión podrá cambiarla o posponer el cambio; se le volverá a recordar en futuros accesos hasta que la actualice.</p>
<p style="font-size:13px;color:#666;">No comparta este correo. Si no esperaba este mensaje, contacte con su administración de lotería.</p>
@endsection

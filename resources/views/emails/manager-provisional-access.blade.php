@extends('emails.layouts.base')

@section('content')
    <p>Hola {{ $user->name }},</p>
    <p>Se ha creado su cuenta en Partilot ({{ $contextLabel }}).</p>
    <p><strong>Usuario:</strong> {{ $user->email }}</p>
    <p>Para empezar, cree su contraseña desde este enlace personal. Solo se puede usar una vez y caduca en {{ $expiresInDays }} {{ $expiresInDays === 1 ? 'día' : 'días' }}.</p>
    <p><a href="{{ $setPasswordUrl }}">Crear mi contraseña</a></p>
    <p>Si el enlace ha caducado, use «¿Olvidaste tu contraseña?» en la pantalla de acceso con este mismo email.</p>
@endsection

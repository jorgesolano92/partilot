@extends('emails.layouts.base')

@section('content')
    <p>Hola {{ $user->name }},</p>
    <p>Ha solicitado restablecer la contraseña de su cuenta en Partilot.</p>
    <p>Pulse el siguiente enlace para elegir una nueva contraseña. El enlace caduca en 60 minutos.</p>
    <p><a href="{{ $resetUrl }}">Restablecer contraseña</a></p>
    <p>Si no ha solicitado este cambio, puede ignorar este correo.</p>
@endsection

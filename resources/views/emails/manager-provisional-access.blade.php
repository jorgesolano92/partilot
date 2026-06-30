@extends('emails.layouts.base')

@section('content')
    <p>Hola {{ $user->name }},</p>
    <p>Se ha creado su cuenta de gestor en Partilot ({{ $contextLabel }}).</p>
    <p><strong>Usuario:</strong> {{ $user->email }}</p>
    <p><strong>Contraseña provisional:</strong> {{ $plainPassword }}</p>
    <p>Por seguridad, inicie sesión y cambie esta contraseña lo antes posible.</p>
    <p><a href="{{ url('/login') }}">Acceder al panel</a></p>
@endsection

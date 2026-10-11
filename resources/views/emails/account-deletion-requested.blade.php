@extends('emails.layouts.base')

@php($title = 'Solicitud de baja - Partilot')
@php($heading = 'Solicitud de baja recibida')

@section('content')
<p>Hola {{ $user->name ?? 'Usuario' }},</p>
<p>Hemos recibido una solicitud para eliminar tu cuenta de Partilot. La cuenta queda desactivada desde este momento.</p>
<div class="info-box">
    <p>Los datos se eliminarán definitivamente el <strong>{{ optional($user->deletion_scheduled_at)->format('d/m/Y') }}</strong>.</p>
    <p>Hasta esa fecha puedes reactivar la cuenta desde la pantalla de acceso de la app, introduciendo tu email y contraseña.</p>
</div>
<p>Si no has sido tú, reactiva la cuenta cuanto antes y cambia tu contraseña.</p>
@endsection

@extends('emails.layouts.base')

@php
    $entityName = $entity->name ?? 'la entidad';
    $roleLabel = match ($roleType) {
        'gestor_responsable' => 'Gestor Responsable',
        'vendedor' => 'Vendedor',
        default => 'Gestor',
    };
@endphp
@php($title = 'Recordatorio - Partilot')
@php($heading = 'Solicitud pendiente')

@section('content')
<p>Hola {{ $invitedUser->name ?? 'Usuario' }},</p>
<p>Aún tienes una solicitud pendiente de respuesta: colaborar como <strong>{{ $roleLabel }}</strong> en <strong>{{ $entityName }}</strong>.</p>
@if($roleType === 'gestor_responsable')
<p>La entidad no puede operar con normalidad hasta que aceptes o rechaces el cargo.</p>
@endif
<p style="text-align:center; margin: 24px 0;">
    <a href="{{ $acceptUrl }}" style="display:inline-block;padding:10px 18px;background:#198754;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Ver solicitud</a>
</p>
@endsection

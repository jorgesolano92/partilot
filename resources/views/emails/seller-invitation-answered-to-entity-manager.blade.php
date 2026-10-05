<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $accepted ? 'Invitación de vendedor aceptada' : 'Invitación de vendedor rechazada' }} - Partilot</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; background:#f4f4f4; }
        .container { background:#fff; padding: 28px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        .header { border-bottom: 2px solid #007bff; padding-bottom: 18px; margin-bottom: 18px; }
        .header h1 { margin: 0; color:#007bff; font-size: 20px; }
        .box { background:#f8f9fa; border-left: 4px solid {{ $accepted ? '#198754' : '#dc3545' }}; padding: 14px 16px; margin: 14px 0; }
        .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #ddd; color:#666; font-size: 12px; }
        p { margin: 6px 0; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>{{ $accepted ? 'Invitación de vendedor aceptada' : 'Invitación de vendedor rechazada' }}</h1>
    </div>

    @php
        $managerName = trim(($managerUser?->name ?? '') . ' ' . ($managerUser?->last_name ?? ''));
        $managerName = $managerName !== '' ? $managerName : 'Gestor';
        $sellerName = trim((string) $seller->full_name);
        if ($sellerName === '' || $sellerName === 'Sin nombre') {
            $sellerName = (string) ($seller->email ?? 'El vendedor');
        }
    @endphp

    <p>Hola <strong>{{ $managerName }}</strong>,</p>

    @if($accepted)
        <p><strong>{{ $sellerName }}</strong> ha aceptado la invitación para ser vendedor de <strong>{{ $entity->name }}</strong>.</p>
    @else
        <p><strong>{{ $sellerName }}</strong> ha rechazado la invitación para ser vendedor de <strong>{{ $entity->name }}</strong>.</p>
    @endif

    <div class="box">
        <p><strong>Vendedor:</strong> {{ $sellerName }}</p>
        <p><strong>Email:</strong> {{ $seller->display_email ?: ($seller->email ?? '-') }}</p>
        <p><strong>Entidad:</strong> {{ $entity->name }}</p>
        <p><strong>Fecha:</strong> {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    @if($accepted)
        <p>Ya puedes asignarle participaciones desde el panel de Partilot.</p>
    @else
        <p>El vendedor queda en tu listado como «Rechazado». Si fue un error, puedes volver a invitarle con el mismo email.</p>
    @endif

    <div class="footer">
        <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
        <p>&copy; {{ date('Y') }} Partilot</p>
    </div>
</div>
</body>
</html>

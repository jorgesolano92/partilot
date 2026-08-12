<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diseño aprobado - Partilot</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; background:#f4f4f4; }
        .container { background:#fff; padding: 28px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        .header { border-bottom: 2px solid #28a745; padding-bottom: 18px; margin-bottom: 18px; }
        .header h1 { margin: 0; color:#28a745; font-size: 20px; }
        .box { background:#f0fff4; border-left: 4px solid #28a745; padding: 14px 16px; margin: 14px 0; }
        .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #ddd; color:#666; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Diseño aprobado - Partilot</h1>
    </div>

    @php
        $managerName = trim(($design->entity?->manager?->user?->name ?? '') . ' ' . ($design->entity?->manager?->user?->last_name ?? ''));
        $managerName = $managerName !== '' ? $managerName : ($design->entity?->manager?->user?->email ?? 'Gestor');
    @endphp

    <p>Hola <strong>{{ $managerName }}</strong>,</p>

    <p>La administración ha recibido la confirmación de aprobación del <strong>diseño</strong> del set <strong>{{ $design->set->set_name ?? ('#'.$design->set_id) }}</strong> de la entidad <strong>{{ $design->entity?->name ?? '—' }}</strong>.</p>

    @if($design->lottery)
        <div class="box">
            <p><strong>Sorteo:</strong> {{ $design->lottery->name ?? '—' }}</p>
            <p><strong>Fecha sorteo:</strong> {{ $design->lottery->draw_date ? \Carbon\Carbon::parse($design->lottery->draw_date)->format('d/m/Y') : '-' }}</p>
        </div>
    @endif

    <div class="footer">
        <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
        <p>&copy; {{ date('Y') }} Partilot</p>
    </div>
</div>
</body>
</html>


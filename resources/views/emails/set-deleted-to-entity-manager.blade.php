<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set eliminado - Partilot</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; background:#f4f4f4; }
        .container { background:#fff; padding: 28px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        .header { border-bottom: 2px solid #dc3545; padding-bottom: 18px; margin-bottom: 18px; }
        .header h1 { margin: 0; color:#dc3545; font-size: 20px; }
        .box { background:#fff5f5; border-left: 4px solid #dc3545; padding: 14px 16px; margin: 14px 0; }
        .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #ddd; color:#666; font-size: 12px; }
        .grid { display:flex; gap: 24px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 240px; }
        p { margin: 6px 0; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Set eliminado - Partilot</h1>
    </div>

    @php
        $managerName = trim(($set->entity?->manager?->user?->name ?? '') . ' ' . ($set->entity?->manager?->user?->last_name ?? ''));
        $managerName = $managerName !== '' ? $managerName : ($set->entity?->manager?->user?->email ?? 'Gestor');
        $lottery = $set->reserve?->lottery;
    @endphp

    <p>Hola <strong>{{ $managerName }}</strong>,</p>

    <p>Te informamos de que se ha <strong>eliminado</strong> un <strong>set</strong> en la entidad <strong>{{ $set->entity?->name ?? '-' }}</strong>.</p>

    @if(!empty($deletionReason))
        <div class="box">
            <p><strong>Motivo:</strong> {{ $deletionReason }}</p>
        </div>
    @endif

    <div class="box">
        <div class="grid">
            <div class="col">
                <p><strong>Set:</strong> {{ $set->set_name ?? '-' }}</p>
                <p><strong>Sorteo:</strong> {{ $lottery?->name ?? 'N/A' }}</p>
                <p><strong>Fecha sorteo:</strong> {{ $lottery?->draw_date ? \Carbon\Carbon::parse($lottery->draw_date)->format('d/m/Y') : '-' }}</p>
                <p><strong>Cierre de venta:</strong> {{ $set->deadline_date ? \Carbon\Carbon::parse($set->deadline_date)->format('d/m/Y') : '-' }}</p>
            </div>
            <div class="col">
                <p><strong>Participaciones:</strong> {{ (int)($set->total_participations ?? 0) }}</p>
                <p><strong>Físicas:</strong> {{ (int)($set->physical_participations ?? 0) }}</p>
                <p><strong>Digitales:</strong> {{ (int)($set->digital_participations ?? 0) }}</p>
            </div>
        </div>

        <p><strong>Importes:</strong></p>
        <p>Importe jugado: {{ number_format((float)($set->played_amount ?? 0), 2, ',', '.') }} €</p>
        <p>Donativo: {{ number_format((float)($set->donation_amount ?? 0), 2, ',', '.') }} €</p>
        <p>Total participación: {{ number_format((float)($set->total_participation_amount ?? 0), 2, ',', '.') }} €</p>
    </div>

    <div class="footer">
        <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
        <p>&copy; {{ date('Y') }} Partilot</p>
    </div>
</div>
</body>
</html>


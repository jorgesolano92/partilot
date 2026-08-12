<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva eliminada - Partilot</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; background:#f4f4f4; }
        .container { background:#fff; padding: 28px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
        .header { border-bottom: 2px solid #dc3545; padding-bottom: 18px; margin-bottom: 18px; }
        .header h1 { margin: 0; color:#dc3545; font-size: 20px; }
        .box { background:#fff5f5; border-left: 4px solid #dc3545; padding: 14px 16px; margin: 14px 0; }
        table { width:100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #eee; padding: 8px 0; text-align:left; }
        .footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid #ddd; color:#666; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Reserva eliminada - Partilot</h1>
    </div>

    @php
        $managerName = trim(($reserve->entity?->manager?->user?->name ?? '') . ' ' . ($reserve->entity?->manager?->user?->last_name ?? ''));
        $managerName = $managerName !== '' ? $managerName : ($reserve->entity?->manager?->user?->email ?? 'Gestor');
        $lottery = $reserve->lottery;
        $lotteryType = $lottery?->lotteryType;
        $fractionsPerSeries = (int)($lotteryType->billetes_serie ?? 0);
        $ticketsPerNumber = (int)($reserve->reservation_tickets ?? 0);
        $fractionsAssigned = ($fractionsPerSeries > 0) ? ($ticketsPerNumber % $fractionsPerSeries) : $ticketsPerNumber;
        if ($fractionsPerSeries > 0 && $fractionsAssigned === 0 && $ticketsPerNumber > 0) {
            $fractionsAssigned = $fractionsPerSeries;
        }
        $numbers = $reserve->reservation_numbers ?? [];
    @endphp

    <p>Hola <strong>{{ $managerName }}</strong>,</p>

    <p>Te informamos de que se ha <strong>eliminado</strong> una <strong>reserva</strong> para la entidad <strong>{{ $reserve->entity?->name ?? '-' }}</strong>.</p>

    @if(!empty($deletionReason))
        <div class="box">
            <p><strong>Motivo:</strong> {{ $deletionReason }}</p>
        </div>
    @endif

    <div class="box">
        <p><strong>Sorteo:</strong> {{ $lottery?->name ?? 'N/A' }} @if($lottery?->draw_date) ({{ \Carbon\Carbon::parse($lottery->draw_date)->format('d/m/Y') }}) @endif</p>
        <p><strong>Total reservado:</strong> {{ number_format((float)($reserve->total_amount ?? 0), 2, ',', '.') }} €</p>
        <p><strong>Números reservados:</strong> {{ count($numbers) }}</p>
        <p><strong>Dísmos/décimos reservados por número:</strong> {{ $ticketsPerNumber }}</p>
    </div>

    @if(count($numbers) > 0)
        <p><strong>Detalle por número:</strong></p>
        <table>
            <thead>
            <tr>
                <th>Número</th>
                <th>Fracciones</th>
                <th>Total décimos</th>
            </tr>
            </thead>
            <tbody>
            @foreach($numbers as $n)
                <tr>
                    <td>{{ $n }}</td>
                    <td>{{ $fractionsAssigned }}</td>
                    <td>{{ $ticketsPerNumber }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
        <p>&copy; {{ date('Y') }} Partilot</p>
    </div>
</div>
</body>
</html>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Devolución {{ $code }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; line-height: 1.4; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 16px 0 6px; border-bottom: 1px solid #333; padding-bottom: 3px; }
        .muted { color: #555; }
        .meta { margin: 0 0 12px; }
        .meta td { padding: 2px 8px 2px 0; vertical-align: top; }
        table.data { width: 100%; border-collapse: collapse; margin: 0 0 10px; }
        table.data th, table.data td { border: 1px solid #333; padding: 4px 5px; text-align: left; vertical-align: top; }
        table.data th { background: #f2f2f2; font-weight: 600; }
        .summary-box { margin: 0 0 10px; }
        .summary-box span { display: inline-block; margin-right: 14px; }
        .footer-note { margin-top: 18px; font-size: 9px; color: #666; }
    </style>
</head>
<body>
    <h1>Justificante de {{ $isAnnulment ? 'anulación' : 'devolución' }}</h1>
    <p class="muted">Documento individual de este movimiento (no acumulado de la reserva).</p>

    <table class="meta">
        <tr>
            <td><strong>Código:</strong></td>
            <td>{{ $code }}</td>
            <td><strong>Tipo:</strong></td>
            <td>{{ $typeLabel }}</td>
        </tr>
        <tr>
            <td><strong>Entidad:</strong></td>
            <td>{{ $devolution->entity->name ?? 'N/A' }}</td>
            <td><strong>Sorteo:</strong></td>
            <td>{{ $devolution->lottery->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td><strong>Fecha:</strong></td>
            <td>{{ $dateLabel }}</td>
            <td><strong>Generado:</strong></td>
            <td>{{ $generatedAt }}</td>
        </tr>
        @if($reserveId)
        <tr>
            <td><strong>Reserva:</strong></td>
            <td colspan="3">#{{ $reserveId }}@if($reservationNumbers !== '') — {{ $reservationNumbers }}@endif</td>
        </tr>
        @endif
        @if(!$isAnnulment && $devolution->seller)
        <tr>
            <td><strong>Vendedor:</strong></td>
            <td colspan="3">{{ $devolution->seller->full_name ?: $devolution->seller->name }}</td>
        </tr>
        @endif
    </table>

    <div class="summary-box">
        <span><strong>Participaciones:</strong> {{ $counts['total'] }}</span>
        <span><strong>Devueltas:</strong> {{ $counts['devolver'] }}</span>
        <span><strong>Liquidadas:</strong> {{ $counts['vender'] }}</span>
        <span><strong>Anuladas:</strong> {{ $counts['anular'] }}</span>
        @if($liquidation !== null)
            <span><strong>Liquidación:</strong> {{ number_format($liquidation, 2, ',', '.') }} €</span>
        @endif
    </div>

    <h2>Detalle</h2>
    @if(count($rows) === 0)
        <p class="muted">Sin líneas de detalle.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Código</th>
                    <th>Set</th>
                    <th>Acción</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{ $row['number'] }}</td>
                        <td>{{ $row['code'] }}</td>
                        <td>{{ $row['set'] }}</td>
                        <td>{{ $row['action'] }}</td>
                        <td>{{ $row['status'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="footer-note">
        Este PDF corresponde únicamente al movimiento {{ $code }}. Para el acumulado de toda la reserva use el botón «PDF acumulado de la reserva».
    </p>
</body>
</html>

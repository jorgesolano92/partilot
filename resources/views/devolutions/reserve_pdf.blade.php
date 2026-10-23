<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Devoluciones reserva {{ $reserve->id }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; line-height: 1.4; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 16px 0 6px; border-bottom: 1px solid #333; padding-bottom: 3px; }
        h3 { font-size: 11px; margin: 12px 0 4px; }
        .muted { color: #555; }
        .meta { margin: 0 0 12px; }
        .meta td { padding: 2px 8px 2px 0; vertical-align: top; }
        table.data { width: 100%; border-collapse: collapse; margin: 0 0 10px; }
        table.data th, table.data td { border: 1px solid #333; padding: 4px 5px; text-align: left; vertical-align: top; }
        table.data th { background: #f2f2f2; font-weight: 600; }
        .num { text-align: right; }
        .summary-box { margin: 0 0 10px; }
        .summary-box span { display: inline-block; margin-right: 14px; }
        .page-break { page-break-before: always; }
        .footer-note { margin-top: 18px; font-size: 9px; color: #666; }
    </style>
</head>
<body>
    <h1>Albarán acumulativo de devoluciones</h1>
    <p class="muted">Documento regenerado con el acumulado actual de la reserva (incluye anulaciones).</p>

    <table class="meta">
        <tr>
            <td><strong>Entidad:</strong></td>
            <td>{{ $reserve->entity->name ?? 'N/A' }}</td>
            <td><strong>Sorteo:</strong></td>
            <td>{{ $reserve->lottery->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td><strong>Reserva:</strong></td>
            <td>#{{ $reserve->id }} — {{ $reservationNumbers }}</td>
            <td><strong>Generado:</strong></td>
            <td>{{ $generatedAt }}</td>
        </tr>
        <tr>
            <td><strong>Movimientos:</strong></td>
            <td>{{ $totals['movements'] }}</td>
            <td><strong>Acumulado:</strong></td>
            <td>
                Devueltas {{ $totals['devolver'] }}
                · Liquidadas {{ $totals['vender'] }}
                · Anuladas {{ $totals['anular'] }}
            </td>
        </tr>
    </table>

    <h2>Cronología de movimientos</h2>
    @if(count($movements) === 0)
        <p class="muted">No hay devoluciones ni anulaciones registradas para esta reserva.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tipo</th>
                    <th>Fecha</th>
                    <th>Vendedor</th>
                    <th class="num">Total</th>
                    <th class="num">Dev.</th>
                    <th class="num">Liq.</th>
                    <th class="num">Anul.</th>
                    <th class="num">Liquidación</th>
                </tr>
            </thead>
            <tbody>
                @foreach($movements as $m)
                    <tr>
                        <td>{{ $m['code'] }}</td>
                        <td>{{ $m['type'] }}</td>
                        <td>{{ $m['date'] }}</td>
                        <td>{{ $m['seller'] ?: '—' }}</td>
                        <td class="num">{{ $m['total'] }}</td>
                        <td class="num">{{ $m['devolver'] }}</td>
                        <td class="num">{{ $m['vender'] }}</td>
                        <td class="num">{{ $m['anular'] }}</td>
                        <td class="num">
                            @if($m['liquidation'] === null)
                                N/A
                            @else
                                {{ number_format($m['liquidation'], 2, ',', '.') }} €
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Desglose por set</h2>
    @forelse($setSections as $section)
        <h3>{{ $section['set_name'] }}</h3>
        <div class="summary-box">
            <span><strong>Precio unit.:</strong> {{ number_format($section['price'], 2, ',', '.') }} €</span>
            <span><strong>Devueltas:</strong> {{ $section['summary']['devolver'] }}
                ({{ number_format($section['summary']['devolver_amount'], 2, ',', '.') }} €)</span>
            <span><strong>Liquidadas:</strong> {{ $section['summary']['vender'] }}
                ({{ number_format($section['summary']['vender_amount'], 2, ',', '.') }} €)</span>
            <span><strong>Anuladas:</strong> {{ $section['summary']['anular'] }}
                ({{ number_format($section['summary']['anular_amount'], 2, ',', '.') }} €)</span>
        </div>

        @if(count($section['rows']) === 0)
            <p class="muted">Sin participaciones en este set.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Código</th>
                        <th>Acción</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Movimiento</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($section['rows'] as $row)
                        <tr>
                            <td>{{ $row['number'] }}</td>
                            <td>{{ $row['code'] }}</td>
                            <td>{{ $row['action'] }}</td>
                            <td>{{ $row['status'] }}</td>
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $row['devolution'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @empty
        <p class="muted">No hay participaciones asociadas a sets de esta reserva.</p>
    @endforelse

    <p class="footer-note">
        Este PDF refleja el acumulado de la reserva en el momento de la descarga.
        Cualquier devolución o anulación posterior se incluirá al volver a generarlo.
    </p>
</body>
</html>

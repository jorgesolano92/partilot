<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escrutinio {{ $lottery->name }}</title>
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #111; line-height: 1.35; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        h2 { font-size: 12px; margin: 14px 0 6px; border-bottom: 1px solid #333; padding-bottom: 3px; }
        .muted { color: #555; }
        .meta { width: 100%; border-collapse: collapse; margin: 0 0 10px; }
        .meta td { padding: 2px 10px 2px 0; vertical-align: top; }
        .box {
            border: 1px solid #198754;
            background: #f3faf6;
            padding: 8px 10px;
            margin: 0 0 10px;
        }
        .box.warn { border-color: #997404; background: #fff8e6; }
        .box strong { display: block; margin-bottom: 3px; }
        table.data { width: 100%; border-collapse: collapse; margin: 0 0 12px; }
        table.data th, table.data td { border: 1px solid #333; padding: 4px 5px; vertical-align: top; }
        table.data th { background: #f2f2f2; font-weight: 700; text-align: left; font-size: 9px; }
        table.data td.num { text-align: right; white-space: nowrap; }
        table.data td.center { text-align: center; }
        tr.entity-row td { background: #eef2f6; font-weight: 700; }
        tr.detail-row td { background: #fff; }
        .badge {
            display: inline-block;
            border: 1px solid #333;
            padding: 1px 5px;
            font-size: 8px;
            border-radius: 3px;
            margin-left: 4px;
        }
        .badge-ok { border-color: #198754; color: #0f5132; }
        .badge-info { border-color: #0d6efd; color: #084298; }
        .badge-muted { border-color: #6c757d; color: #495057; }
        .footer-note { margin-top: 14px; font-size: 8px; color: #666; }
        .summary span { display: inline-block; margin-right: 16px; }
    </style>
</head>
<body>
    <h1>Escrutinio de premios</h1>
    <p class="muted">Documento generado por Partilot · {{ $generatedAt }}</p>

    <table class="meta">
        <tr>
            <td><strong>Sorteo:</strong></td>
            <td>{{ $lottery->name }} — {{ $lottery->description ?? ($lottery->lotteryType->name ?? '') }}</td>
            <td><strong>Fecha sorteo:</strong></td>
            <td>{{ $lottery->draw_date ? $lottery->draw_date->format('d/m/Y') : '—' }}</td>
        </tr>
        <tr>
            <td><strong>Administración:</strong></td>
            <td>{{ $administration->name ?? ('#'.$administration->id) }}</td>
            <td><strong>Estado:</strong></td>
            <td>{{ $scrutiny->is_saved ? 'Guardado' : 'Escrutado' }}@if($scrutiny->saved_at) ({{ $scrutiny->saved_at->format('d/m/Y H:i') }})@endif</td>
        </tr>
    </table>

    <div class="summary">
        <span><strong>Premio total escrutinio:</strong> {{ number_format($totals['premio_total'], 2, ',', '.') }} €</span>
        <span><strong>Líneas premiadas:</strong> {{ $totals['lineas'] }}</span>
        <span><strong>Entidades:</strong> {{ $totals['entidades'] }}</span>
    </div>

    @if(!empty($specialPrizeHintsByEntity))
        <div class="box">
            <strong>Premio especial a cobrar en Loterías y Apuestas</strong>
            @foreach($specialPrizeHintsByEntity as $hint)
                <div>
                    {{ $hint['entity_name'] ?? ('Entidad #'.$hint['entity_id']) }}:
                    nº {{ $hint['numero'] ?? '—' }}
                    · serie {{ $hint['serie'] ?? '—' }}
                    · fracción {{ $hint['fraccion'] ?? '—' }}
                    @if(!empty($hint['importe']))
                        · {{ number_format((float) $hint['importe'], 2, ',', '.') }} €
                    @endif
                </div>
            @endforeach
        </div>
    @elseif(!empty($lotteryHasSpecialPrize))
        <div class="box warn">
            <strong>Este sorteo tiene premio especial (serie/fracción).</strong>
            Aún no hay liquidación entidad→admin que declare el décimo ganador.
        </div>
    @endif

    <h2>Detalle por entidad</h2>

    @if(empty($entities))
        <p class="muted">No hay resultados de escrutinio para esta administración.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th style="width:18%;">Entidad / Set</th>
                    <th style="width:9%;">Número</th>
                    <th style="width:8%;">Tipo</th>
                    <th class="num" style="width:8%;">Décimos</th>
                    <th class="num" style="width:12%;">Premio / décimo</th>
                    <th class="num" style="width:10%;">Participaciones</th>
                    <th class="num" style="width:12%;">Premio total</th>
                    <th class="num" style="width:13%;">Premio / participación</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entities as $entityBlock)
                    <tr class="entity-row">
                        <td colspan="5">
                            {{ $entityBlock['name'] }}
                            @if(!empty($entityBlock['special_prize']))
                                <span class="badge badge-ok">Premio especial · cobrar a LAE</span>
                            @endif
                        </td>
                        <td class="num">
                            E {{ $entityBlock['stats']['emitidas'] }}
                            · V {{ $entityBlock['stats']['vendidas'] }}
                            · D {{ $entityBlock['stats']['devueltas'] }}
                        </td>
                        <td class="num">{{ number_format($entityBlock['premio_total'], 2, ',', '.') }} €</td>
                        <td class="center">—</td>
                    </tr>

                    @forelse($entityBlock['lines'] as $line)
                        <tr class="detail-row">
                            <td>{{ $line['set_name'] }}</td>
                            <td class="center">{{ $line['number'] }}</td>
                            <td class="center">
                                @if($line['type_label'])
                                    <span class="badge {{ $line['type'] === 'digital' ? 'badge-info' : 'badge-muted' }}">{{ $line['type_label'] }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num">{{ $line['decimos_label'] }}</td>
                            <td class="num">{{ number_format($line['premio_por_decimo'], 2, ',', '.') }} €</td>
                            <td class="num">{{ $line['participaciones'] }}</td>
                            <td class="num">{{ number_format($line['premio_total'], 2, ',', '.') }} €</td>
                            <td class="num">{{ number_format($line['premio_por_participacion'], 2, ',', '.') }} €</td>
                        </tr>
                    @empty
                        <tr class="detail-row">
                            <td colspan="8" class="muted">Sin líneas premiadas con décimos &gt; 0.</td>
                        </tr>
                    @endforelse
                @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($scrutiny->comments))
        <h2>Comentarios</h2>
        <p>{{ $scrutiny->comments }}</p>
    @endif

    <p class="footer-note">
        Los premios de participaciones se calculan por número e importe jugado.
        El premio especial (si aplica) es independiente y corresponde al décimo físico declarado en liquidación entidad→administración.
    </p>
</body>
</html>

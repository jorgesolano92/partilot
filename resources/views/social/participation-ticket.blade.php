<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Participación - PARTILOT</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            background: linear-gradient(160deg, #1e293b 0%, #334155 45%, #0f172a 100%);
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #212529;
        }

        .container {
            width: 100%;
            max-width: 720px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .text-center { text-align: center; }
        .text-muted { color: #6c757d; }
        .mb-0 { margin-bottom: 0; }
        .mb-1 { margin-bottom: 0.25rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-3 { margin-bottom: 1rem; }
        .mt-3 { margin-top: 1rem; }
        .mt-4 { margin-top: 1.5rem; }

        h1, h3, h4, h5 { margin-top: 0; }

        .ticket-container {
            max-width: 560px;
            margin: 1.5rem auto 2.5rem;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 24px 48px rgba(0,0,0,0.22);
            overflow: hidden;
        }

        .ticket-header {
            background: linear-gradient(135deg, #e78307 0%, #c45f00 100%);
            color: #fff;
            padding: 1.5rem 1.25rem;
            text-align: center;
        }

        .ticket-header h1 {
            font-size: 1.45rem;
            margin-bottom: 0.35rem;
        }

        .ticket-body { padding: 1.25rem 1.25rem 1.75rem; }

        .preview-wrap {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem;
            margin-bottom: 1.25rem;
            text-align: center;
            overflow: hidden;
        }

        .preview-wrap img,
        .preview-wrap iframe {
            display: block;
            width: 100%;
            max-width: 100%;
            height: auto;
            max-height: 320px;
            margin: 0 auto;
            border: 0;
            border-radius: 8px;
            background: #fff;
            object-fit: contain;
        }

        .preview-wrap iframe {
            min-height: 200px;
            height: 240px;
        }

        .details-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.1rem 1.15rem;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
        }

        .details-card h5 {
            margin: 0 0 0.75rem;
            font-size: 0.95rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.45rem 0;
            border-bottom: 1px solid #eef2f7;
            font-size: 0.98rem;
        }

        .detail-row:last-child { border-bottom: 0; }
        .detail-row strong { color: #0f172a; }

        .numbers-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 0.35rem;
        }

        .number-box {
            background: #1e293b;
            color: #fff;
            padding: 10px 14px;
            border-radius: 10px;
            text-align: center;
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: 0.06em;
        }

        .amount-hero {
            text-align: center;
            font-size: 1.55rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0.35rem 0 0.15rem;
        }

        .amount-sub {
            text-align: center;
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0;
        }

        .status-box {
            border-radius: 12px;
            padding: 1.1rem 1rem;
            text-align: center;
            margin: 1rem 0;
        }

        .status-box h4 { margin: 0 0 0.4rem; font-size: 1.15rem; }
        .status-box p { margin: 0; font-size: 0.95rem; }

        .status-pending {
            background: #fff7ed;
            border: 1px solid #fdba74;
            color: #9a3412;
        }

        .status-pending .badge-pill {
            display: inline-block;
            margin-top: 0.75rem;
            background: #ea580c;
            color: #fff;
            padding: 0.4rem 0.9rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .status-winner {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #14532d;
        }

        .status-no-prize {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #334155;
        }

        .prize-amount {
            font-size: 2rem;
            font-weight: 800;
            color: #15803d;
            margin: 0.4rem 0;
        }

        .error-container {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 1rem;
            color: #991b1b;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 1rem;
            border: none;
        }

        .btn-verify {
            background: linear-gradient(135deg, #e78307 0%, #c45f00 100%);
            color: white;
            padding: 12px 28px;
            border-radius: 25px;
            font-weight: 700;
        }

        .btn-outline-danger {
            background: transparent;
            border: 2px solid #dc3545;
            color: #dc3545;
            padding: 10px 24px;
            border-radius: 25px;
            font-weight: 600;
        }

        .verify-input-group {
            display: flex;
            align-items: stretch;
            max-width: 100%;
            margin: 0 auto;
        }

        .verify-input {
            flex: 1;
            min-width: 0;
            border: 2px solid #dee2e6;
            border-right: none;
            border-radius: 25px 0 0 25px;
            padding: 12px 20px;
            font-size: 1rem;
            outline: none;
        }

        .verify-input-group .btn-verify {
            border-radius: 0 25px 25px 0;
            white-space: nowrap;
        }

        @media (max-width: 576px) {
            .verify-input-group { flex-direction: column; gap: 12px; }
            .verify-input { border-right: 2px solid #dee2e6; border-radius: 25px; }
            .verify-input-group .btn-verify { border-radius: 25px; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="ticket-container">
            <div class="ticket-header">
                <h1>Verificación de Participación</h1>
                <p class="mb-0">Sistema de Verificación PARTILOT</p>
            </div>

            <div class="ticket-body">
                @if($error)
                    <div class="error-container">
                        <h4>Error</h4>
                        <p>{{ $error }}</p>
                        <a href="{{ url('/comprobar-participaciones') }}" class="btn btn-outline-danger">
                            Intentar de nuevo
                        </a>
                    </div>
                @elseif($ticket)
                    @php
                        $drawStatus = $ticket['draw_status'] ?? null;
                        $played = (float) ($ticket['set']['played_amount'] ?? 0);
                        $donation = (float) ($ticket['set']['donation_amount'] ?? 0);
                        $total = (float) ($ticket['set']['total_amount'] ?? ($played + $donation));
                        $amountLabel = $ticket['set']['amount_label'] ?? (number_format($total, 2, ',', '.').'€');
                        $amountBreakdown = $ticket['set']['amount_breakdown'] ?? null;
                        $previewUrl = $ticket['preview_image_url'] ?? null;
                        $playedNumbersLabel = $ticket['reserve']['played_numbers_label']
                            ?? ((!empty($ticket['reserve']['reservation_numbers']) && count($ticket['reserve']['reservation_numbers']) > 1)
                                ? 'Números jugados'
                                : 'Número jugado');
                        $playedNumbersText = $ticket['reserve']['played_numbers_text'] ?? null;
                        if ($playedNumbersText === null && !empty($ticket['reserve']['reservation_numbers'])) {
                            $playedNumbersText = collect($ticket['reserve']['reservation_numbers'])
                                ->map(fn ($n) => str_pad((string) $n, 5, '0', STR_PAD_LEFT))
                                ->implode(', ');
                        }
                    @endphp

                    @if($previewUrl)
                        <div class="preview-wrap">
                            <img src="{{ $previewUrl }}" alt="Participación" class="preview-img"
                                 onerror="this.closest('.preview-wrap').style.display='none'">
                        </div>
                    @endif

                    <div class="details-card">
                        <h5>{{ $ticket['lottery']['name'] ?? 'Sorteo' }}</h5>
                        <div class="detail-row">
                            <span>Fecha del sorteo</span>
                            <strong>
                                @if(!empty($ticket['lottery']['draw_date']))
                                    @php
                                        $rawDraw = (string) $ticket['lottery']['draw_date'];
                                        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $rawDraw, $m)) {
                                            $drawLabel = $m[3].'-'.$m[2].'-'.$m[1];
                                        } else {
                                            $drawLabel = \Carbon\Carbon::parse($rawDraw)->timezone(config('app.timezone'))->format('d-m-Y');
                                        }
                                    @endphp
                                    {{ $drawLabel }}
                                @else
                                    N/A
                                @endif
                            </strong>
                        </div>
                        <div class="detail-row">
                            <span>Entidad</span>
                            <strong>{{ $ticket['reserve']['entity']['name'] ?? 'N/A' }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>{{ $playedNumbersLabel }}</span>
                            <strong>{{ $playedNumbersText ?? 'N/A' }}</strong>
                        </div>
                        <div class="detail-row">
                            <span>Participación</span>
                            <strong>{{ $ticket['data']['participation_code'] ?? 'N/A' }}</strong>
                        </div>
                    </div>

                    <div class="details-card text-center">
                        <h5>Importe de la participación</h5>
                        <div class="amount-hero">{{ $amountLabel }}</div>
                        @if(!empty($amountBreakdown))
                            <p class="amount-sub">{{ $amountBreakdown }}</p>
                        @elseif($donation > 0 && $played > 0)
                            <p class="amount-sub">Jugado {{ number_format($played, 2, ',', '.') }}€ + donativo {{ number_format($donation, 2, ',', '.') }}€</p>
                        @endif
                    </div>

                    <div class="numbers-grid text-center" style="justify-content:center;margin-bottom:1rem;">
                        @if(!empty($ticket['reserve']['reservation_numbers']))
                            @foreach($ticket['reserve']['reservation_numbers'] as $number)
                                <div class="number-box">{{ str_pad($number, 5, '0', STR_PAD_LEFT) }}</div>
                            @endforeach
                        @endif
                    </div>

                    @if(in_array($drawStatus, ['pending_celebration', 'pending_results'], true) || empty($ticket['prize_info']))
                        <div class="status-box status-pending">
                            @if(($drawStatus ?? '') === 'pending_celebration')
                                <h4>Sorteo pendiente de celebración</h4>
                                <p>El sorteo aún no se ha celebrado. Vuelve a consultar después de la fecha del sorteo para ver si tu participación tiene premio.</p>
                                <span class="badge-pill">SORTEO NO CELEBRADO</span>
                            @else
                                <h4>Resultados pendientes</h4>
                                <p>El sorteo ya tiene fecha, pero los resultados aún no están publicados.</p>
                                <span class="badge-pill">RESULTADOS PENDIENTES</span>
                            @endif
                        </div>
                    @elseif(!empty($ticket['prize_info']['has_won']))
                        <div class="status-box status-winner">
                            <h4>¡Felicidades!</h4>
                            <div class="prize-amount">{{ number_format($ticket['prize_info']['prize_amount'], 2, ',', '.') }}€</div>
                            <p>Premio por participación</p>
                        </div>
                    @else
                        <div class="status-box status-no-prize">
                            <h4>Sin premio</h4>
                            <p>Esta participación no ha resultado premiada en este sorteo.</p>
                        </div>
                    @endif

                    <div class="text-center mt-4">
                        <a href="{{ url('/comprobar-participaciones') }}" class="btn btn-verify">
                            Verificar otra participación
                        </a>
                    </div>
                @else
                    <div class="text-center">
                        <h4>Verificar Participación</h4>
                        <p>Ingrese la referencia de su participación para verificar si ha resultado premiada.</p>

                        <form method="GET" action="{{ url('/comprobar-participaciones') }}" class="mt-4">
                            <div class="verify-input-group mb-3">
                                <input type="text" name="ref" class="verify-input"
                                       placeholder="Ingrese la referencia de su participación"
                                       value="{{ request('ref') }}" required autocomplete="off">
                                <button class="btn btn-verify" type="submit">
                                    Verificar
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>

@extends('layouts.layout')

@php
    $isAnnulment = $devolution->isAnnulment();
    $pageTitle = $isAnnulment ? 'Anulación' : 'Devolución';
    $details = $devolution->details;
    $anuladas = $details->where('action', 'anular');
    $devueltas = $details->whereIn('action', ['devolver', 'devolver_vendedor']);
    $ventasRegistradas = $details->where('action', 'vender');
@endphp

@section('title', 'Ver '.$pageTitle)

@section('content')

<style>
    .resumen-devolucion {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 16px;
    }
    .resumen-devolucion .resumen-titulo { font-weight: bold; margin-bottom: 4px; }
    .resumen-devolucion .resumen-subtitulo { color: #6c757d; font-size: 0.95em; margin-bottom: 12px; }
    .resumen-linea {
        padding: 6px 0;
        border-bottom: 1px solid #e9ecef;
        line-height: 1.5;
    }
    .resumen-linea:last-child { border-bottom: none; }
    .resumen-linea .resumen-etiqueta { font-weight: 500; color: #495057; margin-right: 6px; }
    .resumen-linea .resumen-valor { font-weight: 600; }
    .special-prize-box {
        border: 1px solid #e9ecef;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 16px;
        background: #fff;
    }
    .payments-card-clean { margin-top: 0 !important; margin-bottom: 0 !important; }
    .payments-card-clean .card-header { padding: 10px 14px !important; }
    .payments-card-clean .card-body { padding: 12px 14px !important; }
    .devolution-detail-card { margin-bottom: 1rem !important; }
    .participaciones-table th { white-space: nowrap; }
</style>

<div class="container-fluid content-fit">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('devolutions.index') }}">Devoluciones</a></li>
                        <li class="breadcrumb-item active">Ver {{ $pageTitle }}</li>
                    </ol>
                </div>
                <h4 class="page-title">{{ $isAnnulment ? 'Anulaciones' : 'Devoluciones' }}</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card devolution-detail-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="header-title mb-0">
                            Detalles de la {{ $pageTitle }}
                            @if($isAnnulment)
                                <span class="badge bg-warning text-dark ms-1">Anulación</span>
                            @else
                                <span class="badge bg-secondary ms-1">{{ $devolution->typeLabel() }}</span>
                            @endif
                        </h4>
                        <a href="{{ route('devolutions.index') }}" class="btn btn-secondary" style="border-radius: 30px;">
                            <i class="ri-arrow-left-line me-2"></i>Volver
                        </a>
                    </div>

                    <div class="resumen-devolucion">
                        <div class="resumen-titulo">Información general</div>
                        <div class="resumen-subtitulo">{{ $devolution->typeLabel() }} · #{{ str_pad((string) $devolution->id, 4, '0', STR_PAD_LEFT) }}</div>
                        <div class="resumen-linea"><span class="resumen-etiqueta">Entidad:</span> <span class="resumen-valor">{{ $devolution->entity->name ?? 'N/A' }}</span></div>
                        <div class="resumen-linea"><span class="resumen-etiqueta">Sorteo:</span> <span class="resumen-valor">{{ $devolution->lottery->name ?? 'N/A' }}</span></div>
                        @unless($isAnnulment)
                            <div class="resumen-linea"><span class="resumen-etiqueta">Vendedor:</span> <span class="resumen-valor">{{ $devolution->seller ? ($devolution->seller->full_name ?: $devolution->seller->name) : 'Sin vendedor' }}</span></div>
                        @endunless
                        <div class="resumen-linea"><span class="resumen-etiqueta">Fecha:</span> <span class="resumen-valor">{{ \Carbon\Carbon::parse($devolution->devolution_date)->format('d/m/Y') }}</span></div>
                        @if($isAnnulment && $devolution->annulmentReason())
                            <div class="resumen-linea"><span class="resumen-etiqueta">Motivo:</span> <span class="resumen-valor">{{ $devolution->annulmentReason() }}</span></div>
                        @elseif(! $isAnnulment && $devolution->return_reason)
                            <div class="resumen-linea"><span class="resumen-etiqueta">Motivo:</span> <span class="resumen-valor">{{ $devolution->return_reason }}</span></div>
                        @endif
                    </div>

                    @if($isAnnulment)
                        <div class="resumen-devolucion">
                            <div class="resumen-titulo">Resumen de anulación</div>
                            <div class="resumen-subtitulo">Las participaciones anuladas no se liquidan ni vuelven a la reserva.</div>
                            <div class="resumen-linea">
                                <span class="resumen-etiqueta">Participaciones anuladas:</span>
                                <span class="resumen-valor text-danger">{{ $anuladas->count() }}</span>
                            </div>
                        </div>

                        <div class="resumen-devolucion mb-0">
                            <div class="resumen-titulo mb-2">Desglose de participaciones anuladas</div>
                            <div class="table-responsive">
                                <table id="tabla-desglose-anuladas" class="table table-sm table-hover participaciones-table w-100 mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nº</th>
                                            <th>Código</th>
                                            <th>Set</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($anuladas as $detail)
                                            @php $p = $detail->participation; @endphp
                                            <tr>
                                                <td>{{ $p?->participation_number ?? '—' }}</td>
                                                <td><code>{{ $p?->display_participation_code ?: '—' }}</code></td>
                                                <td>{{ $p?->set?->set_name ?: ($p?->set_id ? ('Set #'.$p->set_id) : '—') }}</td>
                                                <td><span class="badge bg-danger">{{ $p?->status_text ?? 'Anulada' }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        @php
                            $precioPorParticipacion = null;
                            $primerSet = $details->first()?->participation?->set;
                            if ($primerSet) {
                                $precioPorParticipacion = (float) ($primerSet->played_amount ?? 0) + (float) ($primerSet->donation_amount ?? 0);
                                if ($precioPorParticipacion <= 0) {
                                    $precioPorParticipacion = null;
                                }
                            }
                            $fmt = function ($n) use ($precioPorParticipacion) {
                                $importe = $precioPorParticipacion !== null ? $n * $precioPorParticipacion : null;
                                return $importe !== null ? $n.' ('.number_format($importe, 2, ',', '.').'€)' : (string) $n;
                            };
                            $disponibles = max(0, (int) $devolution->total_participations - $devueltas->count() - $ventasRegistradas->count());
                        @endphp

                        <div class="resumen-devolucion">
                            <div class="resumen-titulo">Resumen de liquidación</div>
                            <div class="resumen-subtitulo">{{ $devolution->seller_id ? 'Devolución de vendedor' : 'Devolución a administración' }}</div>
                            <div class="resumen-linea">
                                <span class="resumen-etiqueta">Total participaciones:</span>
                                <span class="resumen-valor">{{ $fmt($devolution->total_participations) }}</span>
                            </div>
                            <div class="resumen-linea">
                                <span class="resumen-etiqueta">Participaciones devueltas:</span>
                                <span class="resumen-valor text-danger">{{ $fmt($devueltas->count()) }}</span>
                            </div>
                            <div class="resumen-linea">
                                <span class="resumen-etiqueta">Ventas registradas:</span>
                                <span class="resumen-valor text-success">{{ $fmt($ventasRegistradas->count()) }}</span>
                            </div>
                            <div class="resumen-linea">
                                <span class="resumen-etiqueta">Disponibles:</span>
                                <span class="resumen-valor text-info">{{ $fmt($disponibles) }}</span>
                            </div>
                        </div>

                        @php
                            $detalleFilas = $details->filter(fn ($d) => in_array((string) $d->action, ['devolver', 'devolver_vendedor', 'vender'], true));
                        @endphp
                        <div class="resumen-devolucion">
                            <div class="resumen-titulo mb-2">Desglose de participaciones</div>
                            <div class="table-responsive">
                                <table id="tabla-desglose-devoluciones" class="table table-sm table-hover participaciones-table w-100 mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nº</th>
                                            <th>Código</th>
                                            <th>Acción</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($detalleFilas as $detail)
                                            @php
                                                $p = $detail->participation;
                                                $accion = match ((string) $detail->action) {
                                                    'devolver', 'devolver_vendedor' => 'Devuelta',
                                                    'vender' => 'Vendida / liquidada',
                                                    default => (string) $detail->action,
                                                };
                                            @endphp
                                            <tr>
                                                <td>{{ $p?->participation_number ?? '—' }}</td>
                                                <td><code>{{ $p?->display_participation_code ?: '—' }}</code></td>
                                                <td>{{ $accion }}</td>
                                                <td>{{ $p?->status_text ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @php
                            $sp = is_array($devolution->special_prize_settlement) ? $devolution->special_prize_settlement : [];
                            $spAssignments = $sp['assignments'] ?? [];
                            $spRequiredFractions = (int) ($sp['required_fractions'] ?? 0);
                            $spAssignedFractions = (int) ($sp['assigned_fractions'] ?? 0);
                            $spRemainingFractions = max(0, $spRequiredFractions - $spAssignedFractions);
                            $spTotalLiquidation = (float) ($sp['total_liquidation'] ?? 0);
                            $spPerFraction = $spRequiredFractions > 0 ? ($spTotalLiquidation / $spRequiredFractions) : 0;
                            $spRemainingAmount = $spRemainingFractions * $spPerFraction;
                            $showSpecialPrize = ! empty($spAssignments) || $spRequiredFractions > 0;
                        @endphp

                        @if($showSpecialPrize)
                            <div class="special-prize-box">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                    <h5 class="mb-0">Detalle Serie/Fracción (Premio Especial)</h5>
                                    <span class="badge bg-warning text-dark">Series válidas: 1-{{ (int) ($sp['max_series'] ?? 0) }}</span>
                                </div>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 120px;">SERIE</th>
                                                <th>FRACCIONES</th>
                                                <th class="text-end" style="width: 140px;">DÉCIMOS</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($spAssignments as $item)
                                                @php
                                                    $serie = $item['serie'] ?? '-';
                                                    $fracciones = collect($item['fracciones'] ?? [])->map(fn ($f) => (int) $f)->sort()->values()->all();
                                                @endphp
                                                <tr>
                                                    <td>{{ $serie }}</td>
                                                    <td>{{ implode('-', $fracciones) }}</td>
                                                    <td class="text-end">{{ count($fracciones) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">Sin series/fracciones registradas.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <div class="border rounded p-2 text-center bg-light">
                                            <small class="text-muted d-block">DÉCIMOS OBJETIVO</small>
                                            <strong>{{ $spRequiredFractions }}</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-2 text-center bg-light">
                                            <small class="text-muted d-block">DÉCIMOS ASIGNADOS</small>
                                            <strong>{{ $spAssignedFractions }}</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-2 text-center bg-light">
                                            <small class="text-muted d-block">RESTANTE DÉCIMOS</small>
                                            <strong class="{{ $spRemainingFractions > 0 ? 'text-warning' : 'text-success' }}">{{ $spRemainingFractions }}</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-2 text-center bg-light">
                                            <small class="text-muted d-block">RESTANTE IMPORTE</small>
                                            <strong class="{{ $spRemainingAmount > 0 ? 'text-warning' : 'text-success' }}">{{ number_format($spRemainingAmount, 2, ',', '.') }}€</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="card payments-card-clean">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Resumen de pagos</h5>
                            </div>
                            <div class="card-body">
                                @php
                                    $totalLiquidacion = $devolution->total_liquidation !== null
                                        ? (float) $devolution->total_liquidation
                                        : $ventasRegistradas->sum(fn ($d) => (float) ($d->participation?->set?->played_amount ?? 0));
                                    $totalPagado = $devolution->payments->sum('amount');
                                    $pendiente = $totalLiquidacion - $totalPagado;
                                @endphp
                                <div class="row g-2 mb-2">
                                    <div class="col-md-4">
                                        <div class="border rounded p-3 text-center bg-light">
                                            <small class="text-muted">Total liquidación</small>
                                            <h4 class="text-primary mb-0">{{ number_format($totalLiquidacion, 2) }}€</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-3 text-center bg-success bg-opacity-10">
                                            <small class="text-muted">Total pagado</small>
                                            <h4 class="text-success mb-0">{{ number_format($totalPagado, 2) }}€</h4>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="border rounded p-3 text-center {{ $pendiente > 0 ? 'bg-warning bg-opacity-10' : 'bg-success bg-opacity-10' }}">
                                            <small class="text-muted">Pendiente</small>
                                            <h4 class="mb-0 {{ $pendiente > 0 ? 'text-warning' : 'text-success' }}">{{ number_format($pendiente, 2) }}€</h4>
                                        </div>
                                    </div>
                                </div>

                                @if($devolution->payments->count() > 0)
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Método</th>
                                                    <th class="text-end">Monto</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($devolution->payments as $payment)
                                                    <tr>
                                                        <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y H:i') }}</td>
                                                        <td>{{ ucfirst($payment->payment_method) }}</td>
                                                        <td class="text-end"><strong>{{ number_format($payment->amount, 2) }}€</strong></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-muted mb-0 small">No hay pagos registrados en esta liquidación.</p>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
@section('scripts')
<script>
$(function () {
    var opts = {
        ordering: true,
        order: [[0, 'asc']],
        pageLength: 20,
        lengthMenu: [[20, 50, 100, -1], [20, 50, 100, 'Todos']],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' }
    };
    if ($('#tabla-desglose-anuladas').length && $('#tabla-desglose-anuladas tbody tr').length) {
        $('#tabla-desglose-anuladas').DataTable(opts);
    }
    if ($('#tabla-desglose-devoluciones').length && $('#tabla-desglose-devoluciones tbody tr').length) {
        $('#tabla-desglose-devoluciones').DataTable(opts);
    }
});
</script>
@endsection

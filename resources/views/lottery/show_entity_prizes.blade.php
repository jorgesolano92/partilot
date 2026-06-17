@extends('layouts.layout')

@section('title','Premio de mi entidad')

@section('content')

<div class="container-fluid">

    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('lotteries.index') }}">Sorteos</a></li>
                        <li class="breadcrumb-item active">Premio de mi entidad</li>
                    </ol>
                </div>
                <h4 class="page-title">Premio escrutado</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="form-card bs mb-3">
                        <h4 class="mb-0 mt-1">Datos del sorteo</h4>
                        <small><i>{{ $entity->name }}</i></small>

                        <div class="row mt-3">
                            <div class="col-md-4">
                                <div class="text-muted small">Sorteo</div>
                                <strong>{{ $lottery->name }}</strong>
                                @if($lottery->description)
                                    <div class="text-muted">{{ $lottery->description }}</div>
                                @endif
                            </div>
                            <div class="col-md-2">
                                <div class="text-muted small">Tipo</div>
                                <strong>{{ $lottery->lotteryType->name ?? '—' }}</strong>
                            </div>
                            <div class="col-md-2">
                                <div class="text-muted small">Fecha sorteo</div>
                                <strong>{{ $lottery->draw_date ? \Carbon\Carbon::parse($lottery->draw_date)->format('d/m/Y') : '—' }}</strong>
                            </div>
                            <div class="col-md-2">
                                <div class="text-muted small">Precio décimo</div>
                                <strong>{{ number_format($lottery->ticket_price, 2, ',', '.') }}€</strong>
                            </div>
                            @if($scrutiny)
                                <div class="col-md-2">
                                    <div class="text-muted small">Escrutinio</div>
                                    <span class="badge bg-{{ $scrutiny->is_saved ? 'success' : 'warning' }}">
                                        {{ $scrutiny->is_saved ? 'Publicado' : 'Provisional' }}
                                    </span>
                                    @if($scrutiny->scrutiny_date)
                                        <div class="small text-muted">{{ $scrutiny->scrutiny_date->format('d/m/Y H:i') }}</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    @if(!$scrutiny)
                        <div class="alert alert-info mb-0">
                            <i class="ri-information-line me-1"></i>
                            La administración aún no ha realizado el escrutinio para este sorteo, o no hay premio registrado para su entidad.
                        </div>
                    @else
                        <div class="form-card bs" style="min-height: 400px;">
                            <h4 class="mb-0 mt-1">Resultado del escrutinio para su entidad</h4>
                            <small><i>Premios calculados por la administración de loterías</i></small>

                            <div class="row mt-3 mb-3">
                                <div class="col-md-3">
                                    <div class="text-muted small">Participaciones emitidas</div>
                                    <strong>{{ $participationStats['emitidas'] }}</strong>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-muted small">Participaciones vendidas</div>
                                    <strong>{{ $participationStats['vendidas'] }}</strong>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-muted small">Participaciones devueltas</div>
                                    <strong>{{ $participationStats['devueltas'] }}</strong>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-muted small">Premio total</div>
                                    <strong class="text-success fs-5">{{ number_format($totalEntityPrize, 2, ',', '.') }}€</strong>
                                </div>
                            </div>

                            @if($entityResults->isEmpty())
                                <div class="alert alert-secondary mb-0">
                                    El escrutinio está realizado pero su entidad no tiene números premiados en este sorteo.
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-bordered mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Número premiado</th>
                                                <th class="text-end">Premio por décimo</th>
                                                <th class="text-center">Décimos</th>
                                                <th class="text-end">Premio por participación</th>
                                                <th class="text-end">Total</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($entityResults as $result)
                                                @php
                                                    $calc = $entityPrizeService->recalculateDecimos($result, $lottery);
                                                @endphp
                                                @if($calc['decimos'] > 0)
                                                    <tr>
                                                        <td><strong>{{ $result->winning_number }}</strong></td>
                                                        <td class="text-end">{{ number_format($result->premio_por_decimo, 2, ',', '.') }}€</td>
                                                        <td class="text-center">{{ $calc['decimos'] }}</td>
                                                        <td class="text-end">{{ number_format($result->premio_por_participacion, 2, ',', '.') }}€</td>
                                                        <td class="text-end"><strong>{{ number_format($calc['premio_total'], 2, ',', '.') }}€</strong></td>
                                                        <td class="text-end">
                                                            <button type="button" class="btn btn-sm btn-outline-info"
                                                                onclick="showPrizeDetails('{{ $result->winning_number }}', {{ json_encode($result->winning_categories) }}, {{ $calc['decimos'] }}, {{ $result->premio_por_decimo }}, {{ $result->premio_por_participacion }})">
                                                                <i class="ri-eye-line"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        </tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <th colspan="4" class="text-end">Total premio entidad</th>
                                                <th class="text-end">{{ number_format($totalEntityPrize, 2, ',', '.') }}€</th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @endif

                            @if($scrutiny->comments)
                                <div class="mt-3">
                                    <div class="text-muted small">Observaciones de la administración</div>
                                    <div class="alert alert-light mb-0">{{ $scrutiny->comments }}</div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="mt-3">
                        <a href="{{ route('lotteries.index') }}" class="btn btn-md btn-dark" style="border-radius: 30px;">
                            <i class="ri-arrow-left-line me-1"></i> Volver a sorteos
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="prizeDetailsModal" tabindex="-1" aria-labelledby="prizeDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="prizeDetailsModalLabel">Detalles de premios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="prizeDetailsContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(amount);
}

function showPrizeDetails(number, categories, totalDecimos, premioPorDecimo, premioPorParticipacion) {
    let content = `<h6>Número: <strong class="text-primary">${number}</strong></h6>`;
    content += `<p><strong>Décimos:</strong> ${totalDecimos} | <strong>Premio por décimo:</strong> ${formatCurrency(premioPorDecimo)}</p>`;
    content += `<p><strong>Premio por participación:</strong> ${formatCurrency(premioPorParticipacion)}</p><hr>`;

    let totalPrize = 0;
    if (categories && Array.isArray(categories)) {
        categories.forEach(category => {
            const prize = parseFloat(category.premio_decimo || 0);
            totalPrize += prize;
            content += `
                <div class="row mb-2">
                    <div class="col-8"><strong>${category.categoria || 'Premio'}</strong></div>
                    <div class="col-4 text-end"><strong class="text-success">${formatCurrency(prize)}</strong></div>
                </div>
            `;
        });
    }

    const premioTotal = premioPorDecimo * totalDecimos;
    content += `<hr><div class="row">
        <div class="col-8"><strong>Total por décimo:</strong></div>
        <div class="col-4 text-end"><strong class="text-success">${formatCurrency(totalPrize)}</strong></div>
    </div>`;
    content += `<div class="row">
        <div class="col-8"><strong>Total con ${totalDecimos} décimos:</strong></div>
        <div class="col-4 text-end"><strong class="text-success">${formatCurrency(premioTotal)}</strong></div>
    </div>`;

    document.getElementById('prizeDetailsContent').innerHTML = content;
    document.getElementById('prizeDetailsModalLabel').textContent = `Detalles de premios — Número ${number}`;
    new bootstrap.Modal(document.getElementById('prizeDetailsModal')).show();
}
</script>
@endsection

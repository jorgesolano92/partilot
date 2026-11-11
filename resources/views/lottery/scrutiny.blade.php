@extends('layouts.layout')

@section('title','Escrutinio de Sorteo')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Sorteos</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('lottery.results') }}">Resultados</a></li>
                        <li class="breadcrumb-item active">Escrutinio</li>
                    </ol>
                </div>
                <h4 class="page-title">Escrutinio de Sorteo</h4>
            </div>
        </div>
    </div>

    @if(session('error'))
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="ri-error-warning-line me-2"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    @endif

    @if(session('warning'))
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="ri-alert-line me-2"></i>
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    @endif

    @if(!empty($scrutinyBlocked))
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning" role="alert">
                    <i class="ri-error-warning-line me-2"></i>
                    {{ $scrutinyBlockedMessage }}
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <form action="{{ route('lottery.process-scrutiny', $lottery->id) }}" method="POST">
                        @csrf
                        
                        <h4 class="header-title">
                            Realizar Escrutinio
                            <span class="badge bg-warning float-end">PENDIENTE</span>
                        </h4>

                        <br>

                        <div class="row">
                            
                            <div class="col-md-12">
                                <div class="form-card bs" style="min-height: 658px;">
                                    <h4 class="mb-0 mt-1">
                                        Datos del Sorteo y Administración
                                    </h4>
                                    <small><i>Verificar que los datos sean correctos antes de procesar</i></small>

                                    <div class="form-group mt-2 mb-3">

                                        <div class="row">

                                            <div class="col-4">

                                                <div style="width: 150px; height: 80px; border-radius: 8px; background-color: silver; float: left; margin-right: 20px;">
                                                    @if($lottery->image)
                                                        <img src="{{ url('uploads/' . $lottery->image) }}" alt="Sorteo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                                                    @endif
                                                </div>

                                                <div style="float: left; margin-top: .5rem">
                                                    Sorteo: {{ $lottery->name }} <br>
                                                    
                                                    <h4 class="mt-0 mb-0">
                                                        {{ $lottery->description ?? $lottery->lotteryType->name }}
                                                    </h4>

                                                </div>

                                                <div class="clearfix"></div>
                                                
                                            </div>

                                            <div class="col-2">

                                                <div style="float: left; margin-top: .5rem">
                                                    Fecha Sorteo <br>
                                                    
                                                    <h5 class="mb-0">
                                                       {{ $lottery->draw_date ? $lottery->draw_date->format('d/m/Y') : 'N/A' }}
                                                    </h5>

                                                </div>
                                                
                                            </div>

                                            <div class="col-6">

                                                <div class="mt-2">
                                                    @php
                                                        // Contar cada set una sola vez (una participación no se suma por cada número premiado).
                                                        $maxPartsBySet = [];
                                                        $totalPrizeAmount = 0;
                                                        foreach ($scrutinyResultsByEntity as $entityResults) {
                                                            foreach ($entityResults as $categoryResult) {
                                                                $decimosInfo = $categoryResult['decimos_info'] ?? [];
                                                                $premioPorDecimo = (float) ($categoryResult['total_prize'] ?? 0);
                                                                $ticketPrice = (float) ($decimosInfo['ticket_price'] ?? 0);
                                                                foreach ($decimosInfo['sets_info'] ?? [] as $setInfo) {
                                                                    $setId = (int) ($setInfo['set_id'] ?? 0);
                                                                    $parts = (int) ($setInfo['participations_vendidas'] ?? 0);
                                                                    if ($setId > 0) {
                                                                        $maxPartsBySet[$setId] = max($maxPartsBySet[$setId] ?? 0, $parts);
                                                                    }
                                                                    $importeJugado = (float) ($setInfo['importe_jugado'] ?? 0);
                                                                    if ($ticketPrice > 0 && $importeJugado > 0 && $parts > 0) {
                                                                        $totalPrizeAmount += ($premioPorDecimo * ($importeJugado / $ticketPrice)) * $parts;
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        $adminIdForStats = session('selected_administration.id');
                                                        $lotteryIdForStats = $lottery->id;
                                                        $totalAsignadas = \App\Models\Participation::whereHas('set.reserve', function ($query) use ($lotteryIdForStats) {
                                                                $query->where('lottery_id', $lotteryIdForStats);
                                                            })
                                                            ->whereHas('entity', function ($query) use ($adminIdForStats) {
                                                                $query->where('administration_id', $adminIdForStats);
                                                            })
                                                            ->soldForScrutiny()
                                                            ->count();
                                                        $totalWinning = (int) array_sum($maxPartsBySet);
                                                        $totalNonWinning = max(0, $totalAsignadas - $totalWinning);
                                                    @endphp
                                                    Participaciones Vendidas: <b>{{ $totalAsignadas }}</b> <br>
                                                    Participaciones Vendidas Premiadas: <b>{{ $totalWinning }}</b> <br>
                                                    Participaciones Vendidas No Premiadas: <b>{{ $totalNonWinning }}</b> <br>
                                                    Importe Premios Repartidos: <b>{{ number_format($totalPrizeAmount, 2) }}€</b>
                                                </div>
                                                
                                            </div>
                                        </div>
                                        
                                    </div>

                                    <h4 class="mb-0 mt-1">
                                        Lista de entidades y sus participaciones asignadas
                                    </h4>

                                    @php
                                        $specialPrizeHintsByEntity = $specialPrizeHintsByEntity ?? [];
                                        $lotteryHasSpecialPrize = $lotteryHasSpecialPrize ?? false;
                                    @endphp
                                    <div class="show-alerts special-prize-scrutiny-hints">
                                        @if(!empty($specialPrizeHintsByEntity))
                                            <div class="alert alert-success py-2 mt-2 mb-2">
                                                <strong>Premio especial a cobrar en Loterías y Apuestas.</strong>
                                                @foreach($specialPrizeHintsByEntity as $hint)
                                                    <div class="small mt-1">
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
                                        @elseif($lotteryHasSpecialPrize)
                                            <div class="alert alert-warning py-2 mt-2 mb-2">
                                                Este sorteo tiene <strong>premio especial</strong> (serie/fracción).
                                                Aún no hay liquidación entidad→admin que declare el décimo ganador.
                                            </div>
                                        @endif
                                    </div>

                                    <div class="scrutiny-entities-scroll" style="min-height: 400px; height: 400px; overflow: auto;">

                                        <table class="table">

                                            <thead>
                                                <tr>
                                                    <th>Entidad</th>
                                                    <th class="text-center">Participaciones</th>
                                                    <th class="text-center">Premio Total</th>
                                                    <th class="text-center">Premio por Participación</th>
                                                </tr>
                                            </thead>

                                            <tbody class="text-center">
                                                @forelse(($scrutinyData['entities'] ?? $scrutinyData) as $data)
                                                    @php
                                                        $entity = $data['entity'];
                                                        $result = $data['result'];
                                                        $prizeBreakdown = $result->prize_breakdown;
                                                    @endphp
                                                    @php
                                                        $entityCategoryResults = $scrutinyResultsByEntity[$entity->id] ?? [];
                                                        $entityWinningParticipations = (int) ($result->winning_participations ?? 0);
                                                        if ($entityWinningParticipations === 0 && ! empty($entityCategoryResults)) {
                                                            foreach ($entityCategoryResults as $categoryResult) {
                                                                $entityWinningParticipations += (int) (($categoryResult['decimos_info'] ?? [])['total_participations'] ?? 0);
                                                            }
                                                        }
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <b>{{ $entity->name }}</b>
                                                            @if(!empty($specialPrizeHintsByEntity[$entity->id]))
                                                                <div class="mt-1">
                                                                    <span class="badge bg-success">Premio especial · cobrar a LAE</span>
                                                                </div>
                                                            @endif
                                                        </td>

                                                        <td>
                                                            Emitidas: <b>{{ $result->total_issued }}</b> <br>
                                                            Vendidas: <b>{{ $result->total_reserved + ($result->total_non_winning ?? 0) }}</b> <br>
                                                            Devueltas: <b>{{ $result->total_returned }}</b> <br>
                                                            <span class="badge bg-{{ $entityWinningParticipations > 0 ? 'success' : 'secondary' }}">
                                                                Premiadas: {{ $entityWinningParticipations }} Participaciones
                                                            </span>
                                                        </td>
                                                        <td>
                                                            @php
                                                                // Obtener el premio total de la entidad desde los resultados por categoría
                                                                $premioTotalEntidad = 0;
                                                                if (isset($scrutinyResultsByEntity[$entity->id])) {
                                                                    foreach ($scrutinyResultsByEntity[$entity->id] as $categoryResult) {
                                                                        $decimosInfo = $categoryResult['decimos_info'] ?? [];
                                                                        $premioPorDecimo = $categoryResult['total_prize'];
                                                                        $ticketPrice = $decimosInfo['ticket_price'] ?? 0;
                                                                        foreach ($decimosInfo['sets_info'] ?? [] as $setInfo) {
                                                                            $importeJugado = $setInfo['importe_jugado'] ?? 0;
                                                                            $participacionesVendidas = (int) ($setInfo['participations_vendidas'] ?? 0);
                                                                            if ($ticketPrice > 0 && $importeJugado > 0 && $participacionesVendidas > 0) {
                                                                                $premioPorParticipacion = $premioPorDecimo * ($importeJugado / $ticketPrice);
                                                                                $premioTotalEntidad += $premioPorParticipacion * $participacionesVendidas;
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            @endphp
                                                            <b>{{ number_format($premioTotalEntidad, 2) }}€</b>
                                                        </td>
                                                        <td>
                                                            <b>-</b>
                                                        </td>
                                                    </tr>
                                                    
                                                    @if(!empty($entityCategoryResults))
                                                        {{-- Escrutinio por Categoría para esta entidad --}}
                                                        @foreach($entityCategoryResults as $categoryResult)
                                                                @php
                                                                    $decimosInfo = $categoryResult['decimos_info'] ?? [];
                                                                    $setsInfo = $decimosInfo['sets_info'] ?? [];
                                                                    $premioPorDecimo = $categoryResult['total_prize'];
                                                                    $ticketPrice = $decimosInfo['ticket_price'] ?? 0;
                                                                @endphp
                                                                
                                                                {{-- Mostrar por cada set individual --}}
                                                                @if(!empty($setsInfo))
                                                                    @foreach($setsInfo as $setInfo)
                                                                        @php
                                                                            $decimosDeEsteSet = (float) ($setInfo['decimos'] ?? 0);
                                                                            $participacionesVendidas = (int) ($setInfo['participations_vendidas'] ?? 0);
                                                                            
                                                                            // Calcular premio por participación para este set específico
                                                                            $premioPorParticipacion = 0;
                                                                            $importeJugado = $setInfo['importe_jugado'] ?? 0;
                                                                            
                                                                            if ($ticketPrice > 0 && $importeJugado > 0) {
                                                                                $porcentajeParticipacion = $importeJugado / $ticketPrice;
                                                                                $premioPorParticipacion = $premioPorDecimo * $porcentajeParticipacion;
                                                                            }

                                                                            $premioTotalSet = $premioPorParticipacion * $participacionesVendidas;
                                                                            $decimosLabel = rtrim(rtrim(number_format($decimosDeEsteSet, 2, ',', '.'), '0'), ',');
                                                                            $setName = $setInfo['set_name'] ?? null;
                                                                            $setTypeLabel = $setInfo['set_type_label'] ?? null;
                                                                            $setType = $setInfo['set_type'] ?? null;
                                                                            if (!$setName && !empty($setInfo['set_id'])) {
                                                                                $setModel = \App\Models\Set::find($setInfo['set_id']);
                                                                                if ($setModel) {
                                                                                    $setName = $setModel->set_name;
                                                                                    $isPhysical = ((int) ($setModel->physical_participations ?? 0) > 0) && ((int) ($setModel->digital_participations ?? 0) <= 0);
                                                                                    $isDigital = ((int) ($setModel->digital_participations ?? 0) > 0) && ((int) ($setModel->physical_participations ?? 0) <= 0);
                                                                                    $setType = $isDigital ? 'digital' : ($isPhysical ? 'fisico' : 'mixto');
                                                                                    $setTypeLabel = $isDigital ? 'Digital' : ($isPhysical ? 'Físico' : 'Mixto');
                                                                                }
                                                                            }
                                                                            $setBadgeClass = $setType === 'digital' ? 'bg-info' : ($setType === 'fisico' ? 'bg-secondary' : 'bg-dark');
                                                                        @endphp
                                                                        <tr>
                                                                            <td colspan="4" style="border-bottom: 1px solid #333; background-color: #f8f9fa;">
                                                                                <div class="d-flex justify-content-between align-items-center">
                                                                                    <div>
                                                                                        <b>Número: {{ $categoryResult['number_str'] }} - Premiado con {{ number_format($premioPorDecimo, 2) }}€ X {{ $decimosLabel }} Décimos = {{ number_format($premioTotalSet, 2) }}€</b>
                                                                                        <small class="text-muted ms-2">({{ $participacionesVendidas }} participaciones)</small>
                                                                                        @if($setName)
                                                                                            <small class="ms-2">
                                                                                                @if($setTypeLabel)
                                                                                                    <span class="badge {{ $setBadgeClass }}">{{ $setTypeLabel }}</span>
                                                                                                @endif
                                                                                                <span class="text-muted">{{ $setName }}</span>
                                                                                            </small>
                                                                                        @endif
                                                                                    </div>
                                                                                    <div class="text-end">
                                                                                        <span class="me-2">{{ number_format($premioPorParticipacion, 2) }}€</span>
                                                                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="showPrizeDetails('{{ $categoryResult['number_str'] }}', {{ json_encode($categoryResult['categories']) }}, {{ $decimosDeEsteSet }}, {{ $premioPorDecimo }}, {{ $premioPorParticipacion }})">
                                                                                            <i class="ri-eye-line"></i>
                                                                                        </button>
                                                                                    </div>
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                @else
                                                                    {{-- Fallback si no hay sets_info --}}
                                                                    {{-- <tr>
                                                                        <td colspan="4" style="border-bottom: 1px solid #333; background-color: #f8f9fa;">
                                                                            <div class="d-flex justify-content-between align-items-center">
                                                                                <div>
                                                                                    @php
                                                                                        $totalDecimos = $decimosInfo['total_decimos'] ?? 0;
                                                                                        $premioTotal = $premioPorDecimo * $totalDecimos;
                                                                                    @endphp
                                                                                    <b>Número: {{ $categoryResult['number_str'] }} - Premiado con {{ number_format($premioPorDecimo, 2) }}€ X {{ $totalDecimos }} Décimos = {{ number_format($premioTotal, 2) }}€</b>
                                                                                </div>
                                                                                <div class="text-end">
                                                                                    <span class="me-2">{{ number_format($premioPorDecimo, 2) }}€</span>
                                                                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="showPrizeDetails('{{ $categoryResult['number_str'] }}', {{ json_encode($categoryResult['categories']) }}, {{ $totalDecimos }}, {{ $premioPorDecimo }}, {{ $premioPorDecimo }})">
                                                                                        <i class="ri-eye-line"></i>
                                                                                    </button>
                                                                                </div>
                                                                            </div>
                                                                        </td>
                                                                    </tr> --}}
                                                                @endif
                                                            @endforeach
                                                    @endif

                                                @empty
                                                <tr>
                                                    <td colspan="4" class="text-center">
                                                        <div class="alert alert-info">
                                                            <i class="ri-information-line me-2"></i>
                                                            No hay entidades con reservas para este sorteo
                                                        </div>
                                                    </td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                            
                                        </table>

                                    </div>

                                    <div class="mt-3">
                                        <label for="comments" class="form-label">Comentarios del escrutinio (opcional):</label>
                                        <textarea class="form-control" id="comments" name="comments" rows="3" placeholder="Ingrese cualquier observación o comentario sobre el escrutinio..."></textarea>
                                    </div>

                                    <div class="row">

                                        <div class="col-6 text-start">
                                            <a href="{{route('lottery.results')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">
                                                <i style="top: 5px; left: 25%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Cancelar</span></a>
                                        </div>
                                        
                                        <div class="col-6 text-end">
                                            <button type="submit" @disabled(!empty($scrutinyBlocked)) style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-warning mt-2" @if(!empty($scrutinyBlocked)) title="{{ $scrutinyBlockedMessage }}" @endif>Procesar Escrutinio
                                                <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i></button>
                                        </div>

                                    </div>

                                </div>
                            </div>

                        </div>

                    </form>
                    
                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div>
    <!-- end row-->


</div> <!-- container -->

<!-- Modal para mostrar detalles de premios -->
<div class="modal fade" id="prizeDetailsModal" tabindex="-1" aria-labelledby="prizeDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="prizeDetailsModalLabel">Detalles de Premios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="prizeDetailsContent">
                <!-- Contenido se llenará dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')

<script>
// Confirmación antes de procesar el escrutinio
document.querySelector('form')?.addEventListener('submit', function(e) {
    @if(!empty($scrutinyBlocked))
    e.preventDefault();
    return;
    @endif
    if (!confirm('¿Está seguro de que desea procesar el escrutinio? Esta acción no se puede deshacer.')) {
        e.preventDefault();
    }
});

// Función para mostrar detalles de premios
function showPrizeDetails(number, categories, totalDecimos, premioPorDecimo, premioPorParticipacion) {
    let content = `<h6>Número: <strong class="text-primary">${number}</strong></h6>`;
    content += `<p><strong>Décimos:</strong> ${totalDecimos} | <strong>Premio por Décimo:</strong> ${formatCurrency(premioPorDecimo)}</p>`;
    content += `<p><strong>Premio por Participación:</strong> ${formatCurrency(premioPorParticipacion)}</p><hr>`;
    
    let totalPrize = 0;
    categories.forEach(category => {
        const prize = parseFloat(category.premio_decimo);
        totalPrize += prize;
        content += `
            <div class="row mb-2">
                <div class="col-8">
                    <strong>${category.categoria}</strong>
                </div>
                <div class="col-4 text-end">
                    <strong class="text-success">${formatCurrency(prize)}</strong>
                </div>
            </div>
        `;
    });
    
    const premioTotal = premioPorDecimo * totalDecimos;
    content += `<hr><div class="row">
        <div class="col-8"><strong>Total por Décimo:</strong></div>
        <div class="col-4 text-end"><strong class="text-success">${formatCurrency(totalPrize)}</strong></div>
    </div>`;
    content += `<div class="row">
        <div class="col-8"><strong>Total con ${totalDecimos} Décimos:</strong></div>
        <div class="col-4 text-end"><strong class="text-success">${formatCurrency(premioTotal)}</strong></div>
    </div>`;
    
    document.getElementById('prizeDetailsContent').innerHTML = content;
    document.getElementById('prizeDetailsModalLabel').textContent = `Detalles de Premios - Número ${number}`;
    
    const modal = new bootstrap.Modal(document.getElementById('prizeDetailsModal'));
    modal.show();
}

// Función para formatear moneda
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-ES', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}
</script>

@php
    // Helper para obtener color del badge según la categoría
    function getCategoryBadgeColor($key) {
        $colors = [
            'primerPremio' => 'danger',
            'segundoPremio' => 'warning', 
            'tercerosPremios' => 'info',
            'cuartosPremios' => 'info',
            'quintosPremios' => 'info',
            'anteriorPrimerPremio' => 'secondary',
            'posteriorPrimerPremio' => 'secondary',
            'anteriorSegundoPremio' => 'secondary',
            'posteriorSegundoPremio' => 'secondary',
            'centenasPrimerPremio' => 'dark',
            'centenasSegundoPremio' => 'dark',
            'dosUltimasCifrasPrimerPremio' => 'primary',
            'tresUltimasCifrasPrimerPremio' => 'primary',
            'ultimaCifraPrimerPremio' => 'primary',
            'extraccionesDeCuatroCifras' => 'primary',
            'extraccionesDeTresCifras' => 'primary',
            'extraccionesDeDosCifras' => 'primary',
            'reintegros' => 'success',
            'pedrea' => 'warning'
        ];
        return $colors[$key] ?? 'secondary';
    }
@endphp

@endsection
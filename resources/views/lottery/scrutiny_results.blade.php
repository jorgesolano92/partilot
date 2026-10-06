@extends('layouts.layout')

@section('title','Resultados del Escrutinio')

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
                        <li class="breadcrumb-item active">Escrutinio Realizado</li>
                    </ol>
                </div>
                <h4 class="page-title">Resultados del Escrutinio</h4>
            </div>
        </div>
    </div>

    {{-- @if(session('success'))
        <div class="row">
            <div class="col-12">
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="ri-check-line me-2"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    @endif

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

    @if(session('info'))
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="ri-information-line me-2"></i>
                    {{ session('info') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    @endif --}}

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">
                        Realizar Escrutinio
                        @if($scrutiny->is_saved)
                            <span class="badge bg-success float-end">GUARDADO</span>
                        @else
                            <span class="badge bg-warning float-end">ESCRUTADO</span>
                        @endif
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
                                                Participaciones Vendidas Premiadas: <b>{{ $scrutiny->scrutiny_summary['total_winning_participations'] ?? 0 }} Números</b> <br>
                                                Participaciones Vendidas No Premiadas: <b>{{ $scrutiny->scrutiny_summary['total_non_winning_participations'] ?? 0 }} Números</b> <br>
                                                Importe Premios Repartidos: <b>{{ number_format($scrutiny->detailedResults->sum('premio_total'), 2) }}€</b>
                                             </div>
                                            
                                        </div>
                                    </div>
                                    
                                </div>

                                <h4 class="mb-0 mt-1">
                                    Lista de entidades y sus participaciones asignadas
                                </h4>

                                <div style="min-height: 400px; height: 400px; overflow: auto;">

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
                                            @forelse($scrutiny->detailedResults->groupBy('entity_id') as $entityId => $entityResults)
                                                @php
                                                    $entity = $entityResults->first()->entity;
                                                    $totalEntityPrize = $entityResults->sum('premio_total');
                                                    $totalWinningNumbers = $entityResults->count();
                                                @endphp
                                                <tr>
                                                    <td><b>{{ $entity->name }}</b></td>

                                                    <td>
                                                        @php
                                                            // Calcular datos correctos desde la base de datos
                                                            $totalEmitidas = \App\Models\Participation::whereHas('set.reserve', function($query) use ($lottery) {
                                                                $query->where('lottery_id', $lottery->id);
                                                            })
                                                            ->whereHas('entity', function($query) use ($entity) {
                                                                $query->where('id', $entity->id);
                                                            })
                                                            ->count();
                                                            
                                                            $totalVendidas = \App\Models\Participation::whereHas('set.reserve', function($query) use ($lottery) {
                                                                $query->where('lottery_id', $lottery->id);
                                                            })
                                                            ->whereHas('entity', function($query) use ($entity) {
                                                                $query->where('id', $entity->id);
                                                            })
                                                            ->soldForScrutiny()
                                                            ->count();

                                                            $totalDevueltas = \App\Models\Participation::whereHas('set.reserve', function($query) use ($lottery) {
                                                                $query->where('lottery_id', $lottery->id);
                                                            })
                                                            ->whereHas('entity', function($query) use ($entity) {
                                                                $query->where('id', $entity->id);
                                                            })
                                                            ->where('status', 'devuelta')
                                                            ->count();
                                                        @endphp
                                                        Emitidas: <b>{{ $totalEmitidas }}</b> <br>
                                                        Vendidas: <b>{{ $totalVendidas }}</b> <br>
                                                        Devueltas: <b>{{ $totalDevueltas }}</b> <br>
                                                        <span class="badge bg-{{ $totalWinningNumbers > 0 ? 'success' : 'secondary' }}">
                                                            Premiadas: {{ $totalWinningNumbers }} Números
                                                     </span>
                                                </td>
                                                <td>
                                                        <b>{{ number_format($totalEntityPrize, 2) }}€</b>
                                                </td>
                                                <td>
                                                        <b>-</b>
                                                </td>
                                            </tr>
                                            
                                                @if($totalWinningNumbers > 0)
                                                    @foreach($entityResults as $result)
                                                    @php
                                                        $decimosDeEsteSet = (float) $result->total_decimos;
                                                        $decimosLabel = \App\Services\EntityLotteryPrizeService::formatDecimosLabel($decimosDeEsteSet);
                                                        $premioTotalSet = (float) $result->premio_total;
                                                    @endphp
                                                    @if ($decimosDeEsteSet > 0)
                                                    <tr>
                                                        <td colspan="4" style="border-bottom: 1px solid #333; background-color: #f8f9fa;">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <b>Número: {{ $result->winning_number }} - Premiado con {{ number_format($result->premio_por_decimo, 2) }}€ X {{ $decimosLabel }} Décimos = {{ number_format($premioTotalSet, 2) }}€</b>
                                                                        <small class="text-muted ms-2">({{ $result->total_participations }} participaciones)</small>
                                                                    </div>
                                                                    <div class="text-end">
                                                                        <span class="me-2">{{ number_format($result->premio_por_participacion, 2) }}€</span>
                                                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="showPrizeDetails('{{ $result->winning_number }}', {{ json_encode($result->winning_categories) }}, {{ $decimosDeEsteSet }}, {{ $result->premio_por_decimo }}, {{ $result->premio_por_participacion }})">
                                                                            <i class="ri-eye-line"></i>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                        </td>
                                                    </tr>
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

                                @if($scrutiny->comments)
                                <div class="mt-3">
                                    <h5>Comentarios del escrutinio:</h5>
                                    <div class="alert alert-light">
                                        {{ $scrutiny->comments }}
                                    </div>
                                </div>
                                @endif

                                <div class="row">

                                    <div class="col-4 text-start">
                                        <a href="{{route('lottery.results')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">
                                            <i style="top: 5px; left: 10%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Volver a Resultados</span></a>
                                    </div>
                                    
                                    <div class="col-4 text-center">
                                        @if(!$scrutiny->is_saved)
                                            <form action="{{ route('lottery.save-scrutiny', $lottery->id) }}" method="POST" style="display: inline;">
                                                @csrf
                                                <button type="submit" style="border-radius: 30px; width: 200px; background-color: #28a745; color: #fff; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-success mt-2" onclick="return confirm('¿Está seguro de que desea guardar el escrutinio? Esta acción no se puede deshacer.')">
                                                    {{-- <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i> --}}Guardar Escrutinio
                                                </button>
                                            </form>
                                        @else
                                            <div class="alert alert-success mt-2" style="border-radius: 30px; width: 200px; margin: 0 auto;">
                                                <i class="ri-check-line me-2"></i>Escrutinio Guardado
                                                <br><small>Guardado el {{ $scrutiny->saved_at->format('d/m/Y H:i') }}</small>
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <div class="col-4 text-end">
                                        <button onclick="window.print()" style="border-radius: 30px; width: 200px; background-color: #17a2b8; color: #fff; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-info mt-2">Imprimir Escrutinio
                                            <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-printer-line"></i></button>
                                    </div>

                                </div>

                            </div>
                        </div>

                    </div>

                    
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
// Función para mostrar detalles de premios
function showPrizeDetails(number, categories, totalDecimos, premioPorDecimo, premioPorParticipacion) {
    let content = `<h6>Número: <strong class="text-primary">${number}</strong></h6>`;
    content += `<p><strong>Décimos:</strong> ${totalDecimos} | <strong>Premio por Décimo:</strong> ${formatCurrency(premioPorDecimo)}</p>`;
    content += `<p><strong>Premio por Participación:</strong> ${formatCurrency(premioPorParticipacion)}</p><hr>`;
    
    let totalPrize = 0;
    if (categories && Array.isArray(categories)) {
        categories.forEach(category => {
            const prize = parseFloat(category.premio_decimo || 0);
            totalPrize += prize;
            content += `
                <div class="row mb-2">
                    <div class="col-8">
                        <strong>${category.categoria || 'Premio'}</strong>
                    </div>
                    <div class="col-4 text-end">
                        <strong class="text-success">${formatCurrency(prize)}</strong>
                    </div>
                </div>
            `;
        });
    }
    
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

@endsection

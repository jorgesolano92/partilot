@extends('layouts.layout')

@section('title', 'Activación cobro de premios')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="page-title mb-1">Activación de cobro de premios</h4>
                    <p class="text-muted mb-0">Entidades con modalidad definida tras la devolución a administración.</p>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Modalidad</label>
                    <select name="mode" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="online" @selected(($filters['mode'] ?? '') === 'online')>Online</option>
                        <option value="presencial" @selected(($filters['mode'] ?? '') === 'presencial')>Presencial</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Estado fondos</label>
                    <select name="funds_status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="pending" @selected(($filters['funds_status'] ?? '') === 'pending')>Pendiente</option>
                        <option value="confirmed" @selected(($filters['funds_status'] ?? '') === 'confirmed')>Confirmado</option>
                        <option value="not_required" @selected(($filters['funds_status'] ?? '') === 'not_required')>No requerido</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Entidad</th>
                            <th>Administración</th>
                            <th>Sorteo</th>
                            <th>Modalidad</th>
                            <th class="text-end">Importe requerido</th>
                            <th>Fondos</th>
                            <th>Contrato</th>
                            <th>Estado cobro</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($settings as $row)
                            <tr>
                                <td><strong>{{ $row->entity->name ?? '—' }}</strong></td>
                                <td>{{ $row->entity->administration->name ?? $row->entity->administration->society ?? '—' }}</td>
                                <td>{{ $row->lottery->name ?? '—' }}</td>
                                <td>{{ $row->modeLabel() }}</td>
                                <td class="text-end">{{ number_format($row->funds_required_amount, 2, ',', '.') }}€</td>
                                <td>
                                    <span class="badge bg-{{ $row->funds_status === 'confirmed' ? 'success' : ($row->funds_status === 'pending' ? 'warning text-dark' : 'secondary') }}">
                                        {{ $row->fundsStatusLabel() }}
                                    </span>
                                </td>
                                <td>
                                    @if($row->contract_status === 'pending')
                                        <span class="badge bg-warning text-dark">Pendiente</span>
                                    @elseif($row->contract_status === 'signed')
                                        <span class="badge bg-success">Firmado</span>
                                    @else
                                        <span class="badge bg-secondary">No requerido</span>
                                    @endif
                                </td>
                                <td>{{ $row->activationSummary() }}</td>
                                <td class="text-end">
                                    <a href="{{ route('prize-payments.show', $row->id) }}" class="btn btn-sm btn-warning text-dark">Gestionar</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    No hay entidades con modalidad de pago registrada. Se crean al liquidar la devolución a administración.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

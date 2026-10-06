@extends('layouts.layout')

@section('title', 'Adeudos SEPA administraciones')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Adeudos SEPA — cobros a administraciones</h4>
                <p class="text-muted mb-0">Generación de XML pain.008 para domiciliar cargos de cuota de gestión y diseño e impresión.</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Cargos pendientes por administración</h5>
                    @forelse($administrationsWithPending as $row)
                        @php
                            $admin = $row['administration'];
                            $charges = $row['pending_charges'];
                        @endphp
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong>{{ $admin->name ?? $admin->society }}</strong>
                                    <div class="small text-muted">{{ $charges->count() }} cargo(s) · {{ number_format($row['pending_total'], 2, ',', '.') }}€</div>
                                </div>
                                <a href="{{ route('administrations.show', $admin->id) }}" class="btn btn-sm btn-light">Ver admin</a>
                            </div>
                            <form method="POST" action="{{ route('billing-direct-debits.store', $admin->id) }}" onsubmit="return confirm('¿Crear orden de adeudo con todos los cargos pendientes?');">
                                @csrf
                                @foreach($charges as $charge)
                                    <input type="hidden" name="charge_ids[]" value="{{ $charge->id }}">
                                @endforeach
                                <div class="mb-2">
                                    <label class="form-label small mb-1">Fecha de cobro</label>
                                    <input type="date" name="collection_date" class="form-control form-control-sm" value="{{ now()->addDays(5)->format('Y-m-d') }}" required>
                                </div>
                                <button type="submit" class="btn btn-warning text-dark btn-sm w-100">
                                    <i class="ri-bank-line me-1"></i> Crear orden de adeudo
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No hay cargos pendientes de remesa en administraciones con modalidad activa.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Órdenes de adeudo</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Administración</th>
                                    <th>Fecha cobro</th>
                                    <th class="text-end">Importe</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $order)
                                    <tr>
                                        <td><a href="{{ route('billing-direct-debits.show', $order->id) }}">{{ $order->id }}</a></td>
                                        <td>{{ $order->administration->name ?? '—' }}</td>
                                        <td>{{ $order->collection_date?->format('d/m/Y') }}</td>
                                        <td class="text-end">{{ number_format($order->control_sum, 2, ',', '.') }}€</td>
                                        <td><span class="badge bg-secondary">{{ $order->statusLabel() }}</span></td>
                                        <td class="text-end">
                                            <a href="{{ route('billing-direct-debits.generate-xml', $order->id) }}" class="btn btn-sm btn-primary" title="Descargar XML">
                                                <i class="ri-download-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-muted text-center py-4">Sin órdenes de adeudo generadas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

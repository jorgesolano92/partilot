@extends('layouts.layout')

@section('title', 'Orden de adeudo #'.$order->id)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('billing-direct-debits.index') }}">Adeudos SEPA</a></li>
                        <li class="breadcrumb-item active">#{{ $order->id }}</li>
                    </ol>
                </div>
                <h4 class="page-title">Orden de adeudo #{{ $order->id }}</h4>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="small text-muted">Administración</div>
                    <strong>{{ $order->administration->name ?? '—' }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Fecha de cobro</div>
                    <strong>{{ $order->collection_date?->format('d/m/Y') }}</strong>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Importe total</div>
                    <strong>{{ number_format($order->control_sum, 2, ',', '.') }}€</strong>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Estado</div>
                    <strong>{{ $order->statusLabel() }}</strong>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Acreedor (PARTILOT)</div>
                    <div>{{ $order->creditor_name }} · {{ $order->creditor_iban }}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Deudor (administración)</div>
                    <div>{{ $order->debtor_name }} · {{ $order->debtor_iban }}</div>
                    <div class="small text-muted">Mandato {{ $order->debtor_mandate_id }} ({{ $order->debtor_mandate_signed_at?->format('d/m/Y') }}) · {{ $order->sequence_type }}</div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <a href="{{ route('billing-direct-debits.generate-xml', $order->id) }}" class="btn btn-primary">
                    <i class="ri-download-line me-1"></i> Descargar XML pain.008
                </a>
                @if($order->status !== \App\Models\BillingDirectDebitOrder::STATUS_COLLECTED)
                    <form method="POST" action="{{ route('billing-direct-debits.mark-collected', $order->id) }}" onsubmit="return confirm('¿Confirmar que el banco ha cobrado este adeudo?');">
                        @csrf
                        <button type="submit" class="btn btn-success">Marcar como cobrado</button>
                    </form>
                    <form method="POST" action="{{ route('billing-direct-debits.cancel', $order->id) }}" onsubmit="return confirm('¿Anular la orden y devolver los cargos a pendientes?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">Anular orden</button>
                    </form>
                @endif
                <a href="{{ route('billing-direct-debits.index') }}" class="btn btn-light">Volver</a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Cargos incluidos ({{ $order->charges->count() }})</h5>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Concepto</th>
                            <th>Descripción</th>
                            <th>Entidad</th>
                            <th class="text-end">Importe</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->charges as $charge)
                            <tr>
                                <td>{{ $charge->conceptLabel() }}</td>
                                <td>{{ $charge->description }}</td>
                                <td>{{ $charge->entity->name ?? '—' }}</td>
                                <td class="text-end">{{ number_format($charge->amount, 2, ',', '.') }}€</td>
                                <td>{{ $charge->statusLabel() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

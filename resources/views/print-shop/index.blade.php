@extends('layouts.layout')

@section('title', 'Panel Imprenta — Órdenes')

@section('content')

<style>
    #print-shop-content .alert {
        display: block !important;
    }
</style>

<div class="container-fluid" id="print-shop-content">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item active">Órdenes de impresión</li>
                    </ol>
                </div>
                <h4 class="page-title">Panel Imprenta</h4>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row mb-3">
        <div class="col-12">
            <div class="btn-group flex-wrap" role="group">
                @php
                    $filters = [
                        'all' => 'Todas',
                        \App\Models\PrintOrder::STATUS_PENDING_REVIEW => 'Pendiente revisión',
                        \App\Models\PrintOrder::STATUS_IN_PRODUCTION => 'En producción',
                        \App\Models\PrintOrder::STATUS_SENT => 'Enviadas',
                        \App\Models\PrintOrder::STATUS_REJECTED => 'Rechazadas',
                    ];
                @endphp
                @foreach($filters as $key => $label)
                    @php
                        $count = $key === 'all'
                            ? ($counts->sum() ?? $printOrders->count())
                            : (int) ($counts[$key] ?? 0);
                    @endphp
                    <a href="{{ route('print-shop.index', ['status' => $key]) }}"
                       class="btn btn-sm {{ ($statusFilter ?? 'all') === $key ? 'btn-dark' : 'btn-outline-dark' }}">
                        {{ $label }} @if($count > 0)<span class="badge bg-secondary ms-1">{{ $count }}</span>@endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card" style="min-height: calc(100vh - 283px);">
                <div class="card-body">
                    @if($printOrders->isEmpty())
                        <div class="alert alert-info mb-0">No hay órdenes con el filtro seleccionado.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Orden</th>
                                        <th>Entidad</th>
                                        <th>Set</th>
                                        <th>Sorteo</th>
                                        <th>Estado</th>
                                        <th>Cobro</th>
                                        <th>Tacos</th>
                                        <th>Importe</th>
                                        <th>Fecha</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($printOrders as $order)
                                    @php
                                        $books = (int) ceil(max(1, (int) ($order->set->total_participations ?? 0)) / max(1, (int) ($order->participations_per_book ?? 50)));
                                        $paymentIssue = $printOrderIssuesById[$order->id] ?? null;
                                        $orderNeedsDesign = $order->requiresPrintShopDesign();
                                        $orderCanDesign = $order->printShopCanEditDesign() && (bool) $order->design_format_id;
                                    @endphp
                                    <tr>
                                        <td><a href="{{ route('print-shop.orders.show', $order->id) }}" class="fw-semibold">{{ $order->order_code }}</a></td>
                                        <td>{{ $order->entity->name ?? '—' }}</td>
                                        <td>{{ $order->set->set_name ?? ('#'.$order->set_id) }}</td>
                                        <td>{{ $order->lottery->name ?? '—' }}</td>
                                        <td>
                                            <span class="badge {{ \App\Models\PrintOrder::statusBadgeClass((string) $order->status) }} rounded-pill">
                                                {{ \App\Models\PrintOrder::statusLabel((string) $order->status) }}
                                            </span>
                                            @if($orderNeedsDesign)
                                                <div class="small mt-1">
                                                    <span class="badge bg-warning text-dark rounded-pill">Diseño pendiente</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ \App\Models\PrintOrder::paymentStatusBadgeClass($order->payment_status) }} rounded-pill">
                                                {{ \App\Models\PrintOrder::paymentStatusLabel($order->payment_status, $order->payment_provider) }}
                                            </span>
                                            @if($paymentIssue)
                                                <div class="small text-warning mt-1">{{ $paymentIssue['label'] }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $order->participations_per_book ?? '—' }} × {{ $books }}</td>
                                        <td>{{ number_format((float) $order->quoted_amount, 2, ',', '.') }}€</td>
                                        <td>{{ $order->sent_at ? $order->sent_at->format('d/m/Y') : ($order->created_at?->format('d/m/Y') ?? '—') }}</td>
                                        <td class="text-end">
                                            @if($orderCanDesign)
                                                <a href="{{ route('print-shop.orders.design', $order->id) }}" class="btn btn-sm btn-warning text-dark" title="Diseñar participaciones">
                                                    <i class="ri-brush-line"></i>
                                                </a>
                                            @endif
                                            <a href="{{ route('print-shop.orders.show', $order->id) }}" class="btn btn-sm btn-outline-dark">
                                                <i class="ri-eye-line"></i> Ver
                                            </a>
                                            @if($order->design_format_id && ! $orderNeedsDesign)
                                                <a href="{{ route('print-shop.orders.show', $order->id) }}#archivos-impresion" class="btn btn-sm btn-outline-primary" title="Descargar PDF">
                                                    <i class="ri-file-pdf-line"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

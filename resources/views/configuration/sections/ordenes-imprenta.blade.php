<style>
    #configuration-content .alert {
        display: block !important;
    }
    #configuration-content .ordenes-imprenta-table-scroll {
        width: 100%;
        max-width: 100%;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    #configuration-content .ordenes-imprenta-table-scroll .dataTables_wrapper {
        width: 100%;
        min-width: 0;
    }
    #tabla-ordenes-imprenta {
        min-width: 1280px;
    }
    #tabla-ordenes-imprenta td.text-truncate-cell {
        max-width: 180px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    #tabla-ordenes-imprenta .ordenes-imprenta-actions {
        min-width: 140px;
        white-space: nowrap;
    }
    #tabla-ordenes-imprenta .ordenes-imprenta-actions .btn {
        padding: 0.25rem 0.45rem;
    }
    #tabla-ordenes-imprenta .ordenes-imprenta-history-btn {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        flex-wrap: nowrap;
        white-space: nowrap;
        gap: 0.2rem;
        width: auto;
        min-width: 0;
    }
    #tabla-ordenes-imprenta .ordenes-imprenta-history-btn i {
        flex-shrink: 0;
        line-height: 1;
        margin-right: 0 !important;
    }
    #tabla-ordenes-imprenta td:nth-child(7),
    #tabla-ordenes-imprenta th:nth-child(7) {
        min-width: 150px;
    }
    #tabla-ordenes-imprenta td:nth-child(10),
    #tabla-ordenes-imprenta th:nth-child(10) {
        min-width: 118px;
        white-space: nowrap;
    }
</style>
<div class="form-card bs">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h4 class="mb-0">Órdenes Imprenta</h4>
        @php
            $issuesCount = count($printOrderIssuesById ?? []);
            $reconciliationFilter = $printOrdersReconciliationFilter ?? 'all';
        @endphp
        <div class="btn-group btn-group-sm" role="group">
            <a href="{{ route('configuration.index', ['section' => 'ordenes-imprenta', 'reconciliation' => 'all']) }}"
               class="btn {{ $reconciliationFilter === 'all' ? 'btn-dark' : 'btn-outline-dark' }}">Todas</a>
            <a href="{{ route('configuration.index', ['section' => 'ordenes-imprenta', 'reconciliation' => 'issues']) }}"
               class="btn {{ $reconciliationFilter === 'issues' ? 'btn-warning text-dark' : 'btn-outline-warning' }}">
                Incidencias cobro @if($issuesCount > 0)<span class="badge bg-danger ms-1">{{ $issuesCount }}</span>@endif
            </a>
        </div>
    </div>
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($printOrders->isEmpty())
        <div class="alert alert-info mb-0">
            <i class="fe-info me-2"></i>
            No hay órdenes de imprenta registradas todavía.
        </div>
    @else
        @if($issuesCount > 0)
            <div class="alert alert-warning py-2 small mb-3">
                <i class="ri-error-warning-line me-1"></i>
                Hay {{ $issuesCount }} orden(es) con posible desajuste entre pedido y cobro. Usa «Conciliar» o ejecuta <code>php artisan sipart:pending-payments-check</code>.
            </div>
        @endif
        <div class="table-responsive ordenes-imprenta-table-scroll">
            <table id="tabla-ordenes-imprenta" class="table table-striped table-hover align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th>Orden</th>
                        <th>Imprenta</th>
                        <th>Entidad</th>
                        <th>Set</th>
                        <th>Sorteo</th>
                        <th>Estado</th>
                        <th>Cobro</th>
                        <th>Importe</th>
                        <th>Fecha envío</th>
                        <th>Historial</th>
                        <th class="text-end no-export">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($printOrders as $order)
                    @php
                        $canOperate = auth()->user() && (auth()->user()->isSuperAdmin() || auth()->user()->isAdministration());
                        $orderAudits = $printOrderAuditsByOrderId[$order->id] ?? collect();
                        $paymentIssue = $printOrderIssuesById[$order->id] ?? null;
                        if ($reconciliationFilter === 'issues' && !$paymentIssue) {
                            continue;
                        }
                        $paymentBlockReason = $order->paymentTransitionBlockReason();
                    @endphp
                    <tr>
                        <td data-order="{{ $order->order_code }}"><span class="fw-semibold">{{ $order->order_code }}</span></td>
                        <td class="text-truncate-cell" title="{{ $order->printConfiguration?->displayName() ?? '' }}">{{ $order->printConfiguration?->displayName() ?? '—' }}</td>
                        <td class="text-truncate-cell" title="{{ $order->entity->name ?? '' }}">{{ $order->entity->name ?? '—' }}</td>
                        <td>{{ $order->set->set_name ?? ('#'.$order->set_id) }}</td>
                        <td>{{ $order->lottery->name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ \App\Models\PrintOrder::statusBadgeClass((string) $order->status) }} rounded-pill">
                                {{ \App\Models\PrintOrder::statusLabel((string) $order->status) }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ \App\Models\PrintOrder::paymentStatusBadgeClass($order->payment_status) }} rounded-pill">
                                {{ \App\Models\PrintOrder::paymentStatusLabel($order->payment_status, $order->payment_provider) }}
                            </span>
                            @if($order->paid_at)
                                <div class="small text-muted mt-1">{{ $order->paid_at->format('d/m/Y H:i') }}</div>
                            @endif
                            @if($paymentIssue)
                                <div class="small mt-1">
                                    <span class="badge {{ ($paymentIssue['severity'] ?? '') === 'error' ? 'bg-danger' : 'bg-warning text-dark' }} rounded-pill" title="{{ $paymentIssue['label'] ?? '' }}">
                                        {{ $paymentIssue['label'] ?? 'Incidencia' }}
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td data-order="{{ (float) $order->quoted_amount }}">{{ number_format((float) $order->quoted_amount, 2, ',', '.') }}€</td>
                        <td data-order="{{ $order->sent_at?->timestamp ?? $order->created_at?->timestamp ?? 0 }}">
                            {{ $order->sent_at ? $order->sent_at->format('d/m/Y H:i') : '—' }}
                        </td>
                        <td>
                            @if($orderAudits->isNotEmpty())
                                <button type="button" class="btn btn-sm btn-outline-dark ordenes-imprenta-history-btn" data-bs-toggle="modal" data-bs-target="#order-audit-modal-{{ $order->id }}">
                                    <i class="ri-time-line"></i><span>Ver ({{ $orderAudits->count() }})</span>
                                </button>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end ordenes-imprenta-actions">
                            <div class="d-flex justify-content-end gap-1 flex-wrap">
                                @if($canOperate && $order->payment_provider === 'stripe' && $order->payment_intent_id)
                                    <form method="POST" action="{{ route('configuration.print-orders.reconcile-payment', $order->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary" title="Consultar Stripe y alinear estado de cobro">
                                            <i class="ri-refresh-line"></i>
                                        </button>
                                    </form>
                                @endif

                                @if($order->canTransitionTo(\App\Models\PrintOrder::STATUS_IN_PRODUCTION) && $canOperate)
                                    <form method="POST" action="{{ route('configuration.print-orders.status', $order->id) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="target_status" value="{{ \App\Models\PrintOrder::STATUS_IN_PRODUCTION }}">
                                        <button type="submit" class="btn btn-sm btn-info text-dark" title="Marcar en producción">
                                            <i class="ri-hammer-line"></i>
                                        </button>
                                    </form>
                                @endif

                                @if($order->canTransitionTo(\App\Models\PrintOrder::STATUS_SENT) && $canOperate)
                                    <form method="POST" action="{{ route('configuration.print-orders.status', $order->id) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="target_status" value="{{ \App\Models\PrintOrder::STATUS_SENT }}">
                                        <button type="submit" class="btn btn-sm btn-success" title="Marcar enviada">
                                            <i class="ri-truck-line"></i>
                                        </button>
                                    </form>
                                @endif

                                @if($order->canTransitionTo(\App\Models\PrintOrder::STATUS_REJECTED) && $canOperate)
                                    <form method="POST" action="{{ route('configuration.print-orders.status', $order->id) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="target_status" value="{{ \App\Models\PrintOrder::STATUS_REJECTED }}">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Rechazar orden">
                                            <i class="ri-close-circle-line"></i>
                                        </button>
                                    </form>
                                @endif

                                @if($order->canTransitionTo(\App\Models\PrintOrder::STATUS_PENDING_REVIEW) && $canOperate)
                                    <form method="POST" action="{{ route('configuration.print-orders.status', $order->id) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="target_status" value="{{ \App\Models\PrintOrder::STATUS_PENDING_REVIEW }}">
                                        <button type="submit" class="btn btn-sm btn-warning text-dark" title="Reabrir en revisión">
                                            <i class="ri-restart-line"></i>
                                        </button>
                                    </form>
                                @endif

                                @if(!$canOperate)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Solo administración o super admin puede cambiar estados.">
                                        <i class="ri-lock-line"></i>
                                    </button>
                                @elseif($paymentBlockReason && in_array($order->status, [\App\Models\PrintOrder::STATUS_PENDING_REVIEW, \App\Models\PrintOrder::STATUS_IN_PRODUCTION], true))
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="{{ $paymentBlockReason }}">
                                        <i class="ri-bank-card-line"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @foreach($printOrders as $order)
            @php($orderAudits = $printOrderAuditsByOrderId[$order->id] ?? collect())
            @if($orderAudits->isNotEmpty())
                <div class="modal fade" id="order-audit-modal-{{ $order->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Historial de estados - {{ $order->order_code }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Usuario</th>
                                                <th>De</th>
                                                <th>A</th>
                                                <th>Detalle</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($orderAudits->take(25) as $audit)
                                            <tr>
                                                <td>{{ \Carbon\Carbon::parse($audit->created_at)->format('d/m/Y H:i') }}</td>
                                                <td>{{ $audit->user_name ?? 'Sistema' }}</td>
                                                <td>{{ $audit->from_status ? \App\Models\PrintOrder::statusLabel((string) $audit->from_status) : '—' }}</td>
                                                <td>{{ $audit->to_status ? \App\Models\PrintOrder::statusLabel((string) $audit->to_status) : '—' }}</td>
                                                <td>{{ $audit->message ?? 'Cambio de estado' }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    @endif
</div>

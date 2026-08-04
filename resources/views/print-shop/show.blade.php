@extends('layouts.layout')

@section('title', 'Orden '.$printOrder->order_code)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('print-shop.index') }}">Panel Imprenta</a></li>
                        <li class="breadcrumb-item active">{{ $printOrder->order_code }}</li>
                    </ol>
                </div>
                <h4 class="page-title">Orden {{ $printOrder->order_code }}</h4>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row">
        <div class="col-12">
    <div class="card" style="min-height: calc(100vh - 335px);">
        <div class="card-body">                
                
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="form-card bs" style="min-height: calc(100vh - 283px);">
                        <div class="">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span class="badge {{ \App\Models\PrintOrder::statusBadgeClass((string) $printOrder->status) }} rounded-pill fs-6">
                                        {{ \App\Models\PrintOrder::statusLabel((string) $printOrder->status) }}
                                    </span>
                                </div>
                                <div class="text-end">
                                    <div class="fw-semibold fs-5">{{ number_format((float) $printOrder->quoted_amount, 2, ',', '.') }}€</div>
                                    <div class="small text-muted">Presupuesto acordado</div>
                                </div>
                            </div>

                            <div class="row g-3 small">
                                <div class="col-md-6">
                                    <div class="text-muted">Entidad</div>
                                    <div class="fw-semibold">{{ $printOrder->entity->name ?? '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted">Set</div>
                                    <div class="fw-semibold">{{ $printOrder->set->set_name ?? ('#'.$printOrder->set_id) }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted">Sorteo</div>
                                    <div class="fw-semibold">{{ $printOrder->lottery->name ?? '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted">Participaciones totales</div>
                                    <div class="fw-semibold">{{ number_format((int) ($printOrder->set->total_participations ?? 0), 0, ',', '.') }}</div>
                                </div>
                            </div>

                            <hr>

                            <h5 class="mb-3">Parámetros de impresión</h5>
                            <div class="row g-3 small">
                                <div class="col-md-4">
                                    <div class="text-muted">Formato</div>
                                    <div>{{ strtoupper(str_replace('_', ' ', (string) ($printOrder->print_size ?? '—'))) }}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted">Participaciones por taco</div>
                                    <div>{{ $printOrder->participations_per_book ?? '—' }}</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-muted">Trasera</div>
                                    <div>{{ match ($printOrder->back_mode ?? '') {
                                        'color' => 'Color',
                                        'none' => 'Omitida',
                                        default => 'Blanco y negro',
                                    } }}</div>
                                </div>
                            </div>

                            @if($printOrder->notes)
                                <hr>
                                <h5 class="mb-2">Observaciones</h5>
                                <div class="border rounded p-3 bg-light small" style="white-space: pre-wrap;">{{ $printOrder->notes }}</div>
                            @endif

                            @if(is_array($printOrder->quote_breakdown))
                                <hr>
                                <h5 class="mb-2">Desglose presupuesto</h5>
                                <ul class="list-unstyled small mb-0">
                                    @foreach(($printOrder->quote_breakdown['subtotal'] ?? []) as $key => $amount)
                                        <li class="d-flex justify-content-between py-1 border-bottom">
                                            <span>{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                                            <strong>{{ number_format((float) $amount, 2, ',', '.') }}€</strong>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <hr>

                            @if($requiresDesign ?? false)
                                <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                    <div>
                                        <i class="ri-palette-line me-1"></i>
                                        <strong>Diseño pendiente.</strong>
                                        Este pedido requiere que la imprenta elabore el diseño de las participaciones.
                                    </div>
                                    @if($canOpenDesignEditor ?? false)
                                        <a href="{{ route('print-shop.orders.design', $printOrder->id) }}" class="btn btn-warning text-dark">
                                            <i class="ri-brush-line me-1"></i> Diseñar participaciones
                                        </a>
                                    @endif
                                </div>
                            @elseif($canOpenDesignEditor ?? false)
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                    <p class="text-muted small mb-0">Puedes ajustar el diseño antes de generar los PDF de impresión.</p>
                                    <a href="{{ route('print-shop.orders.design', $printOrder->id) }}" class="btn btn-outline-primary btn-sm">
                                        <i class="ri-edit-line me-1"></i> Editar diseño
                                    </a>
                                </div>
                            @endif

                            @php
                                $designApprovalService = app(\App\Services\DesignApprovalService::class);
                                $linkedDesign = $printOrder->design;
                                $linkedDesignApprovalStatus = $linkedDesign
                                    ? $designApprovalService->normalizedApprovalStatus($linkedDesign->approval_status)
                                    : null;
                            @endphp
                            @if($linkedDesign && $designApprovalService->isPrintShopDesign($linkedDesign))
                                @if($linkedDesignApprovalStatus === \App\Services\DesignApprovalService::STATUS_DRAFT && ($canSubmitToEntity ?? false))
                                    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <div>
                                            <i class="ri-send-plane-line me-1"></i>
                                            El diseño está listo. Debe enviarse a la entidad para su aprobación antes de generar los PDF.
                                        </div>
                                        <form action="{{ route('print-shop.orders.submit-approval', $printOrder->id) }}" method="POST" onsubmit="return confirm('¿Enviar este diseño a la entidad para su aprobación?');">
                                            @csrf
                                            <button type="submit" class="btn btn-warning text-dark">
                                                <i class="ri-send-plane-line me-1"></i> Enviar a la entidad
                                            </button>
                                        </form>
                                    </div>
                                @elseif($linkedDesignApprovalStatus === \App\Services\DesignApprovalService::STATUS_PENDING)
                                    <div class="alert alert-info mb-3">
                                        <i class="ri-time-line me-1"></i>
                                        Diseño enviado a la entidad. Pendiente de su aprobación antes de generar los PDF de impresión.
                                    </div>
                                @elseif($linkedDesignApprovalStatus === \App\Services\DesignApprovalService::STATUS_REJECTED)
                                    <div class="alert alert-danger d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <div>
                                            <i class="ri-close-circle-line me-1"></i>
                                            <strong>Rechazado por la entidad.</strong>
                                            @if(filled($linkedDesign->approval_rejection_reason))
                                                <span class="d-block mt-1 small"><strong>Motivo:</strong> {{ $linkedDesign->approval_rejection_reason }}</span>
                                            @endif
                                            Corrija el diseño y guárdelo de nuevo para reenviarlo a la entidad.
                                        </div>
                                        @if($canOpenDesignEditor ?? false)
                                            <a href="{{ route('print-shop.orders.design', $printOrder->id) }}" class="btn btn-danger">
                                                <i class="ri-edit-line me-1"></i> Corregir diseño
                                            </a>
                                        @endif
                                    </div>
                                @elseif($linkedDesignApprovalStatus === \App\Services\DesignApprovalService::STATUS_APPROVED)
                                    <div class="alert alert-success mb-3">
                                        <i class="ri-check-line me-1"></i>
                                        Diseño aprobado por la entidad. Ya puede generar los PDF de impresión.
                                    </div>
                                @endif
                            @endif

                            @if(!empty($briefingInvitation) && (filled($briefingInvitation->comment) || $briefingInvitation->files->isNotEmpty()))
                                <h5 class="mb-2">Briefing del cliente</h5>
                                @if(filled($briefingInvitation->comment))
                                    <div class="border rounded p-3 bg-light small mb-3" style="white-space: pre-wrap;">{{ $briefingInvitation->comment }}</div>
                                @endif
                                @if($briefingInvitation->files->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        @foreach($briefingInvitation->files as $f)
                                            <a href="{{ route('print-shop.orders.briefing-file', [$printOrder->id, $f->id]) }}" class="btn btn-sm btn-outline-dark">
                                                <i class="ri-download-2-line me-1"></i>{{ $f->original_name ?: basename($f->path) }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                                <hr>
                            @endif

                            <h5 class="mb-2" id="archivos-impresion">Archivos para imprimir</h5>
                            @php
                                $design = $printOrder->design;
                                $isDigitalSet = $printOrder->set
                                    && ($printOrder->set->digital_participations ?? 0) > 0
                                    && (int) ($printOrder->set->physical_participations ?? 0) === 0;
                                $hasCover = $design && !empty($design->cover_html);
                                $hasBack = $design && $design->hasBackDesign();
                            @endphp
                            @if(!$design)
                                <p class="text-muted small mb-0">Esta orden no tiene un diseño vinculado. Contacta con Partilot si necesitas los archivos.</p>
                            @elseif($isDigitalSet)
                                <p class="text-muted small mb-0">Set digital: no requiere PDF de participaciones físicas.</p>
                            @elseif($design && $designApprovalService->blocksQrExport($design))
                                <p class="text-muted small mb-0">{{ $designApprovalService->blockMessage($design) }}</p>
                            @else
                                <p class="text-muted small mb-3">Genera y descarga los PDF del pedido. La generación puede tardar unos minutos según el volumen.</p>
                                <div class="d-flex flex-wrap gap-2">
                                    @php
                                        $pdfOut = is_array($design->output) ? $design->output : [];
                                        $pdfCoverCount = is_array($pdfOut['taco_qrs'] ?? null) ? count($pdfOut['taco_qrs']) : 0;
                                    @endphp
                                    <button type="button"
                                        class="btn btn-primary btn-sm js-design-pdf-async"
                                        data-async-url="{{ route('design.exportParticipationPdfAsync', $design->id) }}"
                                        data-pdf-dialog="participation"
                                        data-rows="{{ (int) ($design->rows ?? 1) }}"
                                        data-cols="{{ (int) ($design->cols ?? 1) }}"
                                        data-documents-mode="{{ $pdfOut['documents_mode'] ?? '1' }}"
                                        data-pages-per-document="{{ $pdfOut['pages_per_document'] ?? 150 }}"
                                        data-total-participations="{{ $printOrder->set ? (int) $printOrder->set->total_participations : 0 }}"
                                        data-design-name="{{ $design->design_name ?: ('Diseño ' . $design->id) }}"
                                        data-title="Participaciones">
                                        <i class="ri-file-pdf-line me-1"></i> PDF participaciones
                                    </button>
                                    @if($hasCover)
                                    <button type="button"
                                        class="btn btn-outline-primary btn-sm js-design-pdf-async"
                                        data-async-url="{{ route('design.exportCoverPdfAsync', $design->id) }}"
                                        data-pdf-dialog="covers"
                                        data-rows="{{ (int) ($design->rows ?? 1) }}"
                                        data-cols="{{ (int) ($design->cols ?? 1) }}"
                                        data-cover-count="{{ $pdfCoverCount }}"
                                        data-documents-mode="{{ $pdfOut['documents_mode'] ?? '1' }}"
                                        data-pages-per-document="{{ $pdfOut['pages_per_document'] ?? 150 }}"
                                        data-design-name="{{ $design->design_name ?: ('Diseño ' . $design->id) }}"
                                        data-title="Portadas">
                                        <i class="ri-file-pdf-line me-1"></i> PDF portadas (tacos)
                                    </button>
                                    @endif
                                    @if($hasBack)
                                    <button type="button"
                                        class="btn btn-outline-secondary btn-sm js-design-pdf-async"
                                        data-async-url="{{ route('design.exportBackPdfAsync', $design->id) }}"
                                        data-pdf-dialog="backs"
                                        data-rows="{{ (int) ($design->rows ?? 1) }}"
                                        data-cols="{{ (int) ($design->cols ?? 1) }}"
                                        data-documents-mode="{{ $pdfOut['documents_mode'] ?? '1' }}"
                                        data-pages-per-document="{{ $pdfOut['pages_per_document'] ?? 150 }}"
                                        data-total-participations="{{ $printOrder->set ? (int) $printOrder->set->total_participations : 0 }}"
                                        data-design-name="{{ $design->design_name ?: ('Diseño ' . $design->id) }}"
                                        data-title="Traseras">
                                        <i class="ri-file-pdf-line me-1"></i> PDF traseras
                                    </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="form-card bs mt-3">
                        <div class="">
                            <h5 class="mb-3">Historial</h5>
                            @if($audits->isEmpty())
                                <p class="text-muted mb-0">Sin eventos registrados.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead><tr><th>Fecha</th><th>Usuario</th><th>Detalle</th></tr></thead>
                                        <tbody>
                                        @foreach($audits as $audit)
                                            <tr>
                                                <td>{{ \Carbon\Carbon::parse($audit->created_at)->format('d/m/Y H:i') }}</td>
                                                <td>{{ $audit->user_name ?? 'Sistema' }}</td>
                                                <td>
                                                    @if($audit->from_status || $audit->to_status)
                                                        {{ $audit->from_status ? \App\Models\PrintOrder::statusLabel((string) $audit->from_status) : '—' }}
                                                        →
                                                        {{ $audit->to_status ? \App\Models\PrintOrder::statusLabel((string) $audit->to_status) : '—' }}
                                                    @endif
                                                    @if($audit->message)
                                                        <div class="text-muted">{{ $audit->message }}</div>
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

                <div class="col-lg-4">
                    @if(($requiresDesign ?? false) || ($canOpenDesignEditor ?? false))
                    <div class="form-card bs">
                        <div class="">
                            <h5 class="mb-3">Diseño</h5>
                            @if($requiresDesign ?? false)
                                <span class="badge bg-warning text-dark rounded-pill mb-2">Pendiente de diseño</span>
                                <p class="small text-muted mb-3">Debes elaborar el diseño de las participaciones para poder generar los PDF.</p>
                            @else
                                <p class="small text-muted mb-3">El diseño ya tiene contenido. Puedes revisarlo o ajustarlo si es necesario.</p>
                            @endif
                            @if($canOpenDesignEditor ?? false)
                                <a href="{{ route('print-shop.orders.design', $printOrder->id) }}" class="btn btn-primary w-100">
                                    <i class="ri-brush-line me-1"></i>
                                    {{ ($requiresDesign ?? false) ? 'Diseñar participaciones' : 'Abrir editor de diseño' }}
                                </a>
                            @else
                                <p class="small text-muted mb-0">No hay un diseño vinculado a esta orden. Contacta con Partilot.</p>
                            @endif
                        </div>
                    </div>
                    @endif

                    <div class="form-card bs {{ (($requiresDesign ?? false) || ($canOpenDesignEditor ?? false)) ? 'mt-3' : '' }}">
                        <div class="">
                            <h5 class="mb-3">Cobro</h5>
                            <span class="badge {{ \App\Models\PrintOrder::paymentStatusBadgeClass($printOrder->payment_status) }} rounded-pill">
                                {{ \App\Models\PrintOrder::paymentStatusLabel($printOrder->payment_status, $printOrder->payment_provider) }}
                            </span>
                            @if($printOrder->paid_at)
                                <div class="small text-muted mt-2">Cobrado: {{ $printOrder->paid_at->format('d/m/Y H:i') }}</div>
                            @endif
                            @if($paymentIssue ?? null)
                                <div class="alert alert-warning small py-2 mt-3 mb-0">{{ $paymentIssue['label'] }}</div>
                            @endif
                        </div>
                    </div>

                    @php
                        $canChangePrintOrderStatus = $printOrder->canTransitionTo(\App\Models\PrintOrder::STATUS_IN_PRODUCTION)
                            || $printOrder->canTransitionTo(\App\Models\PrintOrder::STATUS_SENT)
                            || $printOrder->canTransitionTo(\App\Models\PrintOrder::STATUS_REJECTED)
                            || $printOrder->canTransitionTo(\App\Models\PrintOrder::STATUS_PENDING_REVIEW);
                    @endphp
                    @if($canChangePrintOrderStatus)
                    <div class="form-card bs mt-3">
                        <div class="">
                            <h5 class="mb-3">Cambiar estado</h5>
                            <div class="d-grid gap-2">
                                @if($printOrder->canTransitionTo(\App\Models\PrintOrder::STATUS_IN_PRODUCTION))
                                    @if($canSubmitToEntity ?? false)
                                        <form action="{{ route('print-shop.orders.submit-approval', $printOrder->id) }}" method="POST" onsubmit="return confirm('¿Enviar este diseño a la entidad para su aprobación?');">
                                            @csrf
                                            <button type="submit" class="btn btn-warning text-dark w-100">
                                                <i class="ri-send-plane-line me-1"></i> Enviar diseño a la entidad
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('print-shop.orders.status', $printOrder->id) }}">
                                            @csrf
                                            <input type="hidden" name="target_status" value="{{ \App\Models\PrintOrder::STATUS_IN_PRODUCTION }}">
                                            <button type="submit" class="btn btn-info text-dark w-100"><i class="ri-hammer-line me-1"></i> Marcar en producción</button>
                                        </form>
                                    @endif
                                @endif
                                @if($printOrder->canTransitionTo(\App\Models\PrintOrder::STATUS_SENT))
                                    <form method="POST" action="{{ route('print-shop.orders.status', $printOrder->id) }}">
                                        @csrf
                                        <input type="hidden" name="target_status" value="{{ \App\Models\PrintOrder::STATUS_SENT }}">
                                        <button type="submit" class="btn btn-success w-100"><i class="ri-truck-line me-1"></i> Marcar enviada</button>
                                    </form>
                                @elseif($printOrder->designApprovalTransitionBlockReason(\App\Models\PrintOrder::STATUS_SENT))
                                    <p class="small text-muted mb-0">{{ $printOrder->designApprovalTransitionBlockReason(\App\Models\PrintOrder::STATUS_SENT) }}</p>
                                @endif
                                @if($printOrder->canTransitionTo(\App\Models\PrintOrder::STATUS_REJECTED))
                                    <form method="POST" action="{{ route('print-shop.orders.status', $printOrder->id) }}">
                                        @csrf
                                        <input type="hidden" name="target_status" value="{{ \App\Models\PrintOrder::STATUS_REJECTED }}">
                                        <button type="submit" class="btn btn-danger w-100"><i class="ri-close-circle-line me-1"></i> Rechazar</button>
                                    </form>
                                @endif
                                @if($printOrder->canTransitionTo(\App\Models\PrintOrder::STATUS_PENDING_REVIEW))
                                    <form method="POST" action="{{ route('print-shop.orders.status', $printOrder->id) }}">
                                        @csrf
                                        <input type="hidden" name="target_status" value="{{ \App\Models\PrintOrder::STATUS_PENDING_REVIEW }}">
                                        <button type="submit" class="btn btn-warning text-dark w-100">
                                            <i class="ri-restart-line me-1"></i>
                                            {{ $linkedDesignApprovalStatus === \App\Services\DesignApprovalService::STATUS_REJECTED ? 'Reabrir para corregir diseño' : 'Reabrir revisión' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="mt-3">
                        <a href="{{ route('print-shop.index') }}" class="btn btn-dark w-100"><i class="ri-arrow-left-line me-1"></i> Volver al listado</a>
                    </div>
                </div>
            </div>
        
        </div>

    </div>

    </div>
    </div>
</div>
@endsection

@section('scripts')
    @include('design.partials.async_design_pdf')
@endsection

@extends('layouts.layout')

@section('title', 'Resumen - Diseño guardado')

@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('design.index') }}">Diseño e Impresión</a></li>
                        <li class="breadcrumb-item active">Resumen</li>
                    </ol>
                </div>
                <h4 class="page-title">Diseño guardado</h4>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body text-center py-5">
                    @if(session('success'))
                        <div class="alert alert-success text-start">{{ session('success') }}</div>
                    @endif
                    @if(session('warning'))
                        <div class="alert alert-warning text-start">{{ session('warning') }}</div>
                    @endif
                    <p class="text-success mb-4 fs-5">
                        <i class="ri-checkbox-circle-line me-1"></i>
                        La configuración del diseño se ha guardado correctamente.
                    </p>
                    <p class="text-muted mb-4">
                        Puedes descargar los PDF generados o volver al listado de diseños.
                    </p>

                    @if(!empty($designApproval) && !empty($designApproval['required']))
                        <div class="alert {{ !empty($designApproval['blocks_export']) ? 'alert-warning' : 'alert-light border' }} text-start mx-auto mb-4" style="max-width: 540px;">
                            <h5 class="mb-3">Aprobación de la entidad</h5>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Estado</span>
                                <strong>{{ $designApproval['status_label'] }}</strong>
                            </div>
                            @if(!empty($designApproval['rejection_reason']))
                                <p class="small text-danger mb-2">{{ $designApproval['rejection_reason'] }}</p>
                            @endif
                            @if(!empty($designApproval['can_submit']))
                                <form action="{{ route('design.submitForApproval', $design->id) }}" method="POST" class="text-end mt-3" onsubmit="return confirm('¿Enviar este diseño a la entidad para su aprobación?');">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-sm text-dark">
                                        <i class="ri-send-plane-line me-1"></i> Enviar a la entidad para aprobación
                                    </button>
                                </form>
                            @endif
                            @if(!empty($designApproval['can_review']))
                                <div class="text-end mt-3">
                                    <a href="{{ route('design.approval.review', $design->id) }}" class="btn btn-primary btn-sm">
                                        <i class="ri-eye-line me-1"></i> Revisar y aprobar
                                    </a>
                                </div>
                            @endif
                            @if(!empty($designApproval['blocks_export']) && ($designApproval['status'] ?? '') === 'pending_approval')
                                <p class="small text-muted mb-0 mt-3">
                                    Los PDF con códigos QR permanecen bloqueados hasta que la entidad apruebe el diseño.
                                </p>
                            @endif
                        </div>
                    @endif

                    @if(!empty($managementFee))
                        <div class="alert {{ !empty($managementFee['blocks_export']) ? 'alert-warning' : 'alert-light border' }} text-start mx-auto mb-4" style="max-width: 540px;">
                            <h5 class="mb-3">Cuota de gestión PARTILOT</h5>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Estado</span>
                                <strong>{{ $managementFee['status_label'] }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Pagador</span>
                                <strong>{{ $managementFee['payer_label'] }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Participaciones</span>
                                <strong>{{ number_format($managementFee['participation_count'], 0, ',', '.') }}</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Importe unitario</span>
                                <strong>{{ number_format($managementFee['unit_price'], 4, ',', '.') }}€</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Importe total</span>
                                <strong>{{ number_format($managementFee['amount'], 2, ',', '.') }}€</strong>
                            </div>
                            @if(!empty($managementFee['paid_at']))
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Pagada el</span>
                                    <strong>{{ $managementFee['paid_at']->format('d/m/Y H:i') }}</strong>
                                </div>
                            @endif
                            @if(!empty($managementFee['blocks_export']) && empty($managementFee['awaiting_approval']))
                                <p class="small text-muted mb-3 mt-3">
                                    Los archivos con códigos QR permanecen bloqueados hasta confirmar el pago de la cuota de gestión.
                                </p>
                            @elseif(!empty($managementFee['awaiting_approval']))
                                <p class="small text-muted mb-3 mt-3">
                                    El importe se calculará cuando la entidad apruebe el diseño.
                                </p>
                            @elseif(!empty($managementFee['uses_remittance']) && ($managementFee['status'] ?? '') === 'queued_for_remittance')
                                <p class="small text-muted mb-3 mt-3">
                                    Cargo registrado en remesa {{ strtolower($managementFee['remittance_frequency_label'] ?? '') }}. Se adeudará en el próximo ciclo de facturación.
                                </p>
                            @endif
                            @if(!empty($managementFee['can_pay_stripe']) && empty($managementFee['awaiting_approval']))
                                <div class="text-end mt-3">
                                    <a href="{{ route('design.managementFee.pay', $design->set_id) }}" class="btn btn-success btn-sm">
                                        <i class="ri-bank-card-line me-1"></i> Pagar cuota con tarjeta
                                    </a>
                                </div>
                            @endif
                            @if(!empty($managementFee['can_queue_remittance']) && empty($managementFee['awaiting_approval']))
                                <form action="{{ route('design.managementFee.confirmRemittance', $design->set_id) }}" method="POST" class="text-end mt-3" onsubmit="return confirm('¿Confirmar el cargo de cuota de gestión en la próxima remesa {{ strtolower($managementFee['remittance_frequency_label'] ?? '') }}?');">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="ri-bank-line me-1"></i> Confirmar cargo en remesa
                                    </button>
                                </form>
                            @endif
                            @if(!empty($managementFee['can_mark_paid']) && empty($managementFee['awaiting_approval']))
                                <form action="{{ route('design.markManagementFeePaid', $design->set_id) }}" method="POST" class="text-end mt-3" onsubmit="return confirm('¿Confirmar el pago de la cuota de gestión PARTILOT?');">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="ri-check-line me-1"></i> Confirmar pago cuota gestión
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif

                    @php
                        $isDigitalSet = $design->set
                            && ($design->set->digital_participations ?? 0) > 0
                            && (int) ($design->set->physical_participations ?? 0) === 0;
                        $hasCover = !empty($design->cover_html);
                        $hasBack = !empty($design->back_html);
                        $blocksQrExport = !empty($managementFee['blocks_export']);
                    @endphp
                    <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
                        @if($isDigitalSet)
                            @if($blocksQrExport)
                                <button type="button" class="btn btn-primary" disabled title="Cuota de gestión PARTILOT pendiente">
                                    <i class="ri-image-line me-1"></i> Descargar imagen participación digital
                                </button>
                            @else
                                <a target="_blank" href="{{ route('design.digitalParticipationImage', $design->id) }}" class="btn btn-primary">
                                    <i class="ri-image-line me-1"></i> Descargar imagen participación digital
                                </a>
                            @endif
                        @else
                        @if($blocksQrExport)
                            <button type="button" class="btn btn-primary" disabled title="Cuota de gestión PARTILOT pendiente">
                                <i class="ri-file-pdf-line me-1"></i> Descargar PDF participaciones
                            </button>
                        @else
                        <button type="button"
                            class="btn btn-primary js-design-pdf-async"
                            data-async-url="{{ route('design.exportParticipationPdfAsync', $design->id) }}"
                            data-pdf-dialog="participation"
                            data-total-participations="{{ $design->set ? (int)$design->set->total_participations : 0 }}"
                            data-title="Participaciones">
                            <i class="ri-file-pdf-line me-1"></i> Descargar PDF participaciones
                        </button>
                        @endif
                        @endif
                        @if(!$isDigitalSet && $hasCover)
                        <button type="button"
                            class="btn btn-outline-primary js-design-pdf-async"
                            data-async-url="{{ route('design.exportCoverPdfAsync', $design->id) }}"
                            data-title="Portadas">
                            <i class="ri-file-pdf-line me-1"></i> PDF portadas (tacos)
                        </button>
                        @endif
                        @if(!$isDigitalSet && $hasBack)
                        <button type="button"
                            class="btn btn-outline-secondary js-design-pdf-async"
                            data-async-url="{{ route('design.exportBackPdfAsync', $design->id) }}"
                            data-pdf-dialog="backs"
                            data-total-participations="{{ $design->set ? (int)$design->set->total_participations : 0 }}"
                            data-title="Traseras">
                            <i class="ri-file-pdf-line me-1"></i> PDF traseras
                        </button>
                        @endif
                        @if(!$isDigitalSet)
                            @if(!empty($printOrderLock['locked']))
                                <button type="button" class="btn btn-outline-warning text-dark" disabled title="{{ $printOrderLock['message'] ?? '' }}">
                                    <i class="ri-send-plane-line me-1"></i> Enviar a imprenta
                                </button>
                            @else
                                <a href="{{ route('design.sendToPrint', $design->id) }}" class="btn btn-warning text-dark">
                                    <i class="ri-send-plane-line me-1"></i> Enviar a imprenta
                                </a>
                            @endif
                        @endif
                    </div>

                    @if(!empty($latestPrintOrder))
                        <div class="alert alert-light border text-start mx-auto" style="max-width: 540px;">
                            <div class="d-flex justify-content-between">
                                <span>Última orden</span>
                                <strong>{{ $latestPrintOrder->order_code }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Estado</span>
                                <strong>{{ \App\Models\PrintOrder::statusLabel((string) $latestPrintOrder->status) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Importe</span>
                                <strong>{{ number_format((float) $latestPrintOrder->quoted_amount, 2, ',', '.') }}€</strong>
                            </div>
                        </div>
                    @endif

                    <hr class="my-4">

                    <a href="{{ route('design.index') }}" class="btn btn-dark">
                        <i class="ri-arrow-left-line me-1"></i> Volver a Diseño e impresión
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
    @include('design.partials.async_design_pdf')
@endsection

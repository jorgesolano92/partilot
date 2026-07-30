@extends('layouts.layout')

@section('title', 'Resumen - Diseño guardado')

@section('content')

<div class="container-fluid partilot-page-shell">
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

    <div class="row partilot-page-panel-row">
        <div class="col-12">
            <div class="card partilot-page-panel partilot-page-panel--centered show-alerts">
                <div class="card-body text-center py-5">
                    <div class="partilot-page-panel__inner">
                    @if(session('success'))
                        <div class="alert alert-success text-start">{{ session('success') }}</div>
                    @endif
                    @if(session('warning'))
                        <div class="alert alert-warning text-start">{{ session('warning') }}</div>
                    @endif
                    @php
                        $approvalService = app(\App\Services\DesignApprovalService::class);
                        $adminUser = $approvalService->userActsAsAdministration(auth()->user());
                        $entityViewer = auth()->user()->isEntity()
                            && ! auth()->user()->isAdministration()
                            && ! $adminUser;
                        $managementFeeData = $managementFee ?? [];
                        $entityMustPayNow = $entityViewer && (
                            ! empty($awaitingEntityFeeBeforeDesign)
                            || (
                                ! empty($managementFeeData['needs_payment_action'])
                                && ($managementFeeData['payer'] ?? '') === 'entity'
                            )
                        );
                        $entityFeeBlocksEditing = ! empty($awaitingEntityFeeBeforeDesign)
                            || ! empty($entityFeeDue)
                            || (! empty($managementFeeData['payment_before_admin_design']) && $adminUser)
                            || (! empty($managementFeeData['payment_before_editor']) && $entityViewer);
                        $showExportActions = ! $entityMustPayNow && empty($blocksQrExport);
                        $qrBlockTitle = $summaryBlockMessage ?? $approvalService->blockMessage($design);
                        $canDownloadPendingSample = ! empty($canDownloadPendingSample);
                    @endphp

                    @if($entityMustPayNow && !empty($managementFeeData['can_pay_stripe']))
                        <div class="text-center mb-4">
                            <a href="{{ route('design.managementFee.pay', $design->set_id) }}" class="btn btn-success btn-lg">
                                <i class="ri-bank-card-line me-1"></i> Pagar cuota de gestión
                            </a>
                        </div>
                    @elseif($entityMustPayNow && !empty($managementFeeData['can_mark_paid']))
                        <form action="{{ route('design.markManagementFeePaid', $design->set_id) }}" method="POST" class="text-center mb-4" onsubmit="return confirm('¿Confirmar el pago de la cuota de gestión PARTILOT?');">
                            @csrf
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="ri-check-line me-1"></i> Confirmar pago cuota gestión
                            </button>
                        </form>
                    @endif

                    @if(
                        $adminUser
                        && empty($awaitingEntityFeeBeforeDesign)
                        && !empty($hasDesignContent)
                        && $approvalService->canEntityEditDesign(auth()->user(), $design)
                    )
                        <div class="text-center mb-4">
                            @if(!empty($canOpenEditor))
                                <a href="{{ route('design.editFormat', $design->id) }}" class="btn btn-primary">
                                    <i class="ri-palette-line me-1"></i> Editar diseño
                                </a>
                            @elseif(!empty($canPreviewDesign))
                                <a href="{{ route('design.participationPreview', $design->id) }}" class="btn btn-primary">
                                    <i class="ri-eye-line me-1"></i> Ver diseño
                                </a>
                            @endif
                        </div>
                    @endif

                    <div class="design-summary-cards">
                    @if(!empty($exportLocked))
                        <div class="alert alert-secondary text-start design-summary-card">
                            <h5 class="mb-2"><i class="ri-lock-line me-1"></i> Diseño bloqueado</h5>
                            <p class="mb-0 small">Ya se descargó el PDF de participaciones. El diseño no se puede editar; puede seguir descargando archivos desde esta pantalla.</p>
                        </div>
                    @endif

                    @if(!empty($summaryStatus))
                        <div class="alert alert-{{ ($summaryStatus['tone'] ?? 'warning') === 'success' ? 'success' : 'warning' }} text-start design-summary-card">
                            @if(!empty($summaryStatus['title']))
                                <h5 class="mb-2">
                                    @if(($summaryStatus['tone'] ?? '') !== 'success')
                                        <i class="ri-error-warning-line me-1"></i>
                                    @else
                                        <i class="ri-checkbox-circle-line me-1"></i>
                                    @endif
                                    {{ $summaryStatus['title'] }}
                                </h5>
                            @endif
                            <p class="mb-0 small">{{ $summaryStatus['message'] ?? '' }}</p>
                        </div>
                    @endif

                    @if(!empty($printOrderLock['completed']))
                        <div class="alert alert-success text-start design-summary-card">
                            <i class="ri-checkbox-circle-line me-1"></i>
                            <strong>Impresión completada.</strong>
                            La imprenta marcó la orden
                            @if(!empty($latestPrintOrder?->order_code))
                                <strong>{{ $latestPrintOrder->order_code }}</strong>
                            @endif
                            como enviada.
                            @if($entityViewer && !empty($entityFeeDue))
                                Debe abonar la cuota de gestión PARTILOT para activar las participaciones y descargar los archivos.
                            @elseif(!empty($blocksQrExport))
                                Revise los pasos pendientes (cuota de gestión o aprobación) para habilitar descargas.
                            @else
                                Ya puede descargar los archivos desde esta pantalla.
                            @endif
                        </div>
                    @elseif(!empty($printOrderLock['locked']))
                        <div class="alert alert-info text-start design-summary-card">
                            <i class="ri-printer-line me-1"></i>
                            <strong>En imprenta.</strong>
                            @if(!empty($latestPrintOrder?->order_code))
                                Orden <strong>{{ $latestPrintOrder->order_code }}</strong>
                                ({{ \App\Models\PrintOrder::statusLabel((string) $latestPrintOrder->status) }}).
                            @endif
                            El diseño no puede modificarse mientras la imprenta trabaja en el pedido.
                        </div>
                    @endif

                    @if($entityViewer && !empty($entityFeeDue) && empty($awaitingEntityFeeBeforeDesign))
                        <div class="alert alert-warning text-start design-summary-card">
                            <h5 class="mb-2"><i class="ri-error-warning-line me-1"></i> Cuota de gestión PARTILOT</h5>
                            <p class="mb-3 small">
                                @if(!empty($printOrderLock['completed']))
                                    La imprenta ya ha completado el trabajo.
                                @else
                                    El diseño está listo para activar.
                                @endif
                                Debe abonar la cuota de gestión
                                @if(!empty($managementFee['amount']))
                                    ({{ number_format($managementFee['amount'], 2, ',', '.') }}€)
                                @endif
                                para activar las participaciones y descargar los archivos con códigos QR.
                                @if(!empty($designApproval['required']) && ($designApproval['status'] ?? '') !== 'approved')
                                    <span class="d-block mt-2 text-muted">Podrá revisar y aprobar el diseño después del pago.</span>
                                @endif
                            </p>
                            @if(!empty($managementFee['can_pay_stripe']))
                                <a href="{{ route('design.managementFee.pay', $design->set_id) }}" class="btn btn-success">
                                    <i class="ri-bank-card-line me-1"></i> Pagar cuota de gestión
                                </a>
                            @endif
                        </div>
                    @endif

                    @if(!empty($designApproval) && !empty($designApproval['required']))
                        <div id="approval" class="alert {{ !empty($designApproval['blocks_export']) ? 'alert-warning' : 'alert-light border' }} text-start design-summary-card">
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
                            @if($canDownloadPendingSample && !empty($design->participation_html))
                                <div class="text-end mt-3">
                                    <a href="{{ route('design.exportParticipationSamplePdf', $design->id) }}"
                                       class="btn btn-outline-primary btn-sm"
                                       target="_blank"
                                       title="Una hoja con referencias y QR en ceros (sin datos reales)">
                                        <i class="ri-file-pdf-line me-1"></i> Descargar muestra (1 hoja)
                                    </a>
                                </div>
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
                        <div class="alert {{ !empty($managementFee['blocks_export']) ? 'alert-warning' : 'alert-light border' }} text-start design-summary-card">
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
                            @if(!empty($managementFee['payment_before_admin_design']))
                                <p class="small text-warning mb-3">
                                    <strong>Paso pendiente:</strong> la entidad debe confirmar el pago de la cuota antes de que la administración pueda entrar al editor y crear el diseño.
                                </p>
                            @elseif(!empty($managementFee['blocks_export']) && ($managementFee['payer'] ?? '') === 'entity')
                                <p class="small text-warning mb-3 mt-3">
                                    <strong>Paso pendiente:</strong>
                                    @if($entityViewer)
                                        Debe abonar la cuota de gestión
                                        @if(!empty($managementFee['awaiting_approval']))
                                            antes de generar archivos con códigos QR. Puede aprobar el diseño después del pago.
                                        @else
                                            antes de generar archivos con códigos QR.
                                        @endif
                                    @else
                                        La entidad debe abonar la cuota de gestión antes de generar archivos con códigos QR.
                                        El pago solo puede realizarse desde el panel de la entidad.
                                    @endif
                                </p>
                            @elseif(!empty($managementFee['blocks_export']) && empty($managementFee['awaiting_approval']))
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
                            @if(!empty($managementFee['can_queue_remittance']))
                                <form action="{{ route('design.managementFee.confirmRemittance', $design->set_id) }}" method="POST" class="text-end mt-3" onsubmit="return confirm('¿Confirmar el cargo de cuota de gestión en la próxima remesa {{ strtolower($managementFee['remittance_frequency_label'] ?? '') }}?');">
                                    @csrf
                                    <button type="submit" class="btn btn-success">
                                        <i class="ri-bank-line me-1"></i> Confirmar cargo en remesa
                                    </button>
                                </form>
                            @elseif(!empty($managementFee['uses_remittance']) && empty($managementFee['has_valid_iban']) && !empty($managementFee['needs_payment_action']))
                                <p class="small text-danger mb-0 mt-3">
                                    La administración tiene activada la modalidad de remesa pero falta un IBAN válido en su ficha.
                                </p>
                            @endif
                            @if(!empty($managementFee['can_pay_stripe']))
                                <div class="text-end mt-3">
                                    <a href="{{ route('design.managementFee.pay', $design->set_id) }}" class="btn btn-success">
                                        <i class="ri-bank-card-line me-1"></i> Pagar cuota con tarjeta
                                    </a>
                                </div>
                            @endif
                            @if(!empty($managementFee['payment_before_admin_design']))
                                <p class="small text-muted mb-3">
                                    La administración podrá continuar con el diseño cuando la entidad confirme el pago.
                                </p>
                            @endif
                            @if(!empty($managementFee['can_mark_paid']))
                                <form action="{{ route('design.markManagementFeePaid', $design->set_id) }}" method="POST" class="text-end mt-3" onsubmit="return confirm('¿Confirmar el pago de la cuota de gestión PARTILOT?');">
                                    @csrf
                                    <button type="submit" class="btn btn-success">
                                        <i class="ri-check-line me-1"></i> Confirmar pago cuota gestión
                                    </button>
                                </form>
                            @endif
                            @if(
                                !empty($managementFee['needs_payment_action'])
                                && empty($managementFee['can_pay_stripe'])
                                && empty($managementFee['can_queue_remittance'])
                                && empty($managementFee['can_mark_paid'])
                                && ($managementFee['status'] ?? '') !== 'queued_for_remittance'
                            )
                                <p class="small text-muted mb-0 mt-3">
                                    No hay un medio de pago disponible para su perfil. Revise la configuración de facturación de la administración.
                                </p>
                            @endif
                        </div>
                    @endif

                    @if(!empty($latestPrintOrder))
                        <div class="alert alert-light border text-start design-summary-card">
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
                    </div>{{-- /.design-summary-cards --}}

                    @php
                        $isDigitalSet = $design->set
                            && ($design->set->digital_participations ?? 0) > 0
                            && (int) ($design->set->physical_participations ?? 0) === 0;
                        $hasCover = !empty($design->cover_html);
                        $hasBack = $design->hasBackDesign();
                    @endphp
                    @if($showExportActions)
                    <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
                        @if($isDigitalSet)
                            @if($blocksQrExport)
                                <button type="button" class="btn btn-primary" disabled title="{{ $qrBlockTitle }}">
                                    <i class="ri-image-line me-1"></i> Descargar imagen participación digital
                                </button>
                            @else
                                <a target="_blank" href="{{ route('design.digitalParticipationImage', $design->id) }}" class="btn btn-primary">
                                    <i class="ri-image-line me-1"></i> Descargar imagen participación digital
                                </a>
                            @endif
                        @else
                        @if($blocksQrExport)
                            <button type="button" class="btn btn-primary" disabled title="{{ $qrBlockTitle }}">
                                <i class="ri-file-pdf-line me-1"></i> Descargar PDF participaciones
                            </button>
                        @else
                        @php
                            $pdfOutPart = is_array($design->output) ? $design->output : [];
                        @endphp
                        <button type="button"
                            class="btn btn-primary js-design-pdf-async"
                            data-async-url="{{ route('design.exportParticipationPdfAsync', $design->id) }}"
                            data-pdf-dialog="participation"
                            data-rows="{{ (int) ($design->rows ?? 1) }}"
                            data-cols="{{ (int) ($design->cols ?? 1) }}"
                            data-documents-mode="{{ $pdfOutPart['documents_mode'] ?? '1' }}"
                            data-pages-per-document="{{ $pdfOutPart['pages_per_document'] ?? 150 }}"
                            data-total-participations="{{ $design->set ? (int)$design->set->total_participations : 0 }}"
                            data-title="Participaciones">
                            <i class="ri-file-pdf-line me-1"></i> Descargar PDF participaciones
                        </button>
                        @endif
                        @endif
                        @if(!$isDigitalSet && !empty($design->participation_html))
                        <a href="{{ route('design.marketingParticipationImage', $design->id) }}" class="btn btn-outline-success" target="_blank">
                            <i class="ri-share-line me-1"></i> Imagen redes (sin QR)
                        </a>
                        @endif
                        @if(!$isDigitalSet && $hasCover)
                        @php
                            $pdfOutCover = is_array($design->output) ? $design->output : [];
                            $pdfCoverCount = is_array($pdfOutCover['taco_qrs'] ?? null) ? count($pdfOutCover['taco_qrs']) : 0;
                        @endphp
                        <button type="button"
                            class="btn btn-outline-primary js-design-pdf-async"
                            data-async-url="{{ route('design.exportCoverPdfAsync', $design->id) }}"
                            data-pdf-dialog="covers"
                            data-rows="{{ (int) ($design->rows ?? 1) }}"
                            data-cols="{{ (int) ($design->cols ?? 1) }}"
                            data-cover-count="{{ $pdfCoverCount }}"
                            data-documents-mode="{{ $pdfOutCover['documents_mode'] ?? '1' }}"
                            data-pages-per-document="{{ $pdfOutCover['pages_per_document'] ?? 150 }}"
                            data-title="Portadas">
                            <i class="ri-file-pdf-line me-1"></i> PDF portadas (tacos)
                        </button>
                        @endif
                        @if(!$isDigitalSet && $hasBack)
                        @php
                            $pdfOutBack = is_array($design->output) ? $design->output : [];
                        @endphp
                        <button type="button"
                            class="btn btn-outline-secondary js-design-pdf-async"
                            data-async-url="{{ route('design.exportBackPdfAsync', $design->id) }}"
                            data-pdf-dialog="backs"
                            data-rows="{{ (int) ($design->rows ?? 1) }}"
                            data-cols="{{ (int) ($design->cols ?? 1) }}"
                            data-documents-mode="{{ $pdfOutBack['documents_mode'] ?? '1' }}"
                            data-pages-per-document="{{ $pdfOutBack['pages_per_document'] ?? 150 }}"
                            data-total-participations="{{ $design->set ? (int)$design->set->total_participations : 0 }}"
                            data-title="Traseras">
                            <i class="ri-file-pdf-line me-1"></i> PDF traseras
                        </button>
                        @endif
                        @if(!$isDigitalSet)
                            @if(!empty($canSendToPrint))
                                <a href="{{ route('design.sendToPrint', $design->id) }}" class="btn btn-warning text-dark">
                                    <i class="ri-send-plane-line me-1"></i> Enviar a imprenta
                                </a>
                            @else
                                <button type="button" class="btn btn-warning text-dark" disabled title="{{ $sendToPrintBlockReason ?? 'No disponible' }}">
                                    <i class="ri-send-plane-line me-1"></i> Enviar a imprenta
                                </button>
                            @endif
                        @endif
                    </div>

                    @if(!empty($sendToPrintBlockReason) && empty($isDigitalSet))
                        <p class="small text-muted partilot-page-panel__narrow mx-auto mb-4">
                            <i class="ri-information-line me-1"></i> {{ $sendToPrintBlockReason }}
                        </p>
                    @endif
                    @endif

                    @if($entityMustPayNow && empty($managementFeeData['can_pay_stripe']) && empty($managementFeeData['can_mark_paid']))
                        <p class="small text-muted partilot-page-panel__narrow mx-auto mb-4">
                            <i class="ri-information-line me-1"></i>
                            No hay un medio de pago disponible para su perfil. Revise la configuración de facturación de la administración.
                        </p>
                    @elseif(! $showExportActions && ! $entityMustPayNow && ! empty($blocksQrExport))
                        @if(!empty($entityFeeBlocksEditing))
                            <div class="alert alert-warning text-start partilot-page-panel__narrow mx-auto mb-4">
                                <h5 class="mb-2"><i class="ri-error-warning-line me-1"></i> Cuota de gestión pendiente (entidad)</h5>
                                <p class="mb-0 small">{{ $qrBlockTitle }}</p>
                            </div>
                        @elseif(empty($designApproval['required']) || empty($canDownloadPendingSample))
                        <p class="small text-muted partilot-page-panel__narrow mx-auto mb-4">
                            <i class="ri-information-line me-1"></i> {{ $qrBlockTitle }}
                        </p>
                        @endif
                        @if($canDownloadPendingSample && !$isDigitalSet && !empty($design->participation_html) && (empty($designApproval['required'])))
                            <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
                                <a href="{{ route('design.exportParticipationSamplePdf', $design->id) }}"
                                   class="btn btn-outline-primary"
                                   target="_blank"
                                   title="Una hoja con referencias y QR en ceros (sin datos reales)">
                                    <i class="ri-file-pdf-line me-1"></i> Descargar muestra (1 hoja)
                                </a>
                            </div>
                            <p class="small text-muted partilot-page-panel__narrow mx-auto mb-4">
                                Muestra de maquetación: todas las participaciones de la hoja usan referencia en ceros y el QR correspondiente. No incluye números reales del set.
                            </p>
                        @endif
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
</div>

@endsection

@section('scripts')
    @if(!empty($showExportActions))
        @include('design.partials.async_design_pdf')
    @endif
@endsection

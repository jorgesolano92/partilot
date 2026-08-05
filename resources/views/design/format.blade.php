@extends($layout ?? 'layouts.layout')

@section('title','Diseño e Impresión')

@section('content')

@if(!empty($externalInvitation))
<div class="card border-warning shadow-sm mb-3">
    <div class="card-header bg-warning bg-opacity-25 d-flex align-items-center gap-2 py-2">
        <i class="ri-folder-user-line fs-5"></i>
        <strong>Indicaciones y archivos del cliente</strong>
        @if(!empty($printShopOrder))
            <span class="badge bg-dark ms-auto">Orden {{ $printShopOrder->order_code }}</span>
        @endif
    </div>
    <div class="card-body py-3">
        @if(filled($externalInvitation->comment))
            <div class="mb-3">
                <span class="text-muted small d-block mb-1">Comentarios</span>
                <div class="border rounded p-3 bg-light small">{!! nl2br(e($externalInvitation->comment)) !!}</div>
            </div>
        @endif
        @if($externalInvitation->files->isNotEmpty())
            <span class="text-muted small d-block mb-2">Archivos de referencia — descarga para usar en el diseño</span>
            <ul class="list-unstyled mb-0 d-flex flex-wrap gap-2">
                @foreach($externalInvitation->files as $f)
                    <li>
                        <a href="{{ !empty($printShopOrder) ? route('print-shop.orders.briefing-file', [$printShopOrder->id, $f->id]) : route('design.external.downloadFile', $f->id) }}" class="btn btn-sm btn-outline-dark">
                            <i class="ri-download-2-line"></i> {{ $f->original_name ?: basename($f->path) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-muted small mb-0">No se adjuntaron archivos en el pedido.</p>
        @endif
    </div>
</div>
@endif

@if(!empty($printShopOrder))
<div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <i class="ri-printer-line me-1"></i>
        Estás diseñando para la orden <strong>{{ $printShopOrder->order_code }}</strong>.
    </div>
    <a href="{{ route('print-shop.orders.show', $printShopOrder->id) }}" class="btn btn-sm btn-outline-dark">
        <i class="ri-arrow-left-line me-1"></i> Volver a la orden
    </a>
</div>
@endif

@php
    if (!function_exists('getNumberFontSize')) {
        function getNumberFontSize($numbers) {
            $count = is_array($numbers) ? count($numbers) : 1;
            $maxDigits = 0;
            if(is_array($numbers)) {
                foreach($numbers as $n) {
                    $maxDigits = max($maxDigits, strlen($n));
                }
            } else {
                $maxDigits = strlen($numbers);
            }
            if($count == 1 && $maxDigits <= 5) return '72px';
            if($count == 2 || $maxDigits > 5) return '56px';
            if($count >= 3 || $maxDigits > 8) return '40px';
            return '32px';
        }
    }
    if (!function_exists('formatMini')) {
        function formatMini($numbers) {
            $doFormat = function($n) {
                $n = (string) $n;
                // Quitar cualquier carácter no dígito
                $n = preg_replace('/\D/', '', $n);
                // Asegúrate de que tenga exactamente 5 dígitos
                $n = str_pad($n, 5, '0', STR_PAD_LEFT);
                // Coloca el punto antes de los 3 últimos
                return substr($n, 0, 2) . '.' . substr($n, 2);
            };
            if(is_array($numbers)) {
                return implode(' - ', array_map($doFormat, $numbers));
            }
            return $doFormat($numbers);
        }
    }
@endphp

@if(isset($design) && $design)
@php
    $blocks = is_array($design->blocks ?? null) ? $design->blocks : [];
    $loadParticipation = $design->participation_html ?? ($blocks['participation_html'] ?? null);
    $loadCover = $design->cover_html ?? ($blocks['cover_html'] ?? null);
    $loadBack = $design->back_html ?? ($blocks['back_html'] ?? null);
@endphp
<script>
window.__designId = @json($designFormatId ?? $design->id);
window.__designUpdatedAt = @json(optional($design->updated_at)->toISOString());
window.__designLoad = {!! json_encode([
    'format' => $design->format,
    'page' => $design->page,
    'rows' => $design->rows,
    'cols' => $design->cols,
    'orientation' => $design->orientation,
    'margin_up' => $design->margin_up ?? ($design->margins['up'] ?? null),
    'margin_right' => $design->margin_right ?? ($design->margins['right'] ?? null),
    'margin_left' => $design->margin_left ?? ($design->margins['left'] ?? null),
    'margin_top' => $design->margin_top ?? ($design->margins['top'] ?? null),
    'identation' => $design->identation,
    'cut_lines' => $design->cut_lines,
    'matrix_box' => $design->matrix_box,
    'margin_custom' => $design->margin_custom,
    'page_rigth' => $design->page_rigth ?? $design->horizontal_space,
    'page_bottom' => $design->page_bottom ?? $design->vertical_space,
    'participation_html' => $loadParticipation,
    'cover_html' => $loadCover,
    'back_html' => $loadBack,
    'backgrounds' => $design->backgrounds,
    'design_name' => $design->design_name,
    'back_skipped' => (bool) ($design->back_skipped ?? false),
    'updated_at' => optional($design->updated_at)->toISOString(),
]) !!};
window.__defaultDesignName = @json($design->design_name ?: ('Diseño ' . ($set->set_name ?: 'Set ' . $set->id) . ' ' . now()->format('d/m/Y')));
window.__backSkipped = @json((bool) ($design->back_skipped ?? false));
</script>
@else
<script> window.__designId = null; window.__designLoad = null; window.__designUpdatedAt = null; window.__defaultDesignName = @json('Diseño ' . ($set->set_name ?: 'Set ' . $set->id) . ' ' . now()->format('d/m/Y')); window.__backSkipped = false; </script>
@endif
<script>
window.__designLocked = @json((bool)($designLock['locked'] ?? false));
window.__designLockMessage = @json($designLock['message'] ?? 'Este set no permite edición de diseño por estado operativo.');
window.__designContext = {
  entity_id: {{ (int)($entity->id ?? 0) }},
  lottery_id: {{ (int)($lottery->id ?? 0) }},
  reserve_id: {{ (int)($set->reserve_id ?? 0) }},
  set_id: {{ (int)($set->id ?? 0) }}
};
window.__forceFreshDraft = @json((bool)($forceFreshDraft ?? false));
window.__preferServerDesign = @json((bool)($loadedFromPicker ?? false));
</script>
<link rel="stylesheet" href="{{ asset('assets/css/design-editor-ui.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/design-editor-fonts.css') }}">

<style>
    @include('design.partials.design_canvas_styles')

    .design-lock-alert {
        border-radius: 12px;
        border: 1px solid #f0ad4e;
        background: #fff8e1;
        color: #7a4b00;
    }

    .design-locked .format-box {
        pointer-events: none;
        opacity: 0.85;
    }

    .design-locked .format-box-btn,
    .design-locked #open-bg-modal,
    .design-locked #btn-guardar-margenes {
        pointer-events: none;
        opacity: 0.55;
    }

    input[disabled],select[disabled] {
        background-color: #cfcfcf !important;
    }
    /* Redimensionado en el editor (no se guarda en HTML exportado; ver getFormatBoxHtmlForSave) */
    [id^="containment-wrapper"] .elements,
    #design-back-bg .elements {
        resize: both !important;
        overflow: hidden !important;
        box-sizing: border-box;
        min-width: 20px;
        min-height: 20px;
    }
    .elements.text {
        position: absolute;
    }
    .elements.text:hover .edit-btn,
    .elements.images:hover .edit-btn {
        display: block;
    }
    .edit-btn {
        display: none;
        position: absolute;
        top: 5px;
        right: 5px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        cursor: pointer;
        z-index: 10;
        font-size: 12px;
    }
    .edit-btn:hover {
        background: #0056b3;
    }
    .elements.selected {
        outline: 2px solid #007bff;
        outline-offset: 1px;
        box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
    }
    .elements.element-critical {
        z-index: 10000 !important;
    }
    /* Participación/referencia por encima del QR (se solapan en la plantilla) para poder editar */
    .elements.element-critical.participation,
    .elements.element-critical.reference {
        z-index: 10002 !important;
    }
    .elements.element-critical.qr {
        z-index: 10001 !important;
    }
    .elements.element-critical.context.cover-taco-label {
        z-index: 10001 !important;
    }
    /* Área de arrastre: span del QR y primer span de texto ocupan el elemento para handle: 'span' */
    .elements.qr > span {
        display: block;
        width: 100%;
        height: 100%;
        min-width: 100%;
        min-height: 100%;
    }
    .elements.text > span:first-child {
        display: block;
        min-height: 100%;
        min-width: 1px;
    }

    .format-box-btn .btn i {
        font-size: 14px;
        position: relative;
        top: 2px;
    }
    .text-style-btn {
        display: inline-block;
    }
    .text-style-btn.text-vertical-btn.active {
        background-color: #e78307 !important;
        border-color: #e78307 !important;
        color: #333 !important;
    }
    .text-bold { font-weight: bold; }
    .text-italic { font-style: italic; }
    .text-underline { text-decoration: underline; }
    .text-strike { text-decoration: line-through; }
    .text-left { text-align: left; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }

    .format-btn-group button, .format-btn-group label {
      flex: 1 1 0;
      min-width: 36px;
      max-width: none;
    }
    
    .design-zoom-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100%;
        transition: transform 0.2s ease;
    }
    /* Contenedor con scroll cuando el zoom > 100% */
    .design-zoom-scroll {
        overflow: auto;
        max-height: calc(100vh - 200px);
        min-height: 600px;
        width: 100%;
        margin: 0 auto;
        display: flex;
        justify-content: center;
        padding: 20px;
    }
</style>

<!-- Start Content-->
<div class="container-fluid design-editor-page {{ !empty($designLock['locked']) ? 'design-locked' : '' }}">
    @if(!empty($designLock['locked']))
        <div class="alert design-lock-alert mb-3" role="alert">
            <strong>Diseño bloqueado.</strong> {{ $designLock['message'] ?? 'Este set ya tiene operación activa y no permite edición.' }}
        </div>
    @endif
    <div class="modal fade" id="draft-choice-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Borrador detectado</h5>
                </div>
                <div class="modal-body">
                    Tenemos un borrador guardado para este set. ¿Quieres continuar editándolo o empezar un diseño limpio?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="btn-draft-discard">Empezar limpio</button>
                    <button type="button" class="btn btn-warning" id="btn-draft-continue">Continuar editando</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Diseño e Impresión</a></li>
                        <li class="breadcrumb-item active">Añadir</li>
                    </ol>
                </div>
                <h4 class="page-title">Diseño e Impresión</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">

                        <div class="d-flex p-2" style=" align-items: center;justify-content: center;">

                            <div class="form-wizard-element active" style="width: 200px;" id="bc-step-1">
                                
                                <span style="top: -4px; margin-right: 8px;">
                                    1
                                </span>

                                <label>
                                    Configurar <br> Formato
                                </label>

                            </div>

                            <div class="form-wizard-element" style="width: 200px;" id="bc-step-2">
                                
                                <span style="top: -4px; margin-right: 8px;">
                                    2
                                </span>

                                <label>
                                    Diseñar <br> Participación
                                </label>

                            </div>

                            @if(!($isDigitalSet ?? false))
                            <div class="form-wizard-element" style="width: 200px;" id="bc-step-3">
                                
                                <span style="top: -4px; margin-right: 8px;">
                                    3
                                </span>

                                <label>
                                    Diseñar <br> Portada
                                </label>

                            </div>

                            <div class="form-wizard-element" style="width: 200px;" id="bc-step-4">
                                
                                <span style="top: -4px; margin-right: 8px;">
                                    4
                                </span>

                                <label>
                                    Diseñar <br> Trasera
                                </label>

                            </div>

                            <div class="form-wizard-element" style="width: 200px;" id="bc-step-5">
                                
                                <span style="top: -4px; margin-right: 8px;">
                                    5
                                </span>

                                <label>
                                    Configurar <br> Salida
                                </label>

                            </div>
                            @endif
                            
                        </div>

                    </h4>

                    <div class="row">
                        
                        <div class="col-md-12">
                            <div class="form-card fade show bs" id="step-1" style="min-height: 658px;">
                                <h4 class="mb-0 mt-1">
                                    Configuración de Formato
                                </h4>
                                <small><i>Configura el formato de la página y las participaciones</i></small>

                                <br>
                                <br>

                                <div style="min-height: 656px;">

                                    <h4 class="mb-0 mt-1">
                                        Formato de la página
                                    </h4>

                                    <div class="row">
                                        
                                        <div class="col-9">

                                            <div class="row">
                                                
                                                <div class="col-12">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">Plantilla rápida</label>

                                                        <div class="input-group input-group-merge group-form">

                                                            <select class="form-control" name="" id="format" style="border-radius: 30px;">
                                                                <option value="a3-h-3x2">A3 - Apaisado - (3x2)</option>
                                                                <option value="a3-h-4x2">A3 - Apaisado - (4x2)</option>
                                                                <option value="a4-v-3x1">A4 - Vertical - (3x1)</option>
                                                                <option value="a4-v-4x1">A4 - Vertical - (4x1)</option>
                                                                <option value="custom">Personalizado</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-6">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">Tamaño de la página</label>

                                                        <div class="input-group input-group-merge group-form">

                                                            <select class="form-control custom" disabled name="" id="page" style="border-radius: 30px;">
                                                                <option selected value="a3">A3 (297x420)</option>
                                                                <option value="a4">A4 (210x297)</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-3">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">Número de filas</label>

                                                        <div class="input-group input-group-merge group-form">

                                                            <input class="form-control custom" value="3" disabled type="number" id="rows" min="1" max="5" style="border-radius: 30px;">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-3">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">Número de columnas</label>

                                                        <div class="input-group input-group-merge group-form">

                                                            <input class="form-control custom" value="2" disabled type="number" id="cols" min="1" max="5" style="border-radius: 30px;">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-6">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">Orientación</label>

                                                        <div class="input-group input-group-merge group-form">

                                                            <select class="form-control custom" disabled name="" id="orientation" style="border-radius: 30px;">
                                                                <option selected value="h">Apaisado</option>
                                                                <option value="v">Vertical</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12">
                                                    <div class="alert alert-info" id="ticket-info" style="margin-top: 10px;">
                                                        <b>Medidas de la hoja:</b> <span id="sheet-size">-</span><br>
                                                        <b>Medidas de cada participación:</b> <span id="ticket-size">-</span><br>
                                                        <b>Cantidad de participaciones por hoja:</b> <span id="ticket-count">-</span>
                                                    </div>
                                                </div>

                                            </div>  

                                            <h4 class="mb-0 mt-1 d-flex align-items-center">
                                                Configurar márgenes
                                                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="btn-desplegar-margenes" data-bs-toggle="collapse" data-bs-target="#marginsCollapse" aria-expanded="false" aria-controls="marginsCollapse">
                                                    Desplegar
                                                </button>
                                            </h4>

                                            <div class="collapse mt-2" id="marginsCollapse">
                                            <div class="row">
                                                
                                                <div class="col-md-12">

                                                    <div class="row mb-3">
                                                        
                                                        <label class="col-form-label label-control col-4 text-end">Márgenes de la página (mm)</label>

                                                        <div class="col-sm-2">
                                                            <input class="form-control" type="number" id="margin-up" value="1" step="0.1" placeholder="0,00" style="border-radius: 30px">
                                                        </div>

                                                        <div class="col-sm-2">
                                                            <input class="form-control" type="number" id="margin-right" value="1" step="0.1" placeholder="0,00" style="border-radius: 30px">
                                                        </div>

                                                        <div class="col-sm-2">
                                                            <input class="form-control" type="number" id="margin-left" value="1" step="0.1" placeholder="0,00" style="border-radius: 30px">
                                                        </div>

                                                        <div class="col-sm-2">
                                                            <input class="form-control" type="number" id="margin-top" value="1" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                        </div>

                                                    </div>

                                                    <div class="row mb-3">
                                                        
                                                        <label class="col-form-label label-control col-4 text-end">
                                                            Sangres de la imagen (mm)
                                                        </label>

                                                        <div class="col-sm-2">
                                                            <input class="form-control" type="number" id="identation" value="0" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                        </div>

                                                    </div>

                                                    <div class="row mb-3">
                                                        <label class="col-form-label label-control col-4 text-end">
                                                            Líneas de corte (mm)
                                                        </label>
                                                        <div class="col-sm-2">
                                                            <input class="form-control" type="number" id="cut-lines" value="2.50" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        
                                                        <label class="col-form-label label-control col-4 text-end">
                                                            Anchura de la matriz (mm)
                                                        </label>

                                                        <div class="col-sm-2">
                                                            <input class="form-control" type="number" id="matrix-box" value="40.00" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <span class="d-block mt-1">
                                                                (Incluyendo sangres)
                                                            </span>
                                                        </div>

                                                    </div>

                                                    <div class="row mb-3">
                                                        
                                                        <label class="col-form-label label-control col-4 text-end">
                                                            Márgenes de la página (mm)
                                                        </label>

                                                        <div class="col-sm-2">
                                                            <input class="form-control" type="number" id="margin-custom" value="1" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                        </div>

                                                    </div>

                                                    <div class="row mb-3">
                                                        
                                                        <label class="col-form-label label-control col-4 text-end">
                                                            Espacio horizontal entre participaciones (mm)
                                                        </label>

                                                        <div class="col-sm-2">
                                                            <input class="form-control" type="number" id="page-rigth" value="0.00" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                        </div>

                                                    </div>

                                                    <div class="row mb-3">
                                                        
                                                        <label class="col-form-label label-control col-4 text-end">
                                                            Espacio vertical entre participaciones (mm)
                                                        </label>

                                                        <div class="col-sm-2">
                                                            <input class="form-control" type="number" id="page-bottom" value="0.00" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                        </div>

                                                    </div>

                                                    <div class="row mt-3">
                                                        <div class="col-12 text-end">
                                                            <a href="javascript:;" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2" id="btn-guardar-margenes">Guardar
                                                                <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i></a>
                                                        </div>
                                                    </div>
                                                    
                                                </div>

                                            </div>
                                            </div>
                                            
                                        </div>
                                        <div class="col-3">

                                            <div class="preview-design">

                                                <div class="a3">
                                                    <div style="height: 72px;"></div>
                                                    <div style="height: 72px;"></div>
                                                    <div style="height: 72px;"></div>
                                                    <div style="height: 72px;"></div>
                                                    <div style="height: 72px;"></div>
                                                    <div style="height: 72px;"></div>
                                                </div>
                                                
                                            </div>
                                            
                                        </div>
                                    </div>

                                </div>

                            </div>

                            <div class="form-card fade bs d-none" id="step-2" style="min-height: 658px;">
                                @if($isDigitalSet ?? false)
                                <div class="design-digital-banner alert alert-info py-2 px-3 mx-auto" style="max-width: 270mm;">
                                    <strong>Set digital.</strong> Diseñas solo la participación (sin portada ni trasera). La imagen se exportará en PNG para venta digital.
                                </div>
                                @endif
                                <h4 class="mb-0 mt-1">
                                    Configuración de Formato
                                </h4>
                                <small><i>Configura el formato de la página y las participaciones</i></small>

                                <br>

                                {{-- <div style="overflow: auto; height: 658px; width: 100%;"> --}}

                                {{-- Tarea 6: barra centrada y que no se salga del ancho de pantalla --}}
                                <div class="format-box-btn">

                                    <br>

                                    <div class="btn-group format-btn-group">
                                        <button type="button" class="btn btn-sm btn-secondary design-zoom-out" title="Alejar" data-step="2"><i class="ri-zoom-out-line"></i></button>
                                        <button type="button" class="btn btn-sm btn-secondary design-zoom-in" title="Acercar" data-step="2"><i class="ri-zoom-in-line"></i></button>
                                        <span class="align-self-center px-1 design-zoom-label" style="font-size: 12px;">100%</span>
                                        <button title="Agregar texto" class="btn btn-sm btn-dark add-text" data-id="2" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-edit-line"></i></button>
                                        <button title="Agregar imagen" class="btn btn-sm btn-dark add-image" data-id="2" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-image-line"></i></button>
                                        {{-- <button class="btn btn-sm btn-dark add-qr" data-id="2" type="button">QR</button> --}}
                                        {{-- <label class="btn btn-sm btn-dark color" style="position: relative;" data-id="2" type="button">
                                            Fondo<input type="color" style="left: 0; opacity: 0; position: absolute; top: 0;">
                                        </label> --}}
                                        <button title="Fondo de la participación" class="btn btn-sm btn-dark" id="open-bg-modal" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-palette-line"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger reset-mandatory-canvas" data-id="2" title="Elimina los campos de ejemplo y deja solo los obligatorios (número, participación, referencia, QR). Se puede deshacer." style="padding-left: 12px; padding-right: 12px;"><i class="ri-eraser-line"></i></button>
                                        <button title="Mostrar/ocultar guías" class="btn btn-sm btn-dark toggle-guide" data-id="2" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-ruler-line"></i></button>
                                        <label title="Color de guías" class="btn btn-sm btn-dark color-guide" style="position: relative; padding-left: 12px; padding-right: 12px;" data-id="2" type="button">
                                            <i class="ri-palette-line"></i><input type="color" style="left: 0; opacity: 0; position: absolute; top: 0;">
                                        </label>
                                        <button class="btn btn-sm btn-warning undo-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Deshacer"><i class="ri-arrow-go-back-line"></i></button>
                                        <button class="btn btn-sm btn-success redo-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Rehacer"><i class="ri-arrow-go-forward-line"></i></button>
                                        <button class="btn btn-sm btn-danger delete-element-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Eliminar elemento"><i class="ri-delete-bin-6-line"></i></button>
                                        <button class="btn btn-sm btn-dark up-layer" disabled style="padding-left: 12px; padding-right: 12px;" title="Subir capa"><i class="ri-arrow-up-line"></i></button>
                                        <button class="btn btn-sm btn-dark down-layer" disabled style="padding-left: 12px; padding-right: 12px;" title="Bajar capa"><i class="ri-arrow-down-line"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn bold-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Negrita"><i class="ri-bold"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn italic-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Cursiva"><i class="ri-italic"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn underline-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Subrayado"><i class="ri-underline"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn strike-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Tachado"><i class="ri-strikethrough"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn align-left-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Alinear izquierda"><i class="ri-align-left"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn align-center-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Centrar"><i class="ri-align-center"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn align-right-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Alinear derecha"><i class="ri-align-right"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn font-size-up-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Aumentar tamaño"><i class="ri-font-size"></i>+</button>
                                        <button class="btn btn-sm btn-dark text-style-btn font-size-down-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Disminuir tamaño"><i class="ri-font-size"></i>-</button>
                                        <button class="btn btn-sm btn-dark text-style-btn text-vertical-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Texto vertical" aria-pressed="false"><i class="ri-text" style="display:inline-block;transform:rotate(-90deg)"></i></button>
                                    </div>
                                </div>
                                <div class="design-zoom-scroll">
                                    <div class="design-zoom-container" id="design-zoom-wrapper-2" style="transform-origin: top center;">
                                        @php
                                            $matrixBoxMm = (isset($design) && $design) ? (float)($design->matrix_box ?? 40) : 40;
                                        @endphp
                                        @if($isDigitalSet ?? false)
                                        {{-- En digital: contenedor con ancho solo de la participación (sin matriz); .format-box se alinea a la derecha para que la matriz quede fuera --}}
                                        <div class="format-box-digital-wrap" style="width: calc(200mm - {{ $matrixBoxMm }}mm); height: 92mm; margin: auto; position: relative; overflow: hidden;">
                                        @endif
                                        <div class="format-box" style="border:1px solid #c8c8c8; width: 200mm; height: 92mm; @if($isDigitalSet ?? false) right: {{ $matrixBoxMm }}mm; @endif margin: auto; position: relative;">
                                        {{-- Guías de márgenes y matriz --}}
                                        <div class="margen-izquierdo guide2" style="opacity: 1; z-index:1;position: absolute; height: 100%; border-left: 1px solid purple; left: 0mm;"></div>
                                        <div class="margen-arriba guide2" style="opacity: 1; z-index:1;position: absolute; width: 100%; border-top: 1px solid purple; top: 0mm;"></div>
                                        <div class="margen-derecho guide2" style="opacity: 1; z-index:1;position: absolute; height: 100%; border-right: 1px solid purple; right: 0mm;"></div>
                                        <div class="margen-abajo guide2" style="opacity: 1; z-index:1;position: absolute; width: 100%; border-bottom: 1px solid purple; bottom: 0mm;"></div>
                                        <div class="caja-matriz guide2" style="opacity: 1; z-index:1;position: absolute; width: 40mm; border-right: 1px solid purple; height: 100%; left: 0mm;"></div>

                                        <div id="containment-wrapper2" style="width: 100%; height: calc(100% - 0mm); background-size: cover; background-position: center;"> 



                                              

                                             <div class="elements element-critical number text ui-draggable" style="padding: 10px; width: 274px; height: 94px; resize: both; overflow: hidden; position: relative; left: 452px; top: 25.875px;">
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                                <span class="ui-draggable-handle"><h1><span style="color:hsl(0,0%,0%);font-size:{{ getNumberFontSize($reservation_numbers) }};" class="ui-draggable-handle"><strong>{{ is_array($reservation_numbers) ? implode(' - ', $reservation_numbers) : $reservation_numbers }}</strong></span></h1></span>
                                            </div>

                                            {{-- <div class="elements text ui-draggable" style="resize: both; overflow: hidden; position: relative; left: 418px; top: 122.011px; width: 316px; height: 85px;">
                                                <span class="ui-draggable-handle"><h5 style="text-align:center;"><span style="color:hsl(0,0%,0%);font-size:10px;" class="ui-draggable-handle">El portador de la presente participación juega DOS EUROS&nbsp;</span><br><span style="color:hsl(0,0%,0%);font-size:10px;" class="ui-draggable-handle">en cada número arriba indicado para el sorteo de Loteria Nacional&nbsp;</span><br><span style="color:hsl(0,0%,0%);font-size:10px;" class="ui-draggable-handle">que se celebrará el 22 de Diciembre de 2025&nbsp;</span><br><span style="font-size:10px;" class="ui-draggable-handle">&nbsp;</span></h5></span>
                                                
                                            </div> --}}
                                            
                                            
                                            <div class="elements text ui-draggable" style="padding: 10px; width: 144px; height: 94px; resize: both; overflow: hidden; position: absolute; top: 0px; left: 12px;">
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                                <span class="ui-draggable-handle"><h6 style="text-align:center;"><span style="font-size:20px;" class="ui-draggable-handle"><strong>LOTERÍA</strong></span><br><span style="font-size:20px;" class="ui-draggable-handle"><strong>NACIONAL</strong></span></h6></span>
                                            </div>
                                                <div class="elements text ui-draggable" style="padding: 10px; width: 200px; height: 120px; resize: both; overflow: hidden; position: absolute; top: 144px; left: 158px;">
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                                <span class="ui-draggable-handle"><h5 style="text-align:center;"><span style="color:hsl(0,0%,0%);font-size:10px;" class="ui-draggable-handle">DATOS DE LA EMPRESA</span><br><span style="color:hsl(0,0%,0%);font-size:10px;" class="ui-draggable-handle">NOMBRE</span><br><span style="color:hsl(0,0%,0%);font-size:10px;" class="ui-draggable-handle">C/NOMBRE DE LA VIA</span><br><span style="color:hsl(0,0%,0%);font-size:10px;" class="ui-draggable-handle">TELEFONO</span><br><span style="color:hsl(0,0%,0%);font-size:10px;" class="ui-draggable-handle">DATOS</span></h5></span>
                                            </div><div class="elements text ui-draggable" style="padding: 10px; width: 82px; height: 44px; resize: both; overflow: hidden; position: absolute; top: 144px; left: 42px;">
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                                <span class="ui-draggable-handle"><p><span style="color:hsl(0, 0%, 0%);" class="ui-draggable-handle"><strong>25/07/25</strong></span></p></span>
                                            </div><div class="elements element-critical text number mini ui-draggable" style="padding: 10px; width: 74px; height: 43px; resize: both; overflow: hidden; position: absolute; top: 180.797px; left: 51.7969px; z-index: 1001;">
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                                <span class="ui-draggable-handle"><p><span style="color:hsl(0,0%,0%);font-family:Arial, Helvetica, sans-serif;font-size:14px;" class="ui-draggable-handle"><strong>{{ formatMini($reservation_numbers) }}</strong></span></p></span>
                                            </div>
                                                <div class="elements text ui-draggable" style="padding: 10px; width: 120px; height: 90px; resize: both; overflow: hidden; position: absolute; top: 214px; left: 26px;">
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                                <span class="ui-draggable-handle"><h4 style="text-align:center;"><span style="color:hsl(0, 0%, 0%);font-size:26px;" class="ui-draggable-handle"><strong>{{ number_format($set->played_amount, 2, ',', '.') }}€</strong></span><br><span style="color:hsl(0, 0%, 0%);font-size:14px;" class="ui-draggable-handle"><strong>Donativo:</strong></span><br><span style="color:hsl(0, 0%, 0%);font-size:18px;" class="ui-draggable-handle"><strong>{{ number_format($set->donation_amount, 2, ',', '.') }}€</strong></span></h4></span>
                                            </div>
                                                <div class="elements element-critical participation text ui-draggable" style="padding: 10px; width: 90px; height: 40px; resize: both; overflow: hidden; position: absolute; top: 300px; left: 94px;">
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                                <span class="ui-draggable-handle"><p><span style="color:hsl(0,0%,0%);font-size:10px;" class="ui-draggable-handle"><strong>Nº 1/0001</strong></span></p></span>
                                            </div>
                                            <div class="elements images ui-draggable" style="resize: both; overflow: hidden; position: relative; left: 44.9659px; top: 68.9773px; height: 78px; width: 76px;"><span class="ui-draggable-handle"><img style="width: 100%; height: 100%" src="{{url('default.jpg')}}" alt=""></span><button class="edit-btn" title="Cambiar imagen"><i class="ri-image-line"></i></button></div><div class="elements images ui-draggable" style="resize: both; overflow: hidden; position: relative; left: 184.884px; top: 15.9091px; height: 84px; width: 137px;"><span class="ui-draggable-handle"><img style="width: 100%; height: 100%" src="{{url('default.jpg')}}" alt=""></span><button class="edit-btn" title="Cambiar imagen"><i class="ri-image-line"></i></button></div><div class="elements text ui-draggable" style="padding: 10px; width: 298px; height: 78px; resize: both; overflow: hidden; position: absolute; top: 258.815px; left: 162.81px;">
                                                <span class="ui-draggable-handle"><p><span style="color:hsl(0,0%,0%);font-size:10px;" class="ui-draggable-handle"><strong>Caduca a los 3 meses, Premios sujetos a la ley.</strong></span><br><span style="color:hsl(0,0%,0%);font-size:10px;" class="ui-draggable-handle"><strong>Nota: Todo talón roto o enmendado será nulo</strong></span></p></span>
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                            </div><div class="elements text ui-draggable" style="padding: 10px; width: 558px; height: 42px; resize: both; overflow: hidden; position: absolute; top: 304px; left: 172px;">
                                                <span class="ui-draggable-handle"><p><span style="color:hsl(0,0%,0%); font-size:6px"><strong>Premios sup. a 2500€ por décimo, tendrán una retención del 20% por encima del importe anterior, que será prorrateada en estas particip. en la proporción correspondiente a su valor nominal.</strong></span></p></span>
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                            </div><div class="elements text ui-draggable" style="padding: 10px; width: 200px; height: 120px; resize: both; overflow: hidden; position: absolute; top: 220px; left: 332px;">
                                            <span class="ui-draggable-handle"><p style="text-align:center;"><span style="color:hsl(0,0%,0%);font-size:26px;" class="ui-draggable-handle"><strong>{{ number_format($set->played_amount, 2, ',', '.') }}€</strong></span><br><span style="color:hsl(0,0%,0%);font-size:14px;" class="ui-draggable-handle"><strong>Donativo:</strong></span><br><span style="color:hsl(0,0%,0%);font-size:18px;" class="ui-draggable-handle"><strong>{{ number_format($set->donation_amount, 2, ',', '.') }}€</strong></span></p></span>
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                            </div><div class="elements element-critical participation text ui-draggable" style="padding: 10px; width: 80px; height: 42px; resize: both; overflow: hidden; position: absolute; top: 218px; left: 662px;">
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                                <span class="ui-draggable-handle"><p><span style="color:hsl(0,0%,0%);font-size:10px;" class="ui-draggable-handle"><strong>Nº 1/0001</strong></span></p></span>
                                            </div><div class="elements text ui-draggable" style="padding: 10px; width: 92px; height: 36px; resize: both; overflow: hidden; position: absolute; top: 247.797px; left: 490.781px;">
                                                <span class="ui-draggable-handle"><p><span style="color:hsl(0,0%,0%);font-size:12px;" class="ui-draggable-handle"><strong>DEPOSITARIO</strong></span></p></span>
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                            </div><div class="elements element-critical reference text ui-draggable" style="padding: 10px; width: 227px; height: 40px; resize: both; overflow: hidden; position: absolute; top: 278.688px; left: 459.703px;">
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                                <span class="ui-draggable-handle"><p><span style="color:hsl(0,0%,0%);font-size:12px;" class="ui-draggable-handle"><strong>Nº Ref: 00000000000000000000</strong></span></p></span>
                                            </div>
                                        <div class="elements element-critical qr ui-draggable" style="resize: both; overflow: hidden; position: absolute; top: 253.562px; left: 666.588px; width: 76px; height: 76px; min-width: 76px; min-height: 76px;"><span class="ui-draggable-handle"></span></div>
                                        
                                        </div>

                                        </div>
                                        @if($isDigitalSet ?? false)
                                        </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Tarea 6: información de dimensiones (participación) --}}
                                <div class="mt-2 p-2 small text-muted border-top" id="dimensions-info-step2"></div>

                                {{-- </div> --}}
                            </div>

                            @if(!($isDigitalSet ?? false))
                            <div class="form-card fade bs d-none" id="step-3" style="min-height: 658px;">
                                <h4 class="mb-0 mt-1">
                                    Configuración de Formato
                                </h4>
                                <small><i>Configura el formato de la página y las participaciones</i></small>

                                <br>

                                {{-- <div style="overflow: auto; height: 658px; width: 100%;"> --}}

                                <div class="format-box-btn">

                                    <br>

                                    <div class="btn-group format-btn-group">
                                        <button type="button" class="btn btn-sm btn-secondary design-zoom-out" title="Alejar" data-step="3"><i class="ri-zoom-out-line"></i></button>
                                        <button type="button" class="btn btn-sm btn-secondary design-zoom-in" title="Acercar" data-step="3"><i class="ri-zoom-in-line"></i></button>
                                        <span class="align-self-center px-1 design-zoom-label" style="font-size: 12px;">100%</span>
                                        <button title="Agregar texto" class="btn btn-sm btn-dark add-text" data-id="3" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-edit-line"></i></button>
                                        <button title="Agregar imagen" class="btn btn-sm btn-dark add-image" data-id="3" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-image-line"></i></button>
                                        <button title="Indicador QR (donde se colocará el QR para lectura del taco)" class="btn btn-sm btn-dark add-qr" data-id="3" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-qr-code-line"></i></button>
                                        {{-- <label class="btn btn-sm btn-dark color" style="position: relative;" data-id="3" type="button">
                                            Fondo<input type="color" style="left: 0; opacity: 0; position: absolute; top: 0;">
                                        </label> --}}
                                        <button title="Fondo de la participación" class="btn btn-sm btn-dark" id="open-bg-modal" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-palette-line"></i></button>
                                        <button title="Agregar barra superior" class="btn btn-sm btn-dark add-top" data-id="3" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-layout-top-line"></i></button>
                                        <button title="Agregar barra inferior" class="btn btn-sm btn-dark add-bottom" data-id="3" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-layout-bottom-line"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger reset-mandatory-canvas" data-id="3" title="Elimina los campos de ejemplo y deja solo los obligatorios. Se puede deshacer." style="padding-left: 12px; padding-right: 12px;"><i class="ri-eraser-line"></i></button>
                                        <button title="Mostrar/ocultar guías" class="btn btn-sm btn-dark toggle-guide" data-id="2" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-ruler-line"></i></button>
                                        <label title="Color de guías" class="btn btn-sm btn-dark color-guide" style="position: relative; padding-left: 12px; padding-right: 12px;" data-id="2" type="button">
                                            <i class="ri-palette-line"></i><input type="color" style="left: 0; opacity: 0; position: absolute; top: 0;">
                                        </label>
                                        <button class="btn btn-sm btn-warning undo-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Deshacer"><i class="ri-arrow-go-back-line"></i></button>
                                        <button class="btn btn-sm btn-success redo-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Rehacer"><i class="ri-arrow-go-forward-line"></i></button>
                                        <button class="btn btn-sm btn-danger delete-element-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Eliminar elemento"><i class="ri-delete-bin-6-line"></i></button>
                                        <button class="btn btn-sm btn-dark up-layer" disabled style="padding-left: 12px; padding-right: 12px;" title="Subir capa"><i class="ri-arrow-up-line"></i></button>
                                        <button class="btn btn-sm btn-dark down-layer" disabled style="padding-left: 12px; padding-right: 12px;" title="Bajar capa"><i class="ri-arrow-down-line"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn bold-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Negrita"><i class="ri-bold"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn italic-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Cursiva"><i class="ri-italic"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn underline-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Subrayado"><i class="ri-underline"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn strike-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Tachado"><i class="ri-strikethrough"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn align-left-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Alinear izquierda"><i class="ri-align-left"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn align-center-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Centrar"><i class="ri-align-center"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn align-right-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Alinear derecha"><i class="ri-align-right"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn font-size-up-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Aumentar tamaño"><i class="ri-font-size"></i>+</button>
                                        <button class="btn btn-sm btn-dark text-style-btn font-size-down-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Disminuir tamaño"><i class="ri-font-size"></i>-</button>
                                        <button class="btn btn-sm btn-dark text-style-btn text-vertical-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Texto vertical" aria-pressed="false"><i class="ri-text" style="display:inline-block;transform:rotate(-90deg)"></i></button>
                                    </div>
                                </div>
                                <div class="design-zoom-scroll">
                                    <div class="design-zoom-container" id="design-zoom-wrapper-3" style="transform-origin: top center;">
                                        <div class="format-box" style="border:1px solid #c8c8c8; width: 200mm; height: 92mm; margin: auto; position: relative;">

                                        {{-- margen izquierdo --}}
                                        <div class="margen-izquierdo guide3" style="opacity: 1; z-index: 1; position: absolute; height: 100%; border-left: 1px solid purple; left: 0mm;"></div>
                                        {{-- margen arriba --}}
                                        <div class="margen-arriba guide3" style="opacity: 1; z-index: 1; position: absolute; width: 100%; border-top: 1px solid purple; top: 0mm;"></div>
                                        {{-- margen derecho --}}
                                        <div class="margen-derecho guide3" style="opacity: 1; z-index: 1; position: absolute; height: 100%; border-right: 1px solid purple; right: 0mm;"></div>
                                        {{-- margen abajo --}}
                                        <div class="margen-abajo guide3" style="opacity: 1; z-index: 1; position: absolute; width: 100%; border-bottom: 1px solid purple; bottom: 0mm;"></div>

                                        <div id="containment-wrapper3" style="width: 100%; height: calc(100% - 0mm); background-size: cover; background-position: center;"> 
                                            {{-- Cuadro blanco QR portada: se reemplazará por el QR del taco en el PDF; más arriba para no chocar con la barra inferior --}}
                                            <div class="elements element-critical qr cover-taco-qr" style="resize: both; overflow: hidden; position: absolute; bottom: 50px; right: 15px; width: 76px; height: 76px; min-width: 76px; min-height: 76px; background: #fff; border: 2px solid #ccc; z-index: 5;"><span></span></div>
                                            <div class="elements text ui-draggable" style="padding: 10px; width: 351px; height: 93px; resize: both; overflow: hidden; position: absolute; top: 59.8295px; left: 378.71px;">
                                                <span class="ui-draggable-handle"><h4><span style="color:hsl(0,0%,0%);" class="ui-draggable-handle"><u>&nbsp; Nombre: &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;</u></span></h4></span>
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                            </div><div class="elements element-critical context cover-taco-label ui-draggable" style="width: calc(100% - 60px); border-radius: 10px; height: 10%; resize: both; overflow: hidden; position: absolute; inset: 294.67px 0px 20px 2.83209px; margin: auto; background-color: rgb(223, 223, 223); border: 2px solid #333;"><span style="padding: 8px; display: block; text-align: center; font-size: 12px; font-weight: 700;" class="ui-draggable-handle">@{{taco_label}}</span></div><div class="elements images ui-draggable" style="resize: both; overflow: hidden; position: absolute; top: 49.7045px; left: 25.7074px; width: 90px; height: 36px;"><span class="ui-draggable-handle"><img style="width: 100%; height: 100%" src="{{url('logo.svg')}}" alt=""></span><button class="edit-btn" title="Cambiar imagen"><i class="ri-image-line"></i></button></div><div class="elements text ui-draggable" style="padding: 10px; width: 280px; height: 87px; resize: both; overflow: hidden; position: absolute; top: 29.4034px; left: 106.426px;">
                                                <span class="ui-draggable-handle"><h1><span style="font-size:38px;" class="ui-draggable-handle"><strong>PARTI</strong></span><span style="color:hsl(36,100%,48%);font-size:38px;" class="ui-draggable-handle"><strong>LOT</strong></span></h1></span>
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                            </div><div class="elements text ui-draggable" style="padding: 10px; width: 257px; height: 165px; resize: both; overflow: hidden; position: absolute; top: 107.724px; left: 24.7074px;">
                                                <span class="ui-draggable-handle"><h3><strong>Descargate la APP</strong><br><strong>PARTILOT</strong><br><strong>Y Comprueba&nbsp;</strong><br><strong>tu Participación</strong></h3></span>
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                            </div>
                                            
                                        </div>

                                        </div>
                                    </div>
                                </div>

                                {{-- Tarea 6: información de dimensiones (portada) --}}
                                <div class="mt-2 p-2 small text-muted border-top" id="dimensions-info-step3"></div>

                                {{-- </div> --}}
                            </div>

                            <div class="form-card fade bs d-none" id="step-4" style="min-height: 658px;">
                                <div class="design-skip-back-banner alert py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2" id="skip-back-banner">
                                    <span class="mb-0 skip-back-msg"><strong>¿No necesitas diseñar la trasera?</strong> Puedes omitir este paso. No se habilitará la descarga de PDF de traseras.</span>
                                    <span class="mb-0 restore-back-msg d-none"><strong>Trasera omitida.</strong> Puedes volver a activarla para diseñar y generar PDF de traseras.</span>
                                    <button type="button" class="btn btn-dark btn-sm rounded-pill px-3" id="btn-skip-back-design"><i class="ri-skip-forward-line me-1"></i> Omitir trasera</button>
                                    <button type="button" class="btn btn-success btn-sm rounded-pill px-3 d-none" id="btn-restore-back-design"><i class="ri-arrow-go-back-line me-1"></i> Usar trasera</button>
                                </div>
                                <h4 class="mb-0 mt-1">
                                    Configuración de Formato
                                </h4>
                                <small><i>Configura el formato de la página y las participaciones</i></small>

                                <br>

                                {{-- <div style="overflow: auto; height: 658px; width: 100%;"> --}}

                                <div class="format-box-btn">

                                    <br>

                                    <div class="btn-group format-btn-group">
                                        <button type="button" class="btn btn-sm btn-secondary design-zoom-out" title="Alejar" data-step="4"><i class="ri-zoom-out-line"></i></button>
                                        <button type="button" class="btn btn-sm btn-secondary design-zoom-in" title="Acercar" data-step="4"><i class="ri-zoom-in-line"></i></button>
                                        <span class="align-self-center px-1 design-zoom-label" style="font-size: 12px;">100%</span>
                                        <button title="Agregar texto" class="btn btn-sm btn-dark add-text" data-id="4" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-edit-line"></i></button>
                                        <button title="Agregar imagen" class="btn btn-sm btn-dark add-image" data-id="4" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-image-line"></i></button>
                                        {{-- <button class="btn btn-sm btn-dark add-qr" data-id="4" type="button">QR</button> --}}
                                        {{-- <label class="btn btn-sm btn-dark color" style="position: relative;" data-id="4" type="button">
                                            Fondo<input type="color" style="left: 0; opacity: 0; position: absolute; top: 0;">
                                        </label> --}}
                                        <button title="Fondo de la participación" class="btn btn-sm btn-dark" id="open-bg-modal" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-palette-line"></i></button>
                                        <button title="Agregar barra superior" class="btn btn-sm btn-dark add-top" data-id="4" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-layout-top-line"></i></button>
                                        <button title="Agregar barra inferior" class="btn btn-sm btn-dark add-bottom" data-id="4" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-layout-bottom-line"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger reset-mandatory-canvas" data-id="4" title="Elimina los campos de ejemplo y deja solo los obligatorios. Se puede deshacer." style="padding-left: 12px; padding-right: 12px;"><i class="ri-eraser-line"></i></button>
                                        <button title="Mostrar/ocultar guías" class="btn btn-sm btn-dark toggle-guide" data-id="2" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-ruler-line"></i></button>
                                        <label title="Color de guías" class="btn btn-sm btn-dark color-guide" style="position: relative; padding-left: 12px; padding-right: 12px;" data-id="2" type="button">
                                            <i class="ri-palette-line"></i><input type="color" style="left: 0; opacity: 0; position: absolute; top: 0;">
                                        </label>
                                        <button class="btn btn-sm btn-warning undo-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Deshacer"><i class="ri-arrow-go-back-line"></i></button>
                                        <button class="btn btn-sm btn-success redo-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Rehacer"><i class="ri-arrow-go-forward-line"></i></button>
                                        <button class="btn btn-sm btn-danger delete-element-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Eliminar elemento"><i class="ri-delete-bin-6-line"></i></button>
                                        <button class="btn btn-sm btn-dark up-layer" disabled style="padding-left: 12px; padding-right: 12px;" title="Subir capa"><i class="ri-arrow-up-line"></i></button>
                                        <button class="btn btn-sm btn-dark down-layer" disabled style="padding-left: 12px; padding-right: 12px;" title="Bajar capa"><i class="ri-arrow-down-line"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn bold-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Negrita"><i class="ri-bold"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn italic-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Cursiva"><i class="ri-italic"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn underline-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Subrayado"><i class="ri-underline"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn strike-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Tachado"><i class="ri-strikethrough"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn align-left-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Alinear izquierda"><i class="ri-align-left"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn align-center-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Centrar"><i class="ri-align-center"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn align-right-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Alinear derecha"><i class="ri-align-right"></i></button>
                                        <button class="btn btn-sm btn-dark text-style-btn font-size-up-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Aumentar tamaño"><i class="ri-font-size"></i>+</button>
                                        <button class="btn btn-sm btn-dark text-style-btn font-size-down-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Disminuir tamaño"><i class="ri-font-size"></i>-</button>
                                        <button class="btn btn-sm btn-dark text-style-btn text-vertical-btn" disabled style="padding-left: 12px; padding-right: 12px;" title="Texto vertical" aria-pressed="false"><i class="ri-text" style="display:inline-block;transform:rotate(-90deg)"></i></button>
                                    </div>
                                </div>
                                <div class="design-zoom-scroll">
                                    <div class="design-zoom-container" id="design-zoom-wrapper-4" style="transform-origin: top center;">
                                        <div class="format-box" style="border:1px solid #c8c8c8; width: 200mm; height: 92mm; margin: auto; position: relative;">

                                        {{-- margen izquierdo --}}
                                        <div class="margen-izquierdo guide4" style="opacity: 1; z-index: 1; position: absolute; height: 100%; border-left: 1px solid purple; left: 0mm;"></div>
                                        {{-- margen arriba --}}
                                        <div class="margen-arriba guide4" style="opacity: 1; z-index: 1; position: absolute; width: 100%; border-top: 1px solid purple; top: 0mm;"></div>
                                        {{-- margen derecho --}}
                                        <div class="margen-derecho guide4" style="opacity: 1; z-index: 1; position: absolute; height: 100%; border-right: 1px solid purple; right: 0mm;"></div>
                                        {{-- margen abajo --}}
                                        <div class="margen-abajo guide4" style="opacity: 1; z-index: 1; position: absolute; width: 100%; border-bottom: 1px solid purple; bottom: 0mm;"></div>
                                        {{-- caja matriz --}}
                                        {{-- <div class="caja-matriz-2 guide4" style="opacity: 1; z-index:1;position: absolute; width: 40mm; border-left: 1px solid purple; height: 100%; right: 0mm;"></div> --}}

                                        {{-- Tarea 5: imagen de fondo solo hasta el límite matrix-box (identation+matrix desde la derecha) --}}
                                        <div id="containment-wrapper4" style="width: 100%; height: calc(100% - 0mm); position: relative;">
                                            <div id="design-back-bg" style="position: absolute; left: 0; top: 0; right: 40mm; bottom: 0; z-index: 0; pointer-events: none; background-color: #dfdfdf; background-size: cover; background-position: center;"></div>
                                            <div class="elements images ui-draggable" style="resize: both; overflow: hidden; position: absolute; top: 38.7969px; left: 44.8125px; width: 111px; height: 74px;"><span class="ui-draggable-handle"><img style="width: 100%; height: 100%" src="{{url('logo.svg')}}" alt=""></span><button class="edit-btn" title="Cambiar imagen"><i class="ri-image-line"></i></button></div><div class="elements text ui-draggable" style="padding: 10px; width: 380px; height: 140px; resize: both; overflow: hidden; position: absolute; top: 17.5938px; left: 173px;">
                                                <span class="ui-draggable-handle"><h3><strong>Descargate la APP</strong><br><strong>PARTILOT</strong><br><strong>Y Comprueba tu Participación</strong></h3></span>
                                                <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
                                            </div>
                                        </div>

                                        </div>
                                    </div>
                                </div>

                                {{-- Tarea 6: información de dimensiones (trasera) --}}
                                <div class="mt-2 p-2 small text-muted border-top" id="dimensions-info-step4"></div>

                                {{-- </div> --}}
                            </div>

                            <div class="form-card fade bs d-none" id="step-5" style="min-height: 658px;">
                                <h4 class="mb-0 mt-1">
                                    Configurar salida
                                </h4>
                                <small><i>Configura el formato de salida de las participaciones</i></small>

                                <br>
                                <br>

                                <div>

                                    <h4 class="mb-0 mt-1">
                                        Formato de la página
                                    </h4>

                                    <div class="row">
                                        <div class="col-12">
                                            <div class="form-group mb-1">
                                                <div class="form-check form-switch mt-3">
                                                    <input style="float: left;" class="form-check-input bg-dark" type="checkbox" role="switch" id="guides" checked>
                                                    <label style="float: left; margin-left: 50px;" class="form-check-label" for="guides"><b>Dibujar las guías de corte</b></label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-6">
                                            <div class="row mb-3">
                                                        
                                                <label class="col-form-label label-control col-6 text-start">
                                                    Color de las guías
                                                </label>

                                                <div class="col-sm-2">
                                                    <input class="form-control" type="color" id="guide_color" style="border-radius: 30px">
                                                </div>

                                            </div>
                                        </div>

                                        <div class="col-6">
                                            <div class="row mb-3">
                                                        
                                                <label class="col-form-label label-control col-6 text-start">
                                                    Grosor de las guías (mm):
                                                </label>

                                                <div class="col-sm-2">
                                                    <input class="form-control" type="number" id="guide_weight" value="0.3" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                </div>

                                            </div>
                                        </div>
                                    </div>

                                    @if(!$set->digital_participations || ($set->physical_participations ?? 0) > 0)
                                    <h4 class="mb-0 mt-1">
                                        Participaciones por talonario
                                    </h4>
                                    <small><i>Elige la cantidad de participaciones por talonario</i></small>

                                    <div class="row mb-3">

                                        <label class="col-form-label label-control col-3 text-start">
                                            Cantidad de participaciónes:
                                        </label>

                                        <div class="col-sm-1">
                                            <input class="form-control" type="number" value="50" id="participation_number" style="border-radius: 30px">
                                        </div>

                                    </div>

                                    <br>
                                    @else
                                    <p class="text-muted mb-3"><i>Set digital: una sola serie (no hay talonarios).</i></p>
                                    <input type="hidden" id="participation_number" value="{{ $set->total_participations ?? 1 }}">
                                    @endif

                                    <h4 class="mb-0 mt-1">
                                        Participaciones a generar
                                    </h4>

                                    <div class="form-group mb-3">
                                        <div class="form-check form-switch mt-3">
                                            <input style="float: left;" class="form-check-input bg-dark" type="radio" name="generate" value="1" role="switch" id="generate1" checked>
                                            <label style="float: left; margin-left: 50px;" class="form-check-label" for="generate1"><b>Generar todas las participaciones ({{ $set->total_participations ?? 0 }})</b></label>
                                        </div>

                                        <div class="form-check form-switch mt-3">
                                            <input style="float: left;" class="form-check-input bg-dark" type="radio" name="generate" value="2" role="switch" id="generate">
                                            <label style="float: left; margin-left: 50px;" class="form-check-label" for="generate"><b>Generar un rango de participaciones</b></label>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                                        
                                        <label class="col-form-label label-control col-3 text-start">
                                            Generar de la participación:
                                        </label>

                                        <div class="col-sm-1">
                                            <input class="form-control" type="number" value="1" id="participation_from" style="border-radius: 30px">
                                        </div>

                                        <label class="col-form-label label-control col-3 text-start">
                                            Hasta la participación:
                                        </label>
                                        <div class="col-sm-1">
                                            <input class="form-control" type="number" value="{{ $set->total_participations ?? '' }}" id="participation_to" style="border-radius: 30px">
                                        </div>

                                        <label class="col-form-label label-control col-4 text-start">
                                            (ambas incluidas)
                                        </label>

                                    </div>

                                    <br>

                                    <h4 class="mb-0 mt-1">
                                        Número de documentos
                                    </h4>
                                    <p class="text-muted small mb-2">Valores por defecto al imprimir. Al generar el PDF podrá elegir en ese momento si quiere un único documento o un ZIP (sin necesidad de reaprobar el diseño).</p>

                                    <div class="form-group mb-3">
                                        <div class="form-check form-switch mt-3">
                                            <input style="float: left;" class="form-check-input bg-dark" type="radio" name="documents" value="1" role="switch" id="documents1" checked>
                                            <label style="float: left; margin-left: 50px;" class="form-check-label" for="documents1"><b>Generar un único documento</b></label>
                                        </div>

                                        <div class="form-check form-switch mt-3">
                                            <input style="float: left;" class="form-check-input bg-dark" type="radio" name="documents" value="2" role="switch" id="documents">
                                            <label style="float: left; margin-left: 50px;" class="form-check-label" for="documents"><b>Separar las participaciones en múltiples documentos</b></label>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                                        
                                        <label class="col-form-label label-control col-3 text-start">
                                            Número de páginas por documento:
                                        </label>

                                        <div class="col-sm-1">
                                            <input class="form-control" type="number" value="150" id="participation_page" min="1" style="border-radius: 30px">
                                        </div>
                                        <label class="col-form-label label-control col-8 text-start" id="pages-per-document-hint">
                                            (6 participaciones por página, 1 documento)
                                        </label>

                                    </div>
                                </div>
                            </div>
                            @endif

                            <div class="row">

                                <div class="col-6 text-start">
                                    <a href="javascript:;" class="btn btn-md btn-light mt-2 prev-step design-wizard-nav-btn design-wizard-nav-btn--dark">
                                        <i class="ri-arrow-left-circle-line" aria-hidden="true"></i>
                                        <span>Atrás</span>
                                    </a>
                                </div>
                                <div class="col-6 text-end">
                                    {{-- Previsualizar (pasos 2–4) + Siguiente/Guardar --}}
                                    <div class="d-inline-flex flex-wrap align-items-end justify-content-end gap-2">
                                        <button type="button" id="design-preview-pdf-btn" class="btn btn-md btn-light mt-2 d-none design-wizard-nav-btn design-wizard-nav-btn--dark" title="Vista previa del PDF del paso actual">
                                            <i class="ri-file-pdf-line" aria-hidden="true"></i>
                                            <span>Previsualizar PDF</span>
                                        </button>
                                        <div class="d-inline-flex flex-column align-items-end gap-1" id="design-step-actions" style="min-width: 200px;">
                                        <button id="step" type="button" class="btn btn-md btn-light mt-2 next-step design-wizard-nav-btn design-wizard-nav-btn--primary">
                                            <span>Siguiente</span>
                                            <i class="ri-arrow-right-circle-line" aria-hidden="true"></i>
                                        </button>
                                        <button id="save-step" type="button" class="btn btn-md btn-light mt-2 d-none design-wizard-nav-btn design-wizard-nav-btn--primary">
                                            <span>Guardar</span>
                                            <i class="ri-save-line" aria-hidden="true"></i>
                                        </button>
                                        <button id="save-continue-step" type="button" class="btn btn-md btn-light mt-2 d-none design-wizard-nav-btn design-wizard-nav-btn--continue">
                                            <span>Guardar y continuar</span>
                                            <i class="ri-arrow-right-circle-line" aria-hidden="true"></i>
                                        </button>
                                        </div>
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

<div class="modal fade design-editor-modal" id="ckeditor-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Editar Texto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        
        <p class="text-muted small mb-2 mb-md-3" style="margin-bottom: 0.75rem;">
          Mientras editas, el texto se muestra en negro para que se lea bien. El color real (p. ej. blanco) se verá en el diseño al aceptar.
        </p>
        <div class="editor-container__editor"><div id="editor" style="height: 200px;"></div></div>
        {{-- <div class="editor-container editor-container_document-editor" id="editor-container">
            <div class="editor-container__editor-wrapper">
            </div>
        </div> --}}

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-danger deleteElements" data-bs-dismiss="modal">Eliminar elemento</button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary accept-text">Aceptar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade design-editor-modal" id="imagen-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Imagen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        
            <div class="form-group mt-2 mb-3">
                <label class="label-control">Subir imagen</label>

                <div class="input-group input-group-merge group-form">
                    <input class="" id="imageInput" type="file">
                </div>
            </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-danger deleteElements" data-bs-dismiss="modal">Eliminar elemento</button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary accept-image">Aceptar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="qr-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Generar QR</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        
            <div class="form-group mt-2 mb-3">
                <label class="label-control">Texto para el QR</label>

                <div class="input-group input-group-merge group-form">
                    <input class="form-control" type="text" id="qr-text" placeholder="Texto para el QR" style="border-radius: 30px">
                </div>
            </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-danger deleteElements" data-bs-dismiss="modal">Eliminar elemento</button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary accept-qr">Aceptar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade design-editor-modal" id="design-name-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Nombre del diseño</h5>
      </div>
      <div class="modal-body">
        <p class="small text-muted">Asigne un nombre para identificar este diseño en el listado de la entidad.</p>
        <input type="text" class="form-control" id="design-name-input" maxlength="120" placeholder="Nombre del diseño">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="design-name-cancel">Cancelar</button>
        <button type="button" class="btn btn-primary" id="design-name-confirm">Guardar diseño</button>
      </div>
    </div>
  </div>
</div>

<!-- Overlay de carga (subir imagen, guardar, etc.) -->
<div id="design-loading-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center; flex-direction: column;">
  <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
    <span class="visually-hidden">Cargando...</span>
  </div>
  <p class="text-white mt-2 mb-0" id="design-loading-text">Procesando...</p>
</div>

<div class="modal fade" id="position-modal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Cambiar posición</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        
            <button class="btn btn-sm btn-info up-z">Subir</button>
            <button class="btn btn-sm btn-info dw-z">Bajar</button>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal opciones de barra (portada/trasera) -->
<div class="modal fade" id="bar-options-modal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Opciones de la barra</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Color de fondo</label>
          <input type="color" id="bar-modal-bg" class="form-control form-control-color w-100" value="#dfdfdf" title="Color de fondo">
        </div>
        <div class="mb-3">
          <label class="form-label">Borde (px) — 0 = sin borde</label>
          <input type="number" id="bar-modal-border-width" class="form-control" min="0" max="20" value="2">
        </div>
        <div class="mb-3">
          <label class="form-label">Color del borde</label>
          <input type="color" id="bar-modal-border-color" class="form-control form-control-color w-100" value="#333333" title="Color del borde">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-danger" id="bar-modal-delete">Borrar barra</button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- === MODAL FONDO DE TICKET === -->
<div class="modal fade design-editor-modal" id="background-modal" tabindex="-1" aria-labelledby="backgroundModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="backgroundModalLabel">Seleccionar fondo de la participación</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="background-color" class="form-label">Color de fondo</label>
          <input type="color" class="form-control form-control-color" id="background-color" value="#dfdfdf" title="Elige un color">
        </div>
        <div class="mb-3">
          <label for="background-image" class="form-label">Imagen de fondo</label>
          <input class="form-control" type="file" id="background-image" accept="image/*">
        </div>
        <div class="mb-3">
          <button class="btn btn-secondary" id="remove-bg-image">Quitar imagen de fondo</button>
        </div>
        <div id="bg-preview" class="design-bg-preview"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary" id="apply-bg">Aplicar fondo</button>
      </div>
    </div>
  </div>
</div>
{{-- // ... existing code ...
// === BOTÓN PARA ABRIR EL MODAL DE FONDO ===
// Puedes ponerlo junto al botón de color de fondo actual: --}}

@endsection

@section('scripts')
<script src="{{ asset('assets/libs/html2canvas/html2canvas.min.js') }}"></script>
<script>
// ... existing code ...
// === SCRIPTS PARA EL MODAL DE FONDO ===
function showDesignLoading(msg) {
  $('#design-loading-text').text(msg || 'Procesando...');
  $('#design-loading-overlay').css('display', 'flex').show();
}
function hideDesignLoading() {
  $('#design-loading-overlay').hide();
}

// URL con espacios/caracteres especiales debe ir entre comillas en CSS url()
// Debe ser global: next/prev-step viven en otro bloque <script>
function bgImageCssUrl(url) {
  if (!url) return 'none';
  return 'url("' + String(url).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '")';
}

// Zoom del diseño (global: setupDraggable vive en otro bloque <script>)
var designZoom = 1;
var designZoomSteps = [0.5, 0.75, 1, 1.25, 1.5, 2, 2.5, 3, 3.5, 4];
function applyDesignZoom() {
  var s = designZoom;
  $('.design-zoom-container').css('transform', 'scale(' + s + ')');
  $('.design-zoom-label').text(Math.round(s * 100) + '%');
  try { localStorage.setItem('designZoom', s); } catch (e) {}
}
try { designZoom = parseFloat(localStorage.getItem('designZoom')) || 1; } catch (e) {}
designZoom = designZoomSteps.indexOf(designZoom) >= 0 ? designZoom : 1;
applyDesignZoom();
$(document).on('click', '.design-zoom-in', function() {
  var i = designZoomSteps.indexOf(designZoom);
  if (i < 0) { for (i = 0; i < designZoomSteps.length && designZoomSteps[i] < designZoom; i++); i = Math.min(i, designZoomSteps.length - 1); }
  if (i < designZoomSteps.length - 1) { designZoom = designZoomSteps[i + 1]; applyDesignZoom(); }
});
$(document).on('click', '.design-zoom-out', function() {
  var i = designZoomSteps.indexOf(designZoom);
  if (i < 0) { for (i = designZoomSteps.length - 1; i >= 0 && designZoomSteps[i] > designZoom; i--); i = Math.max(i, 0); }
  if (i > 0) { designZoom = designZoomSteps[i - 1]; applyDesignZoom(); }
});

$(document).ready(function() {
  $('#marginsCollapse').on('show.bs.collapse', function() {
    $('#btn-desplegar-margenes').text('Ocultar');
  }).on('hide.bs.collapse', function() {
    $('#btn-desplegar-margenes').text('Desplegar');
  });

  // Botón Guardar márgenes (paso 1): feedback visual (los valores se guardan al finalizar el diseño)
  $(document).on('click', '#btn-guardar-margenes', function(e) {
    e.preventDefault();
    if (typeof configMargins === 'function') configMargins();
    var $ti = $('#ticket-info');
    if ($ti.length) {
      $ti.addClass('alert-success').removeClass('alert-info');
      setTimeout(function() { $ti.removeClass('alert-success').addClass('alert-info'); }, 2500);
    }
    alert('Márgenes aplicados. Se guardarán con el diseño al finalizar.');
  });

  // Botón para abrir el modal
  $(document).on('click', '#open-bg-modal', function() {
    // Cargar valores actuales
    const color = localStorage.getItem('bg-step'+step) || '#dfdfdf';
    const img = localStorage.getItem('bgimg-step'+step) || null;
    $('#background-color').val(color);
    $('#background-image').val('');
    if(img) {
      $('#bg-preview').css('background-image', bgImageCssUrl(img));
    } else {
      $('#bg-preview').css('background-image', 'none');
    }
    $('#bg-preview').css('background-color', color);
    $('#background-modal').modal('show');
  });

  // Previsualizar color
  $('#background-color').on('input', function() {
    $('#bg-preview').css('background-color', $(this).val());
  });

  // Previsualizar imagen
  $('#background-image').on('change', function(e) {
    if(this.files && this.files[0]) {
      const reader = new FileReader();
      reader.onload = function(ev) {
        $('#bg-preview').css('background-image', bgImageCssUrl(ev.target.result));
      };
      reader.readAsDataURL(this.files[0]);
    }
  });

  // Quitar imagen de fondo
  $('#remove-bg-image').on('click', function() {
    $('#bg-preview').css('background-image', 'none');
    $('#background-image').val('');
    localStorage.removeItem('bgimg-step'+step);
  });

  // Aplicar fondo
  $('#apply-bg').on('click', function() {
    const color = $('#background-color').val();
    let img = '';
    if($('#background-image')[0].files && $('#background-image')[0].files[0]) {
      // Subir imagen al servidor
      const file = $('#background-image')[0].files[0];
      const formData = new FormData();
      formData.append('image', file);
      showDesignLoading('Subiendo imagen...');
      fetch(@json($design_upload_url ?? route('design.uploadImage')), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if(data.url) {
          img = data.url;
          localStorage.setItem('bgimg-step'+step, img);
          setBgToContainment(color, img);
          $('#background-modal').modal('hide');
        }
      })
      .finally(() => hideDesignLoading());
    } else {
      img = localStorage.getItem('bgimg-step'+step) || '';
      setBgToContainment(color, img);
      $('#background-modal').modal('hide');
    }
    localStorage.setItem('bg-step'+step, color);
  });

  function setBgToContainment(color, img) {
    var $cont = (typeof getBackgroundTargetEl === 'function')
      ? getBackgroundTargetEl(step)
      : ((step === 4) ? $('#design-back-bg') : $('#containment-wrapper'+step));
    if (!$cont || !$cont.length) $cont = $('#containment-wrapper'+step);
    $cont.css('background-color', color || '#ffffff');
    if (img) {
      $cont.css('background-image', bgImageCssUrl(img));
      $cont.css('background-size', 'cover');
      $cont.css('background-position', 'center');
      $cont.css('background-repeat', 'no-repeat');
    } else {
      $cont.css('background-image', 'none');
    }
    if (typeof syncMarginBgLayers === 'function') syncMarginBgLayers();
  }
});
// ... existing code ...
</script>

<script>

function initDatatable() 
  {
    if (!$("#example2").length || typeof $.fn.DataTable === 'undefined') return;
    $("#example2").DataTable({

      "select":{style:"single"},

      "ordering": false,
      "sorting": false,

      "scrollX": true, "scrollCollapse": true,
        orderCellsTop: true,
        fixedHeader: true,
        initComplete: function () {
            var api = this.api();
 
            // For each column
            api
                .columns()
                .eq(0)
                .each(function (colIdx) {
                    // Set the header cell to contain the input element
                    var cell = $('.filters th').eq(
                        $(api.column(colIdx).header()).index()
                    );
                    var title = $(cell).text();
                    if ($(cell).hasClass('no-filter')) {
                      $(cell).addClass('sorting_disabled').html(title);
                    }else{
                      $(cell).addClass('sorting_disabled').html('<input type="text" class="inline-fields" placeholder="' + title + '" />');
                    }
 
                    // On every keypress in this input
                    $(
                        'input',
                        $('.filters th').eq($(api.column(colIdx).header()).index())
                    )
                        .off('keyup change')
                        .on('keyup change', function (e) {
                            e.stopPropagation();
 
                            // Get the search value
                            $(this).attr('title', $(this).val());
                            var regexr = '({search})'; //$(this).parents('th').find('select').val();
 
                            var cursorPosition = this.selectionStart;
                            // Search the column for that value

                            // console.log(val.replace(/<select[\s\S]*?<\/select>/,''));
                            let wSelect = false;
                            $.each(api.column(colIdx).data(), function(index, val) {
                               if (val.indexOf('<select') == -1) {
                                wSelect = false;
                               }else{
                                wSelect = true;
                               }
                            });

                            // $.each(api
                            //     .column(colIdx).data(), function(index, val) {
                            //     console.log(val)
                            // });

                            api
                                .column(colIdx)
                                .search(

                                  (wSelect ?
                                      (this.value != ''
                                        ? regexr.replace('{search}', '(((selected' + this.value + ')))')
                                        : '')
                                    :
                                      (this.value != ''
                                        ? regexr.replace('{search}', '(((' + this.value + ')))')
                                        : '')),

                                    this.value != '',
                                    this.value == ''
                                ).draw()
 
                            $(this)
                                .focus()[0]
                                .setSelectionRange(cursorPosition, cursorPosition);
                        });
                });
        }
    });
  }

  initDatatable();

  setTimeout(()=>{
    if ($('.filters .inline-fields:first').length) $('.filters .inline-fields:first').trigger('keyup');
  },100);


  function getCustomDimensions() {
    let page = $('#page').val();
    let cols = parseInt($('#cols').val());
    let rows = parseInt($('#rows').val());
    let orientation = $('#orientation').val();
    let w, h;
    if (page == 'a3') {
        w = 400 / cols;
        h = 276 / rows;
    } else if (page == 'a4') {
        w = 190 / cols;
        h = 277 / rows;
    }
    {{-- if (orientation == 'v') {
        let aux = w;
        w = h;
        h = aux;
    } --}}
    return {w, h};
}

function recalculateDesign() {
    let cols = $('#cols').val();
    let rows = $('#rows').val();
    let orientation = $('#orientation').val();
    let page = $('#page').val();

    if (orientation == 'h') {
        $('.preview-design > div').css('width','100%');
    }else{
        $('.preview-design > div').css('width','60%');
    }

    let h = 216 / rows;
    let html = "";
    let percent = 100 / cols;
    let margin = 1 / cols;
    for (var i = 0; i < cols*rows; i++) {
        html+=`<div style="height: ${h}px; width: ${percent-1}%; margin-left: ${margin}%"></div>`;
    }
    $('.preview-design > div').html(html);

    // Eliminado: cambio de tamaño de .format-box aquí
    // if($('#format').val() === 'custom') {
    //     const {w, h} = getCustomDimensions();
    //     $('.format-box').css({width: w+'mm', height: h+'mm'});
    // }
}

$('#cols,#rows').change(function (e) {
    e.preventDefault();
    recalculateDesign();
});

$('#page').change(function (e) {
    e.preventDefault();
    let clase = $(this).val();
    $('.preview-design > div').removeClass('a3 a4');
    $('.preview-design > div').addClass(clase);
    recalculateDesign();
});

$('#orientation').change(function(event) {
    recalculateDesign();
});

$('#format').change(function (e) {
    e.preventDefault();
    let html = "";
    restoreValues();
    if($(this).val() == 'a3-h-3x2') {
        $('.custom').prop('disabled', true);
        html = `<div class="a3">
                <div style="height: 72px;"></div>
                <div style="height: 72px;"></div>
                <div style="height: 72px;"></div>
                <div style="height: 72px;"></div>
                <div style="height: 72px;"></div>
                <div style="height: 72px;"></div>
            </div>`;
    } else if($(this).val() == 'a3-h-4x2') {
        $('.custom').prop('disabled', true);
        html = `<div class="a3">
                <div style="height: 54px;"></div>
                <div style="height: 54px;"></div>
                <div style="height: 54px;"></div>
                <div style="height: 54px;"></div>
                <div style="height: 54px;"></div>
                <div style="height: 54px;"></div>
                <div style="height: 54px;"></div>
                <div style="height: 54px;"></div>
            </div>`;
    } else if($(this).val() == 'a4-v-3x1') {
        $('.custom').prop('disabled', true);
        html = `<div class="a4">
                <div style="height: 72px;"></div>
                <div style="height: 72px;"></div>
                <div style="height: 72px;"></div>
            </div>`;
    } else if($(this).val() == 'a4-v-4x1') {
        $('.custom').prop('disabled', true);
        html = `<div class="a4">
                <div style="height: 54px;"></div>
                <div style="height: 54px;"></div>
                <div style="height: 54px;"></div>
                <div style="height: 54px;"></div>
            </div>`;
    } else if($(this).val() == 'custom') {
        $('.custom').prop('disabled', false);
        html = `<div class="a3">
                    <div style="height: 72px;"></div>
                    <div style="height: 72px;"></div>
                    <div style="height: 72px;"></div>
                    <div style="height: 72px;"></div>
                    <div style="height: 72px;"></div>
                    <div style="height: 72px;"></div>
                </div>`;
        // Actualizar el tamaño del format-box en tiempo real para personalizado
        const {w, h} = getCustomDimensions();
        console.log(w,h);
        {{-- $('.format-box').css({width: w+'mm', height: h+'mm'}); --}}
    }
    $('.preview-design').html(html);
});

  function restoreValues()
  {
    $('#page').prop('selectedIndex',0);
    $('#rows').val(3);
    $('#cols').val(2);
    $('#orientation').prop('selectedIndex',0);
  }

  var step = 1;
  var isDigitalSet = {{ ($isDigitalSet ?? false) ? 'true' : 'false' }};
  var selectedElement = null;
  var designDirty = false;
  var autosaveInFlight = false;
  var autosaveIntervalMs = 30000;
  var draftPersistTimer = null;
  var pendingPersistentDraft = null;
  var restoredDraftStep = null;
  var draftContext = window.__designContext || {};
  var draftStorageKey = 'design:draft:set:' + (draftContext.set_id || 'unknown');
  var transientKeys = ['step2','step3','step4','bg-step2','bg-step3','bg-step4','bgimg-step2','bgimg-step3','bgimg-step4','guide-step2','guide-step3','guide-step4'];
  var historyByStep = {};
  var historyIndexByStep = {};
  var designEditorFonts = 'Asgonlae/Asgonlae, sans-serif;Arial/Arial, Helvetica, sans-serif;Georgia/Georgia, serif;Times New Roman/Times New Roman, Times, serif;Verdana/Verdana, Geneva, sans-serif;Courier New/Courier New, Courier, monospace;Tahoma/Tahoma, Geneva, sans-serif;Trebuchet MS/Trebuchet MS, Helvetica, sans-serif';

  function syncCurrentStepToLocalStorage() {
    if (step >= 2 && step <= 4 && $('#containment-wrapper' + step).length) {
      localStorage.setItem('step' + step, $('#containment-wrapper' + step).html());
    }
  }

  function updateDesignActionButtons() {
    if (designDirty) {
      $('#step').addClass('d-none');
      $('#save-step').removeClass('d-none');
      $('#save-continue-step').removeClass('d-none');
    } else {
      $('#step').removeClass('d-none');
      $('#save-step').addClass('d-none');
      $('#save-continue-step').addClass('d-none');
    }
    if (typeof window.syncDesignPreviewPdfButton === 'function') {
      window.syncDesignPreviewPdfButton();
    }
  }

  function confirmLeaveWithUnsaved(callback) {
    if (!designDirty) {
      callback();
      return;
    }
    if (confirm('Tienes cambios sin guardar en el servidor. ¿Quieres continuar sin guardar?')) {
      callback();
    }
  }

  function stashStepHistory(stepNum) {
    if (stepNum < 2) {
      return;
    }
    historyByStep[stepNum] = historyStates.slice();
    historyIndexByStep[stepNum] = currentHistoryIndex;
  }

  function loadStepHistory(stepNum) {
    historyStates = (historyByStep[stepNum] || []).slice();
    currentHistoryIndex = typeof historyIndexByStep[stepNum] === 'number' ? historyIndexByStep[stepNum] : -1;
    updateUndoRedoButtons();
  }

  function ensureStepHistoryInitialized() {
    if (step < 2 || !$('#containment-wrapper' + step).length) {
      return;
    }
    if (!historyByStep[step] || historyByStep[step].length === 0) {
      saveHistoryState();
    }
  }

  function buildCKEditorConfig(contenidoHTML) {
    return {
      enterMode: CKEDITOR.ENTER_BR,
      shiftEnterMode: CKEDITOR.ENTER_P,
      allowedContent: true,
      font_names: designEditorFonts,
      contentsCss: [
        CKEDITOR.getUrl('contents.css'),
        '{{ asset('assets/css/design-editor-fonts.css') }}',
        '{{ asset('assets/css/design-ckeditor-edit.css') }}'
      ],
      toolbar: [
        { name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'Strike' ] },
        { name: 'paragraph', items: [ 'JustifyLeft', 'JustifyCenter', 'JustifyRight' ] },
        { name: 'colors', items: [ 'TextColor', 'BGColor' ] },
        { name: 'styles', items: [ 'Font', 'FontSize' ] }
      ],
      on: {
        instanceReady: function() {
          this.setData(contenidoHTML);
        }
      }
    };
  }

  function performDesignSave(options) {
    options = options || {};
    $('.elements').removeClass('selected');
    selectedElement = null;
    $('.up-layer, .down-layer, .text-style-btn').prop('disabled', true);

    if (window.__designLocked) {
      alert(window.__designLockMessage || 'Este set no permite edición de diseño por estado operativo.');
      return;
    }

    syncCurrentStepToLocalStorage();
    persistDraftLocally();

    var persistOpts = {
      reason: 'manual-save',
      showLoader: true,
      redirectOnSuccess: false,
      skipSuccessAlert: !!options.continueAfterSave,
      onSuccess: options.continueAfterSave ? function() {
        if (typeof options.onSuccess === 'function') {
          options.onSuccess();
        } else {
          $('.next-step').first().trigger('click');
        }
      } : null
    };

    if (step == 2) {
      showDesignLoading('Guardando vista previa...');
      generateParticipationSnapshot(function() {
        syncCurrentStepToLocalStorage();
        persistDesignToServer(persistOpts);
      });
      return;
    }

    showDesignLoading(step === 1 ? 'Guardando configuración...' : 'Guardando diseño...');
    persistDesignToServer(persistOpts);
  }

  function clearTransientLocalState() {
    transientKeys.forEach(function(k){ localStorage.removeItem(k); });
  }

  function clearPersistentDraft() {
    try { localStorage.removeItem(draftStorageKey); } catch (e) {}
  }

  function mapDraftDataToDesignLoad(data) {
    if (!data) return null;
    return {
      format: data.format ?? null,
      page: data.page ?? null,
      rows: data.rows ?? null,
      cols: data.cols ?? null,
      orientation: data.orientation ?? null,
      margin_up: data.margins?.up ?? null,
      margin_right: data.margins?.right ?? null,
      margin_left: data.margins?.left ?? null,
      margin_top: data.margins?.top ?? null,
      identation: data.identation ?? null,
      cut_lines: data.cut_lines ?? null,
      matrix_box: data.matrix_box ?? null,
      margin_custom: data.margin_custom ?? null,
      page_rigth: data.horizontal_space ?? null,
      page_bottom: data.vertical_space ?? null,
      participation_html: data.participation_html ?? null,
      cover_html: data.cover_html ?? null,
      back_html: data.back_html ?? null,
      backgrounds: data.backgrounds ?? null
    };
  }

  function hydrateTransientStateFromDraft(draft) {
    if (!draft || !draft.data) return;
    if (draft.data.participation_html) {
      localStorage.setItem('step2', $('<div>').html(draft.data.participation_html).find('#containment-wrapper2').html() || '');
    }
    if (draft.data.cover_html) {
      localStorage.setItem('step3', $('<div>').html(draft.data.cover_html).find('#containment-wrapper3').html() || '');
    }
    if (draft.data.back_html) {
      localStorage.setItem('step4', $('<div>').html(draft.data.back_html).find('#containment-wrapper4').html() || '');
    }
    if (draft.data.backgrounds) {
      [2,3,4].forEach(function(i){
        var bg = draft.data.backgrounds['step'+i];
        if (!bg) return;
        localStorage.setItem('bg-step'+i, bg.color || '#dfdfdf');
        localStorage.setItem('bgimg-step'+i, bg.image || '');
      });
    }
    if (draft.ui && draft.ui.guide_colors) {
      [2,3,4].forEach(function(i){
        if (draft.ui.guide_colors['step'+i]) {
          localStorage.setItem('guide-step'+i, draft.ui.guide_colors['step'+i]);
        }
      });
    }
  }

  function tryRestorePersistentDraft() {
    if (window.__forceFreshDraft) {
      clearPersistentDraft();
      clearTransientLocalState();
      return null;
    }

    let draft = null;
    try {
      draft = JSON.parse(localStorage.getItem(draftStorageKey) || 'null');
    } catch (e) {
      draft = null;
    }
    if (!draft || !draft.context || !draft.data) return null;

    const sameSet = Number(draft.context.set_id || 0) === Number(draftContext.set_id || 0);
    const sameEntity = Number(draft.context.entity_id || 0) === Number(draftContext.entity_id || 0);
    const sameReserve = Number(draft.context.reserve_id || 0) === Number(draftContext.reserve_id || 0);
    if (!(sameSet && sameEntity && sameReserve)) return null;
    return draft;
  }

  function applyDraftSelection(continueDraft) {
    if (!continueDraft) {
      clearPersistentDraft();
      clearTransientLocalState();
      restoredDraftStep = null;
      return;
    }
    if (!pendingPersistentDraft) return;
    clearTransientLocalState();
    hydrateTransientStateFromDraft(pendingPersistentDraft);
    window.__designLoad = mapDraftDataToDesignLoad(pendingPersistentDraft.data);
    window.__designId = pendingPersistentDraft.data.design_id || window.__designId || null;
    window.__designUpdatedAt = pendingPersistentDraft.data.expected_updated_at || window.__designUpdatedAt || null;
    restoredDraftStep = parseInt(pendingPersistentDraft.step, 10) || null;
  }

  function buildPersistentDraftPayload() {
    const data = collectDesignData();
    const guides = {
      step2: localStorage.getItem('guide-step2') || null,
      step3: localStorage.getItem('guide-step3') || null,
      step4: localStorage.getItem('guide-step4') || null
    };
    return {
      version: 1,
      saved_at: new Date().toISOString(),
      context: draftContext,
      step: step,
      data: data,
      ui: {
        guide_colors: guides
      }
    };
  }

  function persistDraftLocally() {
    if (window.__designLocked) return;
    try {
      localStorage.setItem(draftStorageKey, JSON.stringify(buildPersistentDraftPayload()));
    } catch (e) {}
  }

  function scheduleDraftPersist() {
    if (draftPersistTimer) clearTimeout(draftPersistTimer);
    draftPersistTimer = setTimeout(function() {
      persistDraftLocally();
    }, 800);
  }

  if (window.__preferServerDesign && window.__designLoad) {
    clearPersistentDraft();
    clearTransientLocalState();
    pendingPersistentDraft = null;
  } else {
    pendingPersistentDraft = tryRestorePersistentDraft();
  }

  // Reaplicar position/right/top/margin al .format-box del paso 2 en digital (el JS que actualiza width/height lo sobrescribe)
  function applyDigitalFormatBoxStep2() {
    if (!isDigitalSet) return;
    var $fb = $('#step-2 .format-box');
    if (!$fb.length) return;
    var matrixMm = parseFloat($('#matrix-box').val()) || 40;
    $fb.css({ position: 'absolute', right: '0', top: '0', margin: '0' });
  }

  $('#design-name-confirm').click(function() {
    window.__pendingDesignName = ($('#design-name-input').val() || '').trim() || window.__defaultDesignName || 'Diseño';
    if (typeof bootstrap !== 'undefined') {
      var el = document.getElementById('design-name-modal');
      var inst = bootstrap.Modal.getInstance(el);
      if (inst) inst.hide();
    }
    persistDesignToServer({
      reason: 'final-save',
      showLoader: true,
      redirectOnSuccess: true
    });
  });

  $('#design-name-cancel').click(function() {
    if (typeof bootstrap !== 'undefined') {
      var el = document.getElementById('design-name-modal');
      var inst = bootstrap.Modal.getInstance(el);
      if (inst) inst.hide();
    }
  });

  $('.prev-step').click(function (e) {
      e.preventDefault();

      var navigateBack = function() {
        if (step == 1) {
          window.location.href = '{{ route('design.showChooseType') }}';
          return;
        }

        syncCurrentStepToLocalStorage();
        stashStepHistory(step);
        var previousStep = step - 1;
        step = previousStep;
        
        // Limpiar observers anteriores
        if (resizeObserver) {
          resizeObserver.disconnect();
          resizeObserver = null;
        }
        if (containerObserver) {
          containerObserver.disconnect();
          containerObserver = null;
        }

        $('.form-card[id*="step-"]').addClass('d-none').removeClass('show');
        $('.form-card[id="step-'+step+'"]').removeClass('d-none fade').addClass('show');

        if (localStorage.getItem('step'+step)) {
            $('#containment-wrapper'+step).html(localStorage.getItem('step'+step));
        }
        if (step === 3) ensurePortadaQrPlaceholder();

        initCanvasInteractions();
        
        setTimeout(function() {
          loadStepHistory(step);
          ensureStepHistoryInitialized();
        }, 100);

        if ($('#containment-wrapper'+step).length) {
            var $bgEl = (typeof getBackgroundTargetEl === 'function')
              ? getBackgroundTargetEl(step)
              : ((step === 4) ? $('#design-back-bg') : $('#containment-wrapper'+step));
            if ($bgEl.length) {
              if(localStorage.getItem('bg-step'+step)){
                $bgEl.css('background-color', localStorage.getItem('bg-step'+step));
                $bgEl.css('background-image', bgImageCssUrl(localStorage.getItem('bgimg-step'+step)));
              } else {
                $bgEl.css('background-color', '#dfdfdf');
                $bgEl.css('background-image', 'none');
              }
            }
            if (typeof syncMarginBgLayers === 'function') syncMarginBgLayers();
        }

        updateDesignActionButtons();
        persistDraftLocally();

        $('.form-wizard-element').removeClass('active');
        $('#bc-step-'+step).addClass('active');

        configMargins();
        if (typeof applyPendingRescaleIfStep2 === 'function') applyPendingRescaleIfStep2();
      };

      confirmLeaveWithUnsaved(navigateBack);
  });

  $('.next-step').click(function (e) {
      e.preventDefault();

      if (step == 5 || (isDigitalSet && step == 2)) {
        e.preventDefault();
          if (window.__designLocked) {
            alert(window.__designLockMessage || 'Este set no permite edición de diseño por estado operativo.');
            return;
          }
          $('#design-name-input').val(window.__pendingDesignName || window.__defaultDesignName || '');
          if (typeof bootstrap !== 'undefined' && document.getElementById('design-name-modal')) {
            var nameModal = new bootstrap.Modal(document.getElementById('design-name-modal'));
            nameModal.show();
          } else {
            var name = prompt('Nombre del diseño:', window.__defaultDesignName || '');
            if (name === null) return;
            window.__pendingDesignName = name;
            persistDesignToServer({
              reason: 'final-save',
              showLoader: true,
              redirectOnSuccess: true
            });
          }
          return;

      }else{
          syncCurrentStepToLocalStorage();
          persistDraftLocally();
          stashStepHistory(step);
          step +=1;
          loadStepHistory(step);
          
          // Limpiar observers anteriores
          if (resizeObserver) {
            resizeObserver.disconnect();
            resizeObserver = null;
          }
          if (containerObserver) {
            containerObserver.disconnect();
            containerObserver = null;
          }

          $('.form-card[id*="step-"]').addClass('d-none').removeClass('show');
          $('.form-card[id="step-'+step+'"]').removeClass('d-none fade').addClass('show');

          if (localStorage.getItem('step'+step)) {
            $('#containment-wrapper'+step).html(localStorage.getItem('step'+step));
            // Agregar botón de editar a elementos de texto existentes
            $('.elements.text').each(function() {
              if ($(this).find('.edit-btn').length === 0) {
                $(this).prepend('<button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>');
              }
            });
            // Agregar botón de cambiar imagen a elementos de imagen existentes
            $('.elements.images').each(function() {
              if ($(this).find('.edit-btn').length === 0) {
                $(this).append('<button class="edit-btn" title="Cambiar imagen"><i class="ri-image-line"></i></button>');
              }
            });
          }
          if (step === 3) ensurePortadaQrPlaceholder();

          initCanvasInteractions();
          
          // Guardar estado inicial del paso
          setTimeout(() => {
            ensureStepHistoryInitialized();
            updateUndoRedoButtons();
          }, 100);

          if ($('#containment-wrapper'+step).length) {
              var $bgEl = (typeof getBackgroundTargetEl === 'function')
                ? getBackgroundTargetEl(step)
                : ((step === 4) ? $('#design-back-bg') : $('#containment-wrapper'+step));
              if ($bgEl.length) {
                if(localStorage.getItem('bg-step'+step)){
                  $bgEl.css('background-color', localStorage.getItem('bg-step'+step));
                  $bgEl.css('background-image', bgImageCssUrl(localStorage.getItem('bgimg-step'+step)));
                } else {
                  $bgEl.css('background-color', '#dfdfdf');
                  $bgEl.css('background-image', 'none');
                }
              }
              if (typeof syncMarginBgLayers === 'function') syncMarginBgLayers();
              if(localStorage.getItem('guide-step'+step)){
                  $('.guide'+step).css('border-color', localStorage.getItem('guide-step'+step));
              }else{
                  $('.guide'+step).css('border-color', 'purple');
              }
          }

          $('.form-wizard-element').removeClass('active');
          $('#bc-step-'+step).addClass('active');

          configMargins();
          if (typeof applyPendingRescaleIfStep2 === 'function') applyPendingRescaleIfStep2();
          if (step === 2 && typeof applyDigitalFormatBoxStep2 === 'function') applyDigitalFormatBoxStep2();

          // Si hay un diseño cargado (base) y estamos en paso 2, generar thumbnail de participación sin tener que tocar nada
          if (step === 2 && window.__designLoad && snapshot_path === null && document.querySelector('#step-2 .format-box')) {
            setTimeout(function() {
              if (snapshot_path !== null) return;
              showDesignLoading('Generando vista previa de participación...');
              generateParticipationSnapshot(function() {});
            }, 600);
          }

      }

      if (step == 2) {

        let format = $('#format').val();
        let w = 200;
        let h = 92;
        let orientation = $('#orientation').val();
        if (format != 'custom') {
            if (format == 'a3-h-3x2') {
                w = 200;
                h = 92;
            }
            else if (format == 'a3-h-4x2') {
                w = 200;
                h = 68.88;
            }
            else if (format == 'a4-v-3x1') {
                w = 190;
                h = 92;
            }
            else if (format == 'a4-v-4x1') {
                w = 190;
                h = 69.38;
            } else{
                // Personalizado
                {{-- const dims = getCustomDimensions();
                w = dims.w;
                h = dims.h; --}}
            }

            $('[id*="containment-wrapper"]').parent().css({
                width: w+'mm',
                height: h+'mm'
            });
            if (typeof applyDigitalFormatBoxStep2 === 'function') applyDigitalFormatBoxStep2();
        }
        let matrix = $('#matrix-box').val() ?? 40;
        $('#containment-wrapper4').css('padding-right', matrix+'mm');

      }

  });

  var editor;
  var actualElement;
  
  // Sistema de Undo/Redo limitado
  var historyStates = [];
  var currentHistoryIndex = -1;
  var maxHistoryStates = 30;
  var isRestoringState = false; // Flag para evitar guardar durante restauración
  var resizeTimeout; // Para debounce del ResizeObserver

  /** Clona .format-box y elimina resize del HTML exportado (no altera left/top). */
  function getFormatBoxHtmlForSave(selector) {
    var el = document.querySelector(selector);
    if (!el) return '';
    var clone = el.cloneNode(true);
    clone.querySelectorAll('.elements').forEach(function(node) {
      node.style.removeProperty('resize');
    });
    return clone.outerHTML;
  }

  function enableDesignElementsResize($scope) {
    var $els = ($scope && $scope.length)
      ? $scope.find('.elements')
      : $('[id^="containment-wrapper"] .elements, #design-back-bg .elements');
    $els.each(function() {
      this.style.setProperty('resize', 'both', 'important');
      if (!$(this).hasClass('text-vertical') && $(this).attr('data-text-vertical') !== '1') {
        this.style.setProperty('overflow', 'hidden', 'important');
      }
    });
    enforceQrMinSize($scope);
    if (typeof normalizeVerticalTextBoxes === 'function') {
      normalizeVerticalTextBoxes($scope);
    }
  }

  /** Mínimo 0,9×0,9 cm (9 mm ≈ 35px @ 96dpi). Amplía sin mover left/top. */
  function enforceQrMinSize($scope) {
    var minPx = Math.ceil(9 * 96 / 25.4);
    var $root = ($scope && $scope.length) ? $scope : $(document);
    $root.find('.elements.qr').each(function () {
      var el = this;
      var $el = $(el);
      var w = parseFloat(el.style.width);
      var h = parseFloat(el.style.height);
      if (!isFinite(w) || w <= 0) w = $el.outerWidth() || 0;
      if (!isFinite(h) || h <= 0) h = $el.outerHeight() || 0;
      var side = Math.max(w, h, minPx);
      // No recentrar: al mínimo el recentrado hacía «saltar» el QR al soltarlo.
      if (Math.abs(w - side) > 0.5 || Math.abs(h - side) > 0.5) {
        el.style.width = side + 'px';
        el.style.height = side + 'px';
      }
      el.style.minWidth = minPx + 'px';
      el.style.minHeight = minPx + 'px';
    });
  }

  function destroyStepDraggables(stepNum) {
    var $container = $('#containment-wrapper' + stepNum);
    if (!$container.length) return;
    $container.find('.elements').each(function() {
      var $el = $(this);
      if ($el.data('ui-draggable')) {
        try { $el.draggable('destroy'); } catch (e) {}
      }
    });
  }

  // Funciones del sistema de Undo/Redo
  function saveHistoryState() {
    if (isRestoringState) return; // Evitar guardar durante restauración
    
    console.log('saveHistoryState called, step:', step);
    
    const canvasHtml = $('#containment-wrapper' + step).html();
    const canvasState = {
      html: canvasHtml,
      step: step,
      timestamp: Date.now()
    };
    
    // Remover estados futuros si estamos en medio del historial
    if (currentHistoryIndex < historyStates.length - 1) {
      historyStates = historyStates.slice(0, currentHistoryIndex + 1);
    }
    
    // Agregar nuevo estado
    historyStates.push(canvasState);
    currentHistoryIndex++;
    
    // Mantener máximo de estados
    if (historyStates.length > maxHistoryStates) {
      historyStates.shift();
      currentHistoryIndex--;
    }

    historyByStep[step] = historyStates.slice();
    historyIndexByStep[step] = currentHistoryIndex;
    
    updateUndoRedoButtons();
  }
  
  function restoreHistoryState(targetIndex) {
    if (targetIndex < 0 || targetIndex >= historyStates.length) return;
    
    isRestoringState = true;
    
    const targetState = historyStates[targetIndex];
    
    // Solo restaurar si es el mismo step
    if (targetState.step === step) {
      $('#containment-wrapper' + step).html(targetState.html);
      
      // Re-vincular eventos después de restaurar
      rebindEventsAfterRestore();
      
      currentHistoryIndex = targetIndex;
      historyByStep[step] = historyStates.slice();
      historyIndexByStep[step] = currentHistoryIndex;
      updateUndoRedoButtons();
    }
    
    isRestoringState = false;
  }
  
  function undo() {
    console.log('Undo called, canUndo:', canUndo());
    if (canUndo()) {
      console.log('Restoring to index:', currentHistoryIndex - 1);
      restoreHistoryState(currentHistoryIndex - 1);
    }
  }
  
  function redo() {
    if (canRedo()) {
      restoreHistoryState(currentHistoryIndex + 1);
    }
  }
  
  function canUndo() {
    return currentHistoryIndex > 0 && historyStates.length > 1;
  }
  
  function canRedo() {
    return currentHistoryIndex < historyStates.length - 1;
  }
  
  function updateUndoRedoButtons() {
    $('.undo-btn').prop('disabled', !canUndo());
    $('.redo-btn').prop('disabled', !canRedo());
  }

  $(document).off('click.designUndo').on('click.designUndo', '.undo-btn', function(e) {
    e.preventDefault();
    if ($(this).prop('disabled')) return;
    undo();
  });

  $(document).off('click.designRedo').on('click.designRedo', '.redo-btn', function(e) {
    e.preventDefault();
    if ($(this).prop('disabled')) return;
    redo();
  });
  
  // Compensación de arrastre con zoom: offset del clic en coordenadas lógicas
  var dragClickOffsetX, dragClickOffsetY;

  // Límites = borde morado (identation / Sangres). Trasera: derecho = matrix-box (no pasar de la matriz)
  // Usar offsetWidth/Height (sin transform): getBoundingClientRect incluye el zoom y afloja los límites.
  function getMarginBoundsPx() {
    var $box = $('#step-' + step + ' .format-box');
    if (!$box.length) return null;
    var el = $box[0];
    var boxW = el.offsetWidth;
    var boxH = el.offsetHeight;
    if (!(boxW > 0) || !(boxH > 0)) {
      var r = el.getBoundingClientRect();
      var z = (typeof designZoom === 'number' && designZoom > 0) ? designZoom : 1;
      boxW = r.width / z;
      boxH = r.height / z;
    }
    var ticketW = parseFloat($('#ticket-size').data('w')) || 200;
    var ticketH = parseFloat($('#ticket-size').data('h')) || 92;
    var scaleX = boxW / ticketW, scaleY = boxH / ticketH;
    var identation = parseIdentationMm();
    var matrix = parseFloat($('#matrix-box').val()) || 40;
    var minLeft = identation * scaleX;
    var minTop = identation * scaleY;
    var maxBottom = boxH - identation * scaleY;
    var maxRight;
    if (step === 4) {
      maxRight = boxW - (identation + matrix) * scaleX;
    } else {
      maxRight = boxW - identation * scaleX;
    }
    return {
      minLeft: minLeft,
      minTop: minTop,
      maxRight: maxRight,
      maxBottom: maxBottom
    };
  }
  function clampElementToMargins(el) {
    if (step === 1 || step === 5) return;
    var bounds = getMarginBoundsPx();
    if (!bounds) return;
    var $el = $(el);
    var left = parseFloat($el.css('left')) || 0;
    var top = parseFloat($el.css('top')) || 0;
    var w = $el.outerWidth() || 0;
    var h = $el.outerHeight() || 0;
    left = Math.max(bounds.minLeft, Math.min(bounds.maxRight - w, left));
    top = Math.max(bounds.minTop, Math.min(bounds.maxBottom - h, top));
    // Fijar solo top/left: bottom/right/inset del placeholder QR/barra rompen el PDF
    el.style.removeProperty('bottom');
    el.style.removeProperty('right');
    el.style.removeProperty('inset');
    $el.css({ left: left + 'px', top: top + 'px' });
  }

  // Función auxiliar para configurar draggable con guardado de estado
  function setupDraggable() {
    // Solo en pasos con canvas (2, 3, 4): step 1 no tiene containment-wrapper1 y rompe el init
    if (step < 2 || step > 4) return;
    var $cont = $('#containment-wrapper'+step);
    if (!$cont.length) return;

    destroyStepDraggables(step);
    enableDesignElementsResize($cont);

    $cont.find('.elements').draggable({
      handle: 'span',
      // Con zoom, el containment nativo de jQuery UI usa coords escaladas y falla.
      containment: (typeof designZoom === 'number' && designZoom !== 1) ? false : '#containment-wrapper'+step,
      scroll: false,
      start: function(event, ui){
        selectDesignElement($(this));
        markDesignDirty();
        updateUndoRedoButtons();
        if (typeof designZoom !== 'undefined' && designZoom !== 1) {
          var el = ui.helper[0];
          var r = el.getBoundingClientRect();
          dragClickOffsetX = (event.clientX - r.left) / designZoom;
          dragClickOffsetY = (event.clientY - r.top) / designZoom;
        }
      },
      drag: function(event, ui) {
        if (typeof designZoom !== 'undefined' && designZoom !== 1) {
          var containment = document.getElementById('containment-wrapper' + step);
          if (containment) {
            var cr = containment.getBoundingClientRect();
            var mouseLogicalX = (event.clientX - cr.left) / designZoom;
            var mouseLogicalY = (event.clientY - cr.top) / designZoom;
            ui.position.left = mouseLogicalX - dragClickOffsetX;
            ui.position.top = mouseLogicalY - dragClickOffsetY;
          }
        }
        var bounds = getMarginBoundsPx();
        if (bounds && step >= 2 && step <= 4) {
          var w = $(ui.helper).outerWidth() || 0, h = $(ui.helper).outerHeight() || 0;
          ui.position.left = Math.max(bounds.minLeft, Math.min(bounds.maxRight - w, ui.position.left));
          ui.position.top = Math.max(bounds.minTop, Math.min(bounds.maxBottom - h, ui.position.top));
        }
      },
      stop: function(event, ui) {
        // Forzar left/top inline siempre (si no, al guardar se pierden)
        ui.helper.css({
          position: 'absolute',
          left: ui.position.left + 'px',
          top: ui.position.top + 'px'
        });
        if (step >= 2 && step <= 4) clampElementToMargins(ui.helper[0]);
        console.log('Draggable stop - saving state');
        saveHistoryState();
      }
    });
  }

  // Variable para almacenar el observer
  var resizeObserver = null;
  var containerObserver = null;

  // Función para detectar redimensionamiento de elementos
  function setupResizeObserver() {
    // Limpiar observers anteriores si existen
    if (resizeObserver) {
      resizeObserver.disconnect();
    }
    if (containerObserver) {
      containerObserver.disconnect();
    }

    const container = document.getElementById('containment-wrapper' + step);
    if (!container) return;

    // Observer para detectar cambios en el atributo style (redimensionamiento)
    resizeObserver = new MutationObserver(function(mutations) {
      let shouldSave = false;
      mutations.forEach(function(mutation) {
        if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
          // Solo guardar si el cambio es en width o height (redimensionamiento)
          const oldValue = mutation.oldValue || '';
          const newValue = mutation.target.getAttribute('style') || '';
          // Verificar si cambió width o height
          const widthChanged = (oldValue.match(/width:\s*[^;]+/) || [''])[0] !== (newValue.match(/width:\s*[^;]+/) || [''])[0];
          const heightChanged = (oldValue.match(/height:\s*[^;]+/) || [''])[0] !== (newValue.match(/height:\s*[^;]+/) || [''])[0];
          if (widthChanged || heightChanged) {
            shouldSave = true;
          }
        }
      });

      if (shouldSave) {
        // Debounce para evitar guardar demasiadas veces
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
          console.log('Element resized - saving state');
          saveHistoryState();
        }, 300); // Esperar 300ms después del último cambio
      }
    });

    // Observer para detectar cuando se agregan nuevos elementos
    containerObserver = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        mutation.addedNodes.forEach(function(node) {
          if (node.nodeType === 1 && node.classList && node.classList.contains('elements')) {
            // Observar el nuevo elemento
            resizeObserver.observe(node, {
              attributes: true,
              attributeFilter: ['style'],
              attributeOldValue: true
            });
          }
        });
      });
    });

    // Observar el contenedor para detectar nuevos elementos
    containerObserver.observe(container, {
      childList: true,
      subtree: true
    });

    // Observar todos los elementos existentes con clase .elements
    $(container).find('.elements').each(function() {
      resizeObserver.observe(this, {
        attributes: true,
        attributeFilter: ['style'],
        attributeOldValue: true // Necesario para comparar valores antiguos
      });
    });
  }

  function selectDesignElement($el) {
    if (!$el || !$el.length) return;
    $el = $($el).closest('.elements');
    if (!$el.length) return;
    $('.elements').removeClass('selected');
    $el.addClass('selected');
    selectedElement = $el;
    $('.up-layer, .down-layer, .delete-element-btn').prop('disabled', false);
    if ($el.hasClass('text')) {
      $('.text-style-btn').prop('disabled', false);
      var isVertical = $el.hasClass('text-vertical') || $el.attr('data-text-vertical') === '1';
      $('.text-vertical-btn').toggleClass('active', isVertical).attr('aria-pressed', isVertical ? 'true' : 'false');
    } else {
      $('.text-style-btn').prop('disabled', true);
      $('.text-vertical-btn').removeClass('active').attr('aria-pressed', 'false');
    }
    if (typeof updateUndoRedoButtons === 'function') updateUndoRedoButtons();
  }

  function ensureElementEditButtons($root) {
    var $scope = $root && $root.length ? $root : $(document);
    $scope.find('.elements.text').each(function() {
      if ($(this).find('> .edit-btn, .edit-btn').length === 0) {
        $(this).prepend('<button type="button" class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>');
      }
    });
    $scope.find('.elements.images').each(function() {
      if ($(this).find('> .edit-btn, .edit-btn').length === 0) {
        $(this).append('<button type="button" class="edit-btn" title="Cambiar imagen"><i class="ri-image-line"></i></button>');
      }
    });
  }

  function bindCanvasEditButtons() {
    // Delegados: funcionan aunque el HTML se recargue o falte el botón al inicio
    $(document).off('click.editelement', '.elements.text .edit-btn').on('click.editelement', '.elements.text .edit-btn', function(e) {
      e.preventDefault();
      e.stopPropagation();
      editelements.call(this, e);
      return false;
    });
    $(document).off('click.changeimage', '.elements.images .edit-btn').on('click.changeimage', '.elements.images .edit-btn', function(e) {
      e.preventDefault();
      e.stopPropagation();
      changeImage.call(this, e);
      return false;
    });
    $(document).off('dblclick.edittext', '.elements.text').on('dblclick.edittext', '.elements.text', function(e) {
      if ($(e.target).closest('.edit-btn').length) return;
      e.preventDefault();
      e.stopPropagation();
      editelements.call($(this).find('.edit-btn').get(0) || this, e);
      return false;
    });
  }

  function bindCanvasDeselect() {
    $('body').off('click.deselect').on('click.deselect', function(e) {
      if ($('#imagen-modal').hasClass('show') || $('#ckeditor-modal').hasClass('show') || $('#qr-modal').hasClass('show') || $('#position-modal').hasClass('show') || $('#bar-options-modal').hasClass('show')) return;
      if (!$(e.target).closest('.elements').length && !$(e.target).closest('.up-layer, .down-layer, .text-style-btn, .delete-element-btn, .undo-btn, #bar-options-modal').length) {
        $('.elements').removeClass('selected');
        selectedElement = null;
        $('.up-layer, .down-layer, .text-style-btn, .delete-element-btn').prop('disabled', true);
      }
    });
  }

  function clearStaleTextPlaceholders($root) {
    var $scope = $root && $root.length ? $root : $(document);
    $scope.find('.elements.text.text-placeholder-new').each(function() {
      var $el = $(this);
      var plain = $.trim($el.find('span').first().text() || '');
      if (plain && plain !== 'Escribe aquí...') {
        $el.removeClass('text-placeholder-new');
      }
    });
  }

  function initCanvasInteractions() {
    if (step < 2 || step > 4) return;
    var $wrap = $('#containment-wrapper' + step);
    enableDesignElementsResize($wrap);
    ensureElementEditButtons($wrap.length ? $wrap : $(document));
    markCriticalDesignElements($wrap);
    clearStaleTextPlaceholders($wrap);
    bindCanvasEditButtons();
    setupDraggable();
    setupResizeObserver();
    addEventsElement();
  }

  function rebindEventsAfterRestore() {
    initCanvasInteractions();
  }

  function editelements(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }
    actualElement = $(this).closest('.elements.text');
    selectDesignElement(actualElement);
    
    // Obtener el contenido del span (sin el botón de editar)
    var $wrapper = getTextContentWrapper($(actualElement));
    var contenidoHTML = $wrapper.html() || '';

    // Destruir instancia previa si existe
    if (editor && CKEDITOR.instances['editor']) {
        CKEDITOR.instances['editor'].destroy(true);
    }

    // Limpiar el contenido del div
    $('#editor').html('');

    // Inicializar CKEditor
    editor = CKEDITOR.replace('editor', buildCKEditorConfig(contenidoHTML));

    $('#ckeditor-modal').modal('show');
  }

  function deleteElements(event) {

    let element = $(this);

    if (element.hasClass('element-critical')) {
      alert('Este elemento es obligatorio y no se puede eliminar.');
      return;
    }
    if (confirm('¿Desea eliminar el elemento seleccionado?')) {
        element.remove();
        markDesignDirty();
        saveHistoryState(); // Guardar estado después de eliminar
        updateUndoRedoButtons(); // Actualizar estado de botones
    }

  }

  function changeImage(event) {

    actualElement = $(this).closest('.elements.images');

    $('#imagen-modal').modal('show');

  }

  function setQRtext(event) {

    actualElement = $(this);

    $('#qr-modal').modal('show');

  }

  $('.deleteElements').click(function (e) {
      e.preventDefault();
      if (actualElement && actualElement.hasClass('element-critical')) {
        alert('Este elemento es obligatorio y no se puede eliminar.');
        return;
      }
      if (confirm('¿Desea eliminar el elemento seleccionado?')) {
        actualElement.remove();
        markDesignDirty();
        saveHistoryState(); // Guardar estado después de eliminar
        updateUndoRedoButtons(); // Actualizar estado de botones
    }
  });

  $('.accept-text').click(function(event) {
    /* Act on the event */
    if (editor && CKEDITOR.instances['editor']) {
        var data = CKEDITOR.instances['editor'].getData();
        // Limpiar párrafos vacíos
        data = data.replace(/<p>&nbsp;<\/p>/gi, '').replace(/<p><\/p>/gi, '');
        var $element = $(actualElement);
        var $wrapper = getTextContentWrapper($element);
        var detectedAlign = detectAlignmentFromHtml(data) || getTextElementAlignment($element);
        $wrapper.html(data);
        syncTextElementAlignment($element, detectedAlign || 'left');
        // Quitar marcador naranja de “texto nuevo” al editar
        $element.removeClass('text-placeholder-new');
        CKEDITOR.instances['editor'].destroy(true);
    }
    $('#ckeditor-modal').modal('hide');
    markDesignDirty();
    
    saveHistoryState(); // Guardar estado después de editar texto
    updateUndoRedoButtons(); // Actualizar estado de botones
  });

  const input = document.getElementById('imageInput');
  $('.accept-image').click(function (e) {
      e.preventDefault();

      if (input.files && input.files[0]) {
            const file = input.files[0];
            if (file.type.startsWith('image/')) {
                // Imagen válida
                uploadImage(file);
            } else {
                console.log("El archivo seleccionado no es una imagen.");
            }
        }
  });

  $('.accept-qr').click(function (e) {
    e.preventDefault();

    const formData = new FormData();
    formData.append('text', $('#qr-text').val());
    showDesignLoading('Generando código QR...');
    fetch(@json($design_qr_url ?? route('design.generateQr')), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.url) {
            $(actualElement).find('img').attr('src', data.url);
            $('#qr-modal').modal('hide');
            $('#qr-text').val("");
            markDesignDirty();
        }
    })
    .catch(error => console.error('Error al subir la imagen:', error))
    .finally(() => hideDesignLoading());
  });

  function uploadImage(file) {
    const formData = new FormData();
    formData.append('image', file);
    showDesignLoading('Subiendo imagen...');
    fetch(@json($design_upload_url ?? route('design.uploadImage')), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.url) {
            $(actualElement).find('img').attr('src', data.url);
            $('#imagen-modal').modal('hide');
            input.value = null;
            markDesignDirty();
        }
    })
    .catch(error => console.error('Error al subir la imagen:', error))
    .finally(() => hideDesignLoading());
  }

  $('.add-text').click(function (e) {
      e.preventDefault();
      var $box = $('#step-' + step + ' .format-box');
      var boxW = $box.length ? $box.width() : 400;
      var boxH = $box.length ? $box.height() : 300;
      var left = Math.max(20, Math.round((boxW - 220) / 2));
      var top = Math.max(20, Math.round((boxH - 100) / 2));

      var $newEl = $(`<div class="elements text text-placeholder-new selected" style="padding: 12px; width: 220px; height: 100px; resize: both; overflow: hidden; position: absolute; top: ${top}px; left: ${left}px; z-index: 5000;">
            <button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>
            <span><strong>Escribe aquí...</strong></span>
        </div>`);

      $('#containment-wrapper'+step).append($newEl);
      selectDesignElement($newEl);
      initCanvasInteractions();
      markDesignDirty();
      saveHistoryState();
      updateUndoRedoButtons();
  });

  function coverMandatoryTokens() {
    return ['taco_label', 'taco_number', 'taco_total', 'participation_from', 'participation_to', '__TACO_LABEL__', '%%TACO_LABEL%%'];
  }

  function elementHasCoverMandatoryToken($el) {
    if (!$el || !$el.length) return false;
    if ($el.hasClass('cover-taco-qr') || $el.hasClass('cover-taco-label') || $el.hasClass('qr')) return true;
    var haystack = ($el.html() || '') + ' ' + ($el.text() || '');
    var tokens = coverMandatoryTokens();
    for (var i = 0; i < tokens.length; i++) {
      if (haystack.indexOf(tokens[i]) !== -1) return true;
    }
    return /\{\{\s*taco[_\-\s]*(label|number|total)\s*\}\}/i.test(haystack)
      || /\{\{\s*participation_(from|to)\s*\}\}/i.test(haystack);
  }

  function markCoverCriticalElements($root) {
    var $scope = ($root && $root.length) ? $root : $('#containment-wrapper3');
    if (!$scope.length) return;
    $scope.find('.elements.qr, .elements.cover-taco-qr, .elements.cover-taco-label').addClass('element-critical');
    $scope.find('.elements').each(function() {
      if (elementHasCoverMandatoryToken($(this))) {
        $(this).addClass('element-critical');
      }
    });
  }

  function markCriticalDesignElements($root) {
    var $scope = $root && $root.length ? $root : $('#containment-wrapper' + step);
    $scope.find('.elements.participation, .elements.reference, .elements.qr, .elements.number, .elements.mini')
      .addClass('element-critical');
    if (step === 3 || ($scope.attr('id') === 'containment-wrapper3')) {
      markCoverCriticalElements($scope);
    }
  }

  $(document).off('click.resetMandatory', '.reset-mandatory-canvas').on('click.resetMandatory', '.reset-mandatory-canvas', function (e) {
      e.preventDefault();
      if (step < 2 || step > 4) return;

      var $wrap = $('#containment-wrapper' + step);
      if (!$wrap.length) return;

      markCriticalDesignElements($wrap);
      var $removable = $wrap.find('.elements').not('.element-critical');
      var count = $removable.length;
      if (count === 0) {
        alert('No hay campos de ejemplo que limpiar. Solo quedan los obligatorios.');
        return;
      }

      if (!confirm(
        'Se eliminarán ' + count + ' campo(s) de ejemplo de este paso.\n\n' +
        'Se conservan los obligatorios (número, participación, referencia, QR, taco/participaciones en portada, etc.).\n' +
        'Puedes deshacer con el botón Deshacer.\n\n¿Continuar?'
      )) {
        return;
      }

      $removable.remove();
      $('.elements').removeClass('selected');
      selectedElement = null;
      actualElement = null;
      $('.up-layer, .down-layer, .delete-element-btn, .text-style-btn').prop('disabled', true);
      if (typeof rebindEventsAfterRestore === 'function') {
        rebindEventsAfterRestore();
      } else if (typeof initCanvasInteractions === 'function') {
        initCanvasInteractions();
      }
      markDesignDirty();
      saveHistoryState();
      updateUndoRedoButtons();
      if (typeof syncCurrentStepToLocalStorage === 'function') {
        syncCurrentStepToLocalStorage();
      }
  });

  function syncSkipBackBannerUi() {
      var skipped = !!window.__backSkipped;
      $('#skip-back-banner').removeClass('d-none');
      $('#skip-back-banner .skip-back-msg').toggleClass('d-none', skipped);
      $('#skip-back-banner .restore-back-msg').toggleClass('d-none', !skipped);
      $('#btn-skip-back-design').toggleClass('d-none', skipped);
      $('#btn-restore-back-design').toggleClass('d-none', !skipped);
      if (typeof updateDesignActionButtons === 'function') {
        updateDesignActionButtons();
      }
  }

  $('#btn-skip-back-design').click(function (e) {
      e.preventDefault();
      if (!confirm('¿Omitir el diseño de trasera? No podrá descargar PDF de traseras para este diseño.')) {
        return;
      }
      window.__backSkipped = true;
      $('#containment-wrapper4 .elements').remove();
      if ($('#design-back-bg').length) {
        $('#design-back-bg').css({ 'background-color': '#dfdfdf', 'background-image': 'none' });
      }
      localStorage.setItem('step4', $('#containment-wrapper4').html() || '');
      syncSkipBackBannerUi();
      markDesignDirty();
      persistDraftLocally();
      if (window.__designId) {
        persistDesignToServer({ reason: 'autosave', showLoader: false });
      }
      if (step === 4) {
        syncCurrentStepToLocalStorage();
        stashStepHistory(step);
        step = 5;
        loadStepHistory(step);
        $('.form-card[id*="step-"]').addClass('d-none').removeClass('show');
        $('.form-card[id="step-5"]').removeClass('d-none fade').addClass('show');
        $('.form-wizard-element').removeClass('active');
        $('#bc-step-5').addClass('active');
        updateDesignActionButtons();
      }
  });

  $('#btn-restore-back-design').click(function (e) {
      e.preventDefault();
      window.__backSkipped = false;
      if (typeof ensureMarginBgLayer === 'function') {
        ensureMarginBgLayer(4);
      }
      syncSkipBackBannerUi();
      markDesignDirty();
      persistDraftLocally();
      if (window.__designId) {
        persistDesignToServer({ reason: 'autosave', showLoader: false });
      }
      if (typeof PartilotToast === 'function') {
        PartilotToast('Trasera reactivada. Diseña el paso 4 y guarda el diseño.', 'success');
      }
  });

  // Estado inicial al cargar un diseño ya omitido
  if (window.__backSkipped) {
    syncSkipBackBannerUi();
  }
  $('.add-image').click(function (e) {
      e.preventDefault();

      $('#containment-wrapper'+step).append(`<div class="elements images" style="resize: both; overflow: hidden; position: absolute; top: 0"><span><img style="width: 100%; height: 100%" src="{{url('default.jpg')}}" alt=""></span><button class="edit-btn" title="Cambiar imagen"><i class="ri-image-line"></i></button></div>`);

      setupDraggable();
      setupResizeObserver();

      $('.elements.images').unbind('dblclick',changeImage);
      $('.elements.images .edit-btn').click(changeImage);
      addEventsElement();
      
      setupDraggable();
      setupResizeObserver();
      
      saveHistoryState(); // Guardar estado después de agregar
      updateUndoRedoButtons(); // Actualizar estado de botones
  });

  $('.add-qr').click(function (e) {
      e.preventDefault();
      var qrMinPx = Math.ceil(9 * 96 / 25.4);
      $('#containment-wrapper'+step).append(`<div class="elements element-critical qr" style="resize: both; overflow: hidden; position: absolute; top: 0; width: ${qrMinPx}px; height: ${qrMinPx}px; min-width: ${qrMinPx}px; min-height: ${qrMinPx}px;"><span><img style="width: 100%; height: 100%" src="{{url('basicqr.jpg')}}" alt=""></span></div>`);

      setupDraggable();
      setupResizeObserver();
      enableDesignElementsResize($('#containment-wrapper' + step));

      $('.elements.qr').unbind('dblclick',setQRtext);
      {{-- $('.elements.qr').dblclick(setQRtext); --}}
      addEventsElement();
  });

  $('.add-top').click(function (e) {
      e.preventDefault();

      $('#containment-wrapper'+step).append(`<div class="elements context" style="width: calc(100% - 60px); border-radius: 10px; height: 10%; resize: both; overflow: hidden; position: absolute; top: 20px; left: 0; right: 0; margin: auto; background-color: #dfdfdf; border: 2px solid #333;"><span style="padding: 20px; display: block;"></span></div>`);

      addEventsElement();

      setupDraggable();
      setupResizeObserver();
  });

  $('.add-bottom').click(function (e) {
      e.preventDefault();

      var criticalClass = (step === 3) ? ' element-critical cover-taco-label' : '';
      $('#containment-wrapper'+step).append(`<div class="elements context${criticalClass}" style="width: calc(100% - 60px); border-radius: 10px; height: 10%; resize: both; overflow: hidden; position: absolute; bottom: 20px; left: 0; right: 0; margin: auto; background-color: #dfdfdf; border: 2px solid #333;"><span style="padding: 8px; display: block; text-align: center; font-size: 12px; font-weight: 700;">@{{taco_label}}</span></div>`);

      addEventsElement();

      setupDraggable();
      setupResizeObserver();
  });

  $('.color input').change(function (e) {
      e.preventDefault();

      localStorage.setItem('bg-step'+step,$(this).val());
      var $bg = (typeof getBackgroundTargetEl === 'function')
        ? getBackgroundTargetEl(step)
        : ((step === 4) ? $('#design-back-bg') : $('#containment-wrapper'+step));
      if ($bg.length) $bg.css('background-color', $(this).val());
  });

  function addEventsElement()
  {
    $(document).off('mousedown.designSelect', '.elements');
    $(document).on('mousedown.designSelect', '.elements', function(e) {
      if (e.which !== 1) return;
      if ($(e.target).closest('.edit-btn').length) return;
      selectDesignElement($(this));
    });
    $(document).off('contextmenu.designElement', '.elements');
    $(document).on('contextmenu.designElement', '.elements', changePositionElement);
    bindCanvasDeselect();
  }

  function rgbToHex(rgb) {
    if (!rgb) return '#dfdfdf';
    if (typeof rgb === 'string' && rgb.charAt(0) === '#') return rgb;
    var m = rgb.match(/^rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/);
    if (m) return '#' + [1,2,3].map(function(x) { return ('0'+parseInt(m[x],10).toString(16)).slice(-2); }).join('');
    return '#dfdfdf';
  }

  // Doble clic en barra superior/inferior: abrir modal para color y borde
  var barModalElement = null;
  $(document).off('dblclick.barcontext').on('dblclick.barcontext', '.elements.context', function(e) {
    e.preventDefault();
    e.stopPropagation();
    barModalElement = $(this);
    $('#bar-modal-bg').val(rgbToHex(barModalElement.css('background-color')) || '#dfdfdf');
    var bw = parseInt(barModalElement.css('border-width'), 10);
    $('#bar-modal-border-width').val(isNaN(bw) || bw < 0 ? 0 : Math.min(20, bw));
    $('#bar-modal-border-color').val(rgbToHex(barModalElement.css('border-color')) || '#333333');
    $('#bar-options-modal').modal('show');
    return false;
  });
  $('#bar-modal-bg, #bar-modal-border-width, #bar-modal-border-color').on('input change', function() {
    if (!barModalElement || !barModalElement.length) return;
    var bg = $('#bar-modal-bg').val();
    var bw = parseInt($('#bar-modal-border-width').val(), 10) || 0;
    var bc = $('#bar-modal-border-color').val();
    barModalElement.css('background-color', bg);
    if (bw > 0) {
      barModalElement.css('border-width', bw + 'px');
      barModalElement.css('border-style', 'solid');
      barModalElement.css('border-color', bc);
    } else {
      barModalElement.css('border-width', '0');
      barModalElement.css('border-style', 'none');
      barModalElement.css('border-color', 'transparent');
    }
    if (typeof saveHistoryState === 'function') saveHistoryState();
  });
  $('#bar-modal-delete').off('click').on('click', function() {
    if (!barModalElement || !barModalElement.length) return;
    if (barModalElement.hasClass('element-critical') || elementHasCoverMandatoryToken(barModalElement)) {
      alert('Este elemento es obligatorio y no se puede eliminar.');
      return;
    }
    if (!confirm('¿Eliminar esta barra?')) return;
    barModalElement.remove();
    barModalElement = null;
    $('#bar-options-modal').modal('hide');
    selectedElement = null;
    $('.up-layer, .down-layer, .delete-element-btn').prop('disabled', true);
    $('.text-style-btn').prop('disabled', true);
    if (typeof saveHistoryState === 'function') saveHistoryState();
    if (typeof updateUndoRedoButtons === 'function') updateUndoRedoButtons();
  });

  $(document).on('input change', '.bar-bg-color, .bar-border-width, .bar-border-color', function() {
    if (!selectedElement || !selectedElement.hasClass('context')) return;
    var bg = $('#step-'+step+' .bar-bg-color').val();
    var bw = parseInt($('#step-'+step+' .bar-border-width').val(), 10) || 0;
    var bc = $('#step-'+step+' .bar-border-color').val();
    selectedElement.css('background-color', bg);
    if (bw > 0) {
      selectedElement.css('border-width', bw + 'px');
      selectedElement.css('border-style', 'solid');
      selectedElement.css('border-color', bc);
    } else {
      selectedElement.css('border-width', '0');
      selectedElement.css('border-style', 'none');
      selectedElement.css('border-color', 'transparent');
    }
    if (typeof saveHistoryState === 'function') saveHistoryState();
  });

  function getTextContentWrapper($element) {
    if (!$element || !$element.length) {
      return $element;
    }
    var $wrapper = $element.children('span').first();
    if (!$wrapper.length) {
      $wrapper = $element.find('> span').first();
    }
    return $wrapper.length ? $wrapper : $element;
  }

  function normalizeAlignValue(ta) {
    ta = String(ta || '').toLowerCase();
    if (!ta || ta === 'start') {
      return 'left';
    }
    if (ta === 'end') {
      return 'right';
    }
    if (ta.indexOf('left') >= 0) {
      return 'left';
    }
    if (ta.indexOf('right') >= 0) {
      return 'right';
    }
    if (ta.indexOf('center') >= 0) {
      return 'center';
    }
    return null;
  }

  function detectAlignmentFromHtml(html) {
    if (!html) {
      return null;
    }
    var $tmp = $('<div>').html(html);
    var $block = $tmp.children('h1, h2, h3, h4, h5, h6, p, div').first();
    if (!$block.length) {
      $block = $tmp.find('h1, h2, h3, h4, h5, h6, p, div').first();
    }
    if ($block.length) {
      var blockAlign = normalizeAlignValue($block.css('text-align') || $block.attr('align'));
      if (blockAlign) {
        return blockAlign;
      }
    }
    var styleMatch = html.match(/text-align\s*:\s*(left|right|center|start|end)/i);
    if (styleMatch) {
      return normalizeAlignValue(styleMatch[1]);
    }
    var alignMatch = html.match(/\balign\s*=\s*["']?(left|right|center)\b/i);
    if (alignMatch) {
      return normalizeAlignValue(alignMatch[1]);
    }
    return null;
  }

  function stripInlineTextAlign($root) {
    if (!$root || !$root.length) {
      return;
    }
    $root.add($root.find('*')).each(function() {
      if (this.style && this.style.textAlign) {
        this.style.removeProperty('text-align');
        if (!this.getAttribute('style') || !this.getAttribute('style').trim()) {
          this.removeAttribute('style');
        }
      }
      if (this.hasAttribute('align')) {
        this.removeAttribute('align');
      }
    });
  }

  function detectAlignmentFromContent($wrapper) {
    if (!$wrapper || !$wrapper.length) {
      return null;
    }
    var $block = $wrapper.children('h1, h2, h3, h4, h5, h6, p, div').first();
    if (!$block.length) {
      $block = $wrapper.find('h1, h2, h3, h4, h5, h6, p, div').first();
    }
    if ($block.length) {
      var fromBlock = normalizeAlignValue($block.css('text-align') || $block.attr('align'));
      if (fromBlock) {
        return fromBlock;
      }
    }
    return normalizeAlignValue($wrapper.css('text-align') || $wrapper.attr('align'));
  }

  function getTextElementAlignment($element) {
    if (!$element || !$element.length) {
      return null;
    }
    if ($element.hasClass('text-left')) {
      return 'left';
    }
    if ($element.hasClass('text-right')) {
      return 'right';
    }
    if ($element.hasClass('text-center')) {
      return 'center';
    }
    return detectAlignmentFromContent(getTextContentWrapper($element));
  }

  function syncTextElementAlignment($element, align) {
    if (!$element || !$element.length || !$element.hasClass('text')) {
      return;
    }

    align = normalizeAlignValue(align) || 'left';
    $element.removeClass('text-left text-center text-right');
    if (align === 'left') {
      $element.addClass('text-left');
    } else if (align === 'center') {
      $element.addClass('text-center');
    } else if (align === 'right') {
      $element.addClass('text-right');
    }

    var $wrapper = getTextContentWrapper($element);
    stripInlineTextAlign($wrapper);

    var $blocks = $wrapper.children('h1, h2, h3, h4, h5, h6, p, div');
    if (!$blocks.length) {
      $blocks = $wrapper.find('h1, h2, h3, h4, h5, h6, p, div');
    }
    if ($blocks.length) {
      $blocks.css('text-align', align);
    } else {
      $wrapper.css('text-align', align);
    }
  }

  function applyTextElementAlignment(align) {
    if (!selectedElement || !selectedElement.hasClass('text')) {
      return;
    }
    syncTextElementAlignment(selectedElement, align);
    markDesignDirty();
    if (typeof saveHistoryState === 'function') {
      saveHistoryState();
    }
  }

  // Event listeners for text style buttons
  $('.bold-btn').click(function(e) {
    e.preventDefault();
    if (selectedElement && selectedElement.hasClass('text')) {
      selectedElement.find('span').toggleClass('text-bold');
    }
  });
  $('.italic-btn').click(function(e) {
    e.preventDefault();
    if (selectedElement && selectedElement.hasClass('text')) {
      selectedElement.find('span').toggleClass('text-italic');
    }
  });
  $('.underline-btn').click(function(e) {
    e.preventDefault();
    if (selectedElement && selectedElement.hasClass('text')) {
      selectedElement.find('span').toggleClass('text-underline');
    }
  });
  $('.strike-btn').click(function(e) {
    e.preventDefault();
    if (selectedElement && selectedElement.hasClass('text')) {
      selectedElement.find('span').toggleClass('text-strike');
    }
  });
  $('.align-left-btn').click(function(e) {
    e.preventDefault();
    applyTextElementAlignment('left');
  });
  $('.align-center-btn').click(function(e) {
    e.preventDefault();
    applyTextElementAlignment('center');
  });
  $('.align-right-btn').click(function(e) {
    e.preventDefault();
    applyTextElementAlignment('right');
  });
  $('.font-size-up-btn').click(function(e) {
    e.preventDefault();
    if (selectedElement && selectedElement.hasClass('text')) {
      let span = selectedElement.find('span');
      let currentSize = parseInt(span.css('font-size'));
      span.css('font-size', (currentSize + 2) + 'px');
    }
  });
  $('.font-size-down-btn').click(function(e) {
    e.preventDefault();
    if (selectedElement && selectedElement.hasClass('text')) {
      let span = selectedElement.find('span');
      let currentSize = parseInt(span.css('font-size'));
      span.css('font-size', Math.max(8, currentSize - 2) + 'px');
    }
  });
  /** Intercambia width/height preservando el centro (layout = caja visible en PDF). */
  function swapElementBoxPreserveCenter(el) {
    if (!el) return;
    var w = parseFloat(el.style.width);
    var h = parseFloat(el.style.height);
    var $el = $(el);
    if (!isFinite(w) || w <= 0) w = $el.outerWidth() || 0;
    if (!isFinite(h) || h <= 0) h = $el.outerHeight() || 0;
    var left = parseFloat(el.style.left);
    var top = parseFloat(el.style.top);
    if (!isFinite(left)) left = 0;
    if (!isFinite(top)) top = 0;
    var cx = left + w / 2;
    var cy = top + h / 2;
    var nw = h;
    var nh = w;
    el.style.width = nw + 'px';
    el.style.height = nh + 'px';
    el.style.left = (cx - nw / 2) + 'px';
    el.style.top = (cy - nh / 2) + 'px';
  }

  /**
   * Activa/desactiva texto vertical.
   * Al activar: la caja pasa a ser alta (AABB real) para que el PDF coincida con el editor.
   */
  function setTextElementVertical($el, enable) {
    if (!$el || !$el.length) return;
    var el = $el[0];
    var isOn = $el.hasClass('text-vertical') || $el.attr('data-text-vertical') === '1';
    if (enable === isOn) return;

    // Pasar de horizontal↔vertical: intercambiar dimensiones del layout.
    swapElementBoxPreserveCenter(el);

    if (enable) {
      $el.addClass('text-vertical').attr('data-text-vertical', '1');
    } else {
      $el.removeClass('text-vertical').removeAttr('data-text-vertical');
    }
  }

  /** Corrige cajas verticales antiguas (anchas + transform) al cargar. */
  function normalizeVerticalTextBoxes($scope) {
    var $root = ($scope && $scope.length) ? $scope : $(document);
    $root.find('.elements.text.text-vertical, .elements.text[data-text-vertical="1"]').each(function () {
      var el = this;
      var w = parseFloat(el.style.width);
      var h = parseFloat(el.style.height);
      var $el = $(el);
      if (!isFinite(w) || w <= 0) w = $el.outerWidth() || 0;
      if (!isFinite(h) || h <= 0) h = $el.outerHeight() || 0;
      // Caja aún “apaisada”: era layout pre-rotación → convertir a alta.
      if (w > h * 1.15) {
        swapElementBoxPreserveCenter(el);
        $el.addClass('text-vertical').attr('data-text-vertical', '1');
      }
    });
  }

  $('.text-vertical-btn').click(function(e) {
    e.preventDefault();
    if (!selectedElement || !selectedElement.hasClass('text')) {
      return;
    }
    var enable = !selectedElement.hasClass('text-vertical') && selectedElement.attr('data-text-vertical') !== '1';
    setTextElementVertical(selectedElement, enable);
    $('.text-vertical-btn').toggleClass('active', enable).attr('aria-pressed', enable ? 'true' : 'false');
    markDesignDirty();
    if (typeof saveHistoryState === 'function') {
      saveHistoryState();
    }
  });

  function changePositionElement(event)
  {
    event.preventDefault();

    actualElement = $(this);

    $('#position-modal').modal('show');
  }

  var snapshot_path = null;

  // Genera y sube el thumbnail de la participación (reutilizable al cargar diseño existente o al guardar paso 2)
  // Físico y digital: capturar .format-box y recortar la matriz (sangría + ancho matriz) por la izquierda
  function generateParticipationSnapshot(onSuccess) {
    var el = document.querySelector('#step-2 .format-box');
    if (!el) { if (onSuccess) onSuccess(); return; }
    html2canvas(el).then(function(canvas) {
      // Recorte de la matriz por la izquierda (igual para físico y digital)
      var identationMm = parseIdentationMm();
      var matrixMm = parseFloat($('#matrix-box').val()) || 40;
      var boxWidthMm = 200;
      var leftStripMm = identationMm + matrixMm;
      var cropRatio = Math.min(1, Math.max(0, leftStripMm / boxWidthMm));
      var cropX = Math.floor(canvas.width * cropRatio);
      var cropW = canvas.width - cropX;
      if (cropW > 0 && cropX < canvas.width) {
        var cropped = document.createElement('canvas');
        cropped.width = cropW;
        cropped.height = canvas.height;
        var ctx = cropped.getContext('2d');
        ctx.drawImage(canvas, cropX, 0, cropW, canvas.height, 0, 0, cropW, canvas.height);
        canvas = cropped;
      }
      var imageData = canvas.toDataURL('image/png');
      var formData = new FormData();
      formData.append('design_id', {{ $set->id }});
      formData.append('snapshot', imageData);
      $.ajax({
        type: 'POST',
        url: @json($design_snapshot_url ?? route('design.saveSnapshot')),
        data: formData,
        contentType: false,
        processData: false,
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
          'X-Requested-With': 'XMLHttpRequest'
        },
        success: function (response) {
          snapshot_path = response.path;
          if (typeof onSuccess === 'function') onSuccess();
        },
        error: function () { alert('Error al guardar vista previa'); },
        complete: function() { hideDesignLoading(); }
      });
    }).catch(function() { hideDesignLoading(); if (typeof onSuccess === 'function') onSuccess(); });
  }

  $('#save-step').click(function(event) {
    performDesignSave({ continueAfterSave: false });
  });

  $('#save-continue-step').click(function(event) {
    performDesignSave({ continueAfterSave: true });
  });

  function markDesignDirty() {
    if (window.__designLocked) return;
    designDirty = true;
    updateDesignActionButtons();
    scheduleDraftPersist();
  }

  function markDesignSaved() {
    designDirty = false;
    updateDesignActionButtons();
  }

  function parseDesignApiResponse(response) {
    return response.text().then(function(text) {
      var trimmed = (text || '').trim();
      if (!trimmed) {
        return {};
      }
      try {
        return JSON.parse(trimmed);
      } catch (e) {
        var start = trimmed.indexOf('{');
        var end = trimmed.lastIndexOf('}');
        if (start >= 0 && end > start) {
          try {
            return JSON.parse(trimmed.slice(start, end + 1));
          } catch (e2) {}
        }
        return {};
      }
    });
  }

  function isDesignApiSuccess(result) {
    if (!result || typeof result !== 'object') {
      return false;
    }
    return result.success === true || result.success === 1 || result.success === '1' || result.success === 'true';
  }

  function applyDesignSaveResult(result) {
    if (result.id) {
      window.__designId = result.id;
    }
    if (result.updated_at) {
      window.__designUpdatedAt = result.updated_at;
    }
    markDesignSaved();
    persistDraftLocally();
  }

  function persistDesignToServer(options) {
    options = options || {};
    if (window.__designLocked) {
      if (options.showLoader) hideDesignLoading();
      return Promise.resolve(false);
    }

    var data;
    try {
      data = collectDesignData();
    } catch (e) {
      console.error('collectDesignData failed', e);
      if (options.showLoader) hideDesignLoading();
      if (options.reason === 'manual-save' || options.reason === 'final-save' || options.redirectOnSuccess) {
        alert('Error al guardar el diseño.');
      }
      return Promise.resolve(false);
    }

    data.save_reason = options.reason || 'manual-save';
    const saveUrl = @json($save_format_url ?? route('design.saveFormat'));
    const redirectAfterSave = @json($redirect_after_save ?? null);
    autosaveInFlight = true;

    return fetch(saveUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      },
      body: JSON.stringify(data)
    })
    .then(function(response) {
      return parseDesignApiResponse(response).then(function(result) {
        return { ok: response.ok, status: response.status, result: result };
      });
    })
    .then(function(payload) {
      var ok = payload.ok;
      var status = payload.status;
      var result = payload.result || {};

      if (isDesignApiSuccess(result)) {
        applyDesignSaveResult(result);

        if (options.redirectOnSuccess) {
          clearPersistentDraft();
          clearTransientLocalState();
          if (redirectAfterSave) {
            window.location.href = redirectAfterSave;
          } else if (result.id) {
            window.location.href = '{{ url("design/summary") }}/' + result.id;
          } else {
            window.location.href = '{{ route("design.index") }}';
          }
          return true;
        }

        if (options.reason === 'manual-save' && !options.skipSuccessAlert) {
          alert('Diseño guardado correctamente.');
        }

        if (typeof options.onSuccess === 'function') {
          try {
            options.onSuccess();
          } catch (e) {
            console.error('Post-save callback failed', e);
          }
        }
        return true;
      }

      if (status === 422 && result.code === 'SET_DESIGN_LOCKED') {
        alert(result.message || 'Diseño bloqueado por estado operativo del set.');
      } else if (options.reason === 'manual-save' || options.reason === 'final-save' || options.redirectOnSuccess) {
        console.error('Design save failed', { status: status, result: result });
        alert(result.message || 'Error al guardar el diseño.');
      }
      return false;
    })
    .catch(function(error) {
      console.error('Design save request failed', error);
      if (options.reason === 'manual-save' || options.reason === 'final-save' || options.redirectOnSuccess) {
        alert('Error al guardar el diseño.');
      }
      return false;
    })
    .finally(function() {
      autosaveInFlight = false;
      if (options.showLoader) hideDesignLoading();
    });
  }

  function setupDesignAutosave() {
    if (window.__designLocked) return;

    $(document).on('input change', '#format, #page, #rows, #cols, #orientation, #margin-up, #margin-right, #margin-left, #margin-top, #identation, #cut-lines, #matrix-box, #margin-custom, #page-rigth, #page-bottom, #guide_color, #guide_weight, #participation_number, #participation_from, #participation_to, #participation_page', markDesignDirty);
    $(document).on('input', '.elements [contenteditable="true"], .elements textarea, .elements input', markDesignDirty);
    $(document).on('click', '.add-text, .add-image, .delete-element-btn, .up-layer, .down-layer, .text-style-btn, #open-bg-modal, #remove-bg-image, #apply-bg', function() {
      setTimeout(markDesignDirty, 50);
    });

    const observerConfig = { childList: true, subtree: true, attributes: true, characterData: true };
    ['containment-wrapper2', 'containment-wrapper3', 'containment-wrapper4'].forEach(function(id) {
      const node = document.getElementById(id);
      if (!node) return;
      const observer = new MutationObserver(function() { markDesignDirty(); });
      observer.observe(node, observerConfig);
    });

    setInterval(function() {
      if (window.__designLocked) return;
      if (!designDirty || autosaveInFlight) return;
      if (step < 2) return;
      persistDesignToServer({
        reason: 'autosave',
        showLoader: false,
        redirectOnSuccess: false,
        skipSuccessAlert: true
      });
    }, autosaveIntervalMs);
  }

  setupDesignAutosave();
  window.addEventListener('beforeunload', function() {
    if (!window.__designLocked && designDirty) {
      persistDraftLocally();
    }
  });

  /**
   * Fondo SOLO dentro de márgenes morados (identation).
   * Capa absoluta inset en mm: fiable en editor, html2canvas y DomPDF (sin calc()).
   */
  /** Sangres: 0 es válido; solo default 0 si el campo está vacío o no es número. */
  function parseIdentationMm() {
    var v = parseFloat($('#identation').val());
    return Number.isFinite(v) ? v : 0;
  }

  function marginBgLayerId(stepNum) {
    if (stepNum === 2) return 'design-participation-bg';
    if (stepNum === 3) return 'design-cover-bg';
    if (stepNum === 4) return 'design-back-bg';
    return null;
  }

  function ensureMarginBgLayer(stepNum) {
    var $wrap = $('#containment-wrapper' + stepNum);
    if (!$wrap.length) return $();
    var bgId = marginBgLayerId(stepNum);
    if (!bgId) return $wrap;

    var identationMm = parseIdentationMm();
    var matrixMm = parseFloat($('#matrix-box').val()) || 40;
    var $bg = $('#' + bgId);

    if (stepNum === 4) {
      // Trasera: fondo en todo el canvas salvo la franja de matriz.
      var rightMm = matrixMm;
      if (!$bg.length) {
        var bgColor4 = $wrap.css('background-color') || '#dfdfdf';
        var bgImg4 = $wrap.css('background-image');
        $wrap.prepend(
          '<div id="design-back-bg" style="position:absolute;left:0;top:0;right:' + rightMm +
          'mm;bottom:0;z-index:0;pointer-events:none;background-size:cover;background-position:center;background-repeat:no-repeat;"></div>'
        );
        $bg = $('#design-back-bg');
        if (bgColor4 && bgColor4 !== 'rgba(0, 0, 0, 0)' && bgColor4 !== 'transparent') {
          $bg.css('background-color', bgColor4);
        }
        if (bgImg4 && bgImg4 !== 'none') $bg.css('background-image', bgImg4);
        $wrap.css({ 'background-color': '', 'background-image': 'none' });
      } else {
        $bg.css({ left: '0', top: '0', right: rightMm + 'mm', bottom: '0' });
      }
      return $bg;
    }

    // Fondo en todo el canvas (incluye sangres). Las guías moradas solo marcan zona segura.
    if (!$bg.length) {
      var bgColor = $wrap.css('background-color');
      var bgImg = $wrap.css('background-image');
      $wrap.prepend(
        '<div id="' + bgId + '" class="design-margin-bg" style="position:absolute;left:0;top:0;right:0;bottom:0;' +
        'z-index:0;pointer-events:none;background-size:cover;background-position:center;background-repeat:no-repeat;"></div>'
      );
      $bg = $('#' + bgId);
      if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)' && bgColor !== 'transparent') {
        $bg.css('background-color', bgColor);
      }
      if (bgImg && bgImg !== 'none') $bg.css('background-image', bgImg);
      $wrap.css({ 'background-color': '#ffffff', 'background-image': 'none' });
    } else {
      $bg.css({
        left: '0',
        top: '0',
        right: '0',
        bottom: '0',
        position: 'absolute',
        zIndex: 0,
        pointerEvents: 'none'
      });
      $wrap.css({ 'background-image': 'none' });
      var wrapBg = $wrap.css('background-color');
      if (wrapBg && wrapBg !== 'rgba(0, 0, 0, 0)' && wrapBg !== 'transparent' && wrapBg !== 'rgb(255, 255, 255)') {
        if (!$bg.css('background-image') || $bg.css('background-image') === 'none') {
          var layerBg = $bg.css('background-color');
          if (!layerBg || layerBg === 'rgba(0, 0, 0, 0)' || layerBg === 'transparent') {
            $bg.css('background-color', wrapBg);
          }
        }
        $wrap.css('background-color', '#ffffff');
      }
    }
    return $bg;
  }

  function syncMarginBgLayers() {
    [2, 3, 4].forEach(function (n) {
      if ($('#containment-wrapper' + n).length) ensureMarginBgLayer(n);
    });
  }

  function getBackgroundTargetEl(stepNum) {
    if (stepNum === 4) return ensureMarginBgLayer(4);
    if (stepNum === 2 || stepNum === 3) return ensureMarginBgLayer(stepNum);
    return $('#containment-wrapper' + stepNum);
  }

  function configMargins()
  {
    let identation = parseIdentationMm();
    let matrix = $('#matrix-box').val() ?? 40;
    $('.margen-izquierdo').css('left',identation+'mm')
    $('.margen-arriba').css('top',identation+'mm')
    $('.margen-derecho').css('right',identation+'mm')
    $('.margen-abajo').css('bottom',identation+'mm')
    $('.caja-matriz').css('left',identation+'mm')
    $('.caja-matriz').css('width',matrix+'mm')
    $('.caja-matriz-2').css('right',identation+'mm')
    $('.caja-matriz-2').css('width',matrix+'mm')
    syncMarginBgLayers();
    if (step >= 2 && step <= 4) {
      $('#containment-wrapper'+step+' .elements').each(function() { clampElementToMargins(this); });
    }
    updateDimensionsInfo();
  }

  // Tarea 6: rellenar información de dimensiones en pasos 2, 3, 4
  function updateDimensionsInfo() {
    var format = $('#format').val() || '';
    var page = $('#page').val() || '';
    var orientation = $('#orientation').val() || '';
    var rows = parseInt($('#rows').val(), 10) || 0;
    var cols = parseInt($('#cols').val(), 10) || 0;
    var sheetText = $('#sheet-size').text() || '';
    var ticketText = $('#ticket-size').text() || '';
    var marginTop = parseFloat($('#margin-top').val()) || 0;
    var marginUp = parseFloat($('#margin-up').val()) || 0;
    var marginLeft = parseFloat($('#margin-left').val()) || 0;
    var marginRight = parseFloat($('#margin-right').val()) || 0;
    var identation = parseIdentationMm();
    var matrix = parseFloat($('#matrix-box').val()) || 40;
    var marginCustom = parseFloat($('#margin-custom').val()) || 0;
    var pageRight = parseFloat($('#page-rigth').val()) || 0;
    var pageBottom = parseFloat($('#page-bottom').val()) || 0;
    var formatLabel = format === 'a3-h-3x2' ? 'A3 (297 x 420) apaisado 3 x 2' : (format === 'a3-h-4x2' ? 'A3 (297 x 420) apaisado 4 x 2' : (format === 'a4-v-3x1' ? 'A4 (210 x 297) vertical 3 x 1' : (format === 'a4-v-4x1' ? 'A4 (210 x 297) vertical 4 x 1' : (sheetText || 'Personalizado'))));
    var html = '<strong>Formato:</strong> ' + formatLabel + ' &nbsp;|&nbsp; <strong>Participación:</strong> ' + ticketText +
      ' &nbsp;|&nbsp; <strong>Márgenes página:</strong> sup ' + marginTop + 'mm, inf ' + marginUp + 'mm, izq ' + marginLeft + 'mm, der ' + marginRight + 'mm';
    if (marginCustom) html += ' &nbsp;|&nbsp; <strong>Sangres:</strong> ' + marginCustom + 'mm';
    html += ' &nbsp;|&nbsp; <strong>Matriz:</strong> ' + matrix + 'mm (indent. ' + identation + 'mm)';
    html += ' &nbsp;|&nbsp; <strong>Espaciado por página:</strong> horiz. ' + pageRight + 'mm, vert. ' + pageBottom + 'mm';
    $('#dimensions-info-step2, #dimensions-info-step3, #dimensions-info-step4').html(html);
  }

          $('.up-z').click(function (e) {
      e.preventDefault();
      if (!selectedElement || selectedElement.hasClass('element-critical')) return;
      let zindex = parseInt(selectedElement.css('z-index')) || 0;
      if (zindex >= 9999) return;
      selectedElement.css('z-index', zindex + 1);
  });
  $('.dw-z').click(function (e) {
      e.preventDefault();
      if (selectedElement) {
        let zindex = parseInt(selectedElement.css('z-index')) || 0;
        if (zindex > 0) selectedElement.css('z-index', zindex - 1);
      }
  });

   $('.toggle-guide').click(function (e) {
       e.preventDefault();

       let opacity = $('.guide'+step).css('opacity');

       $('.guide'+step).css('opacity', opacity == 1 ? 0 : 1);
   });
   $('.color-guide input').change(function (e) {
      e.preventDefault();

      localStorage.setItem('guide-step'+step,$(this).val());

      let opacity = $('.guide'+step).css('border-color',$(this).val());
  });

  // === INICIO BLOQUE NUEVO ===
  // Tabla de medidas de ticket para todas las combinaciones posibles
  const ticketSizes = {
    a3: {
      h: {},
      v: {}
    },
    a4: {
      h: {},
      v: {}
    }
  };
  // Medidas útiles de hoja (márgenes de 10mm por lado)
  const sheetUsable = {
    a3: { h: { width: 400, height: 277 }, v: { width: 277, height: 400 } },
    a4: { h: { width: 277, height: 190 }, v: { width: 190, height: 277 } }
  };
  // Generar todas las combinaciones
  for (const page of ['a3', 'a4']) {
    for (const orientation of ['h', 'v']) {
      const usable = sheetUsable[page][orientation];
      for (let rows = 1; rows <= 5; rows++) {
        for (let cols = 1; cols <= 5; cols++) {
          const w = (usable.width / cols).toFixed(2);
          const h = (usable.height / rows).toFixed(2);
          ticketSizes[page][orientation][`${cols}x${rows}`] = { w, h };
        }
      }
    }
  }
  // === FIN BLOQUE NUEVO ===

  // === Tarea 8: Reescalar elementos al cambiar grid (solución 1: por porcentaje) ===
  var lastTicketDimensions = { w: null, h: null };
  var pendingRescale = null;

  function repositionParticipationElementsByScale($box, oldBoxPx, newBoxPx) {
    if (!oldBoxPx || oldBoxPx.w <= 0 || oldBoxPx.h <= 0 || !newBoxPx || newBoxPx.w <= 0 || newBoxPx.h <= 0) return;
    $box.find('.elements').each(function() {
      var $el = $(this);
      var left = parseFloat($el.css('left')) || 0;
      var top = parseFloat($el.css('top')) || 0;
      var w = $el.outerWidth();
      var h = $el.outerHeight();
      var leftPct = (left / oldBoxPx.w) * 100;
      var topPct = (top / oldBoxPx.h) * 100;
      var widthPct = (w / oldBoxPx.w) * 100;
      var heightPct = (h / oldBoxPx.h) * 100;
      var newLeft = (leftPct / 100) * newBoxPx.w;
      var newTop = (topPct / 100) * newBoxPx.h;
      var newWidth = (widthPct / 100) * newBoxPx.w;
      var newHeight = (heightPct / 100) * newBoxPx.h;
      $el.css({ left: newLeft + 'px', top: newTop + 'px', width: newWidth + 'px', height: newHeight + 'px' });
    });
  }

  function applyPendingRescaleIfStep2() {
    if (typeof step === 'undefined' || step !== 2) return;
    if (!pendingRescale || $('#step-2 .format-box .elements').length === 0) return;
    var $box = $('#step-2 .format-box');
    setTimeout(function() {
      var newBoxPx = { w: $box.width(), h: $box.height() };
      if (newBoxPx.w > 0 && newBoxPx.h > 0) {
        var oldBoxPx = {
          w: pendingRescale.oldW * newBoxPx.w / pendingRescale.newW,
          h: pendingRescale.oldH * newBoxPx.h / pendingRescale.newH
        };
        repositionParticipationElementsByScale($box, oldBoxPx, newBoxPx);
      }
      pendingRescale = null;
    }, 100);
  }
  // === FIN Tarea 8 ===

  function updateTicketInfo() {
      // Definir plantillas rápidas
      const quickTemplates = {
          'a3-h-3x2': { page: 'a3', orientation: 'h', cols: 3, rows: 2, ticket: '200mm x 92mm' },
          'a3-h-4x2': { page: 'a3', orientation: 'h', cols: 4, rows: 2, ticket: '200mm x 68.88mm' },
          'a4-v-3x1': { page: 'a4', orientation: 'v', cols: 3, rows: 1, ticket: '190mm x 92mm' },
          'a4-v-4x1': { page: 'a4', orientation: 'v', cols: 4, rows: 1, ticket: '190mm x 69.38mm' }
      };
      let page = $('#page').val();
      let orientation = $('#orientation').val();
      let cols = parseInt($('#cols').val());
      let rows = parseInt($('#rows').val());
      let format = $('#format').val();

      // Medidas de hoja
      const sheetSizes = {
          'a3': { h: { width: 420, height: 297 }, v: { width: 297, height: 420 } },
          'a4': { h: { width: 297, height: 210 }, v: { width: 210, height: 297 } }
      };
      let sheet = sheetSizes[page][orientation];
      let sheetText = `${sheet.width}mm x ${sheet.height}mm`;

      // Medidas de ticket: buscar en la tabla
      let key = `${cols}x${rows}`;
      let ticketObj = ticketSizes[page][orientation][key];
      let ticketText = ticketObj ? `${ticketObj.w}mm x ${ticketObj.h}mm` : '-';

      // Casos especiales de plantillas rápidas
      if(format === 'a3-h-3x2') { ticketText = '200mm x 92mm'; }
      else if(format === 'a3-h-4x2') { ticketText = '200mm x 68.88mm'; }
      else if(format === 'a4-v-3x1') { ticketText = '190mm x 92mm'; }
      else if(format === 'a4-v-4x1') { ticketText = '190mm x 69.38mm'; }
      else if(format === 'custom') {
          // Si la selección personalizada coincide con una plantilla rápida, usar la medida fija
          for (const keyTpl in quickTemplates) {
              const tpl = quickTemplates[keyTpl];
              if (tpl.page === page && tpl.orientation === orientation && tpl.cols === cols && tpl.rows === rows) {
                  ticketText = tpl.ticket;
                  break;
              }
          }
      }

      // Cantidad de tickets
      let ticketCount = cols * rows;

      $('#sheet-size').text(sheetText);
      $('#ticket-size').text(ticketText);
      $('#ticket-count').text(ticketCount);

      // === NUEVO: Calcular y mostrar medidas reales según orientación ===
      let key__ = `${cols}x${rows}`;
      let ticketObj__ = ticketSizes[page][orientation][key__];
      let ticketW = ticketObj__ ? parseFloat(ticketObj__.w) : null;
      let ticketH = ticketObj__ ? parseFloat(ticketObj__.h) : null;
      let ticketText__ = (ticketW && ticketH) ? `${ticketW}mm x ${ticketH}mm` : '-';

      // Casos especiales de plantillas rápidas
      if(format === 'a3-h-3x2') { ticketText__ = '200mm x 92mm'; ticketW = 200; ticketH = 92; }
      else if(format === 'a3-h-4x2') { ticketText__ = '200mm x 68.88mm'; ticketW = 200; ticketH = 68.88; }
      else if(format === 'a4-v-3x1') { ticketText__ = '190mm x 92mm'; ticketW = 190; ticketH = 92; }
      else if(format === 'a4-v-4x1') { ticketText__ = '190mm x 69.38mm'; ticketW = 190; ticketH = 69.38; }
      else if(format === 'custom') {
          for (const keyTpl in quickTemplates) {
              const tpl = quickTemplates[keyTpl];
              if (tpl.page === page && tpl.orientation === orientation && tpl.cols === cols && tpl.rows === rows) {
                  ticketText__ = tpl.ticket;
                  [ticketW, ticketH] = tpl.ticket.split('x').map(v => parseFloat(v));
                  break;
              }
          }
      }

      $('#ticket-size').text(ticketText__).data('w', ticketW).data('h', ticketH);

      // Actualizar tamaño de la caja de diseño y reescalar elementos si cambió el grid (Tarea 8)
      var prevW = lastTicketDimensions.w, prevH = lastTicketDimensions.h;
      var dimensionsChanged = (prevW != null && (prevW !== ticketW || prevH !== ticketH));
      var hasElements = $('#step-2 .format-box .elements').length > 0;
      var $box = $('#step-2 .format-box');

      if (ticketW && ticketH) {
          if (dimensionsChanged && hasElements && $box.length) {
              var step2Visible = $('#step-2').hasClass('show');
              if (step2Visible) {
                  var oldBoxPx = { w: $box.width(), h: $box.height() };
                  if (oldBoxPx.w > 0 && oldBoxPx.h > 0) {
                      $('.format-box').css({width: ticketW+'mm', height: ticketH+'mm'});
                      var newBoxPx = { w: $box.width(), h: $box.height() };
                      repositionParticipationElementsByScale($box, oldBoxPx, newBoxPx);
                  } else {
                      $('.format-box').css({width: ticketW+'mm', height: ticketH+'mm'});
                      pendingRescale = { oldW: prevW, oldH: prevH, newW: ticketW, newH: ticketH };
                  }
              } else {
                  $('.format-box').css({width: ticketW+'mm', height: ticketH+'mm'});
                  pendingRescale = { oldW: prevW, oldH: prevH, newW: ticketW, newH: ticketH };
              }
          } else {
              $('.format-box').css({width: ticketW+'mm', height: ticketH+'mm'});
          }
      }
      lastTicketDimensions = { w: ticketW, h: ticketH };
      if (typeof applyDigitalFormatBoxStep2 === 'function') applyDigitalFormatBoxStep2();
      if (typeof updateDimensionsInfo === 'function') updateDimensionsInfo();
      if (typeof updatePagesPerDocumentHint === 'function') updatePagesPerDocumentHint();
  }

  function updatePagesPerDocumentHint() {
    var $hint = $('#pages-per-document-hint');
    if (!$hint.length) return;
    var rows = Math.max(1, parseInt($('#rows').val(), 10) || 1);
    var cols = Math.max(1, parseInt($('#cols').val(), 10) || 1);
    var pp = rows * cols;
    var pagesPerDoc = Math.max(1, parseInt($('#participation_page').val(), 10) || 1);
    var docsMode = $('input[name="documents"]:checked').val()
      || $('input[name="documents_mode"]:checked').val()
      || '1';
    var genMode = $('input[name="generate"]:checked').val()
      || $('input[name="generate_mode"]:checked').val()
      || '1';
    var total = 0;
    if (genMode === '2') {
      var from = Math.max(1, parseInt($('#participation_from').val(), 10) || 1);
      var to = Math.max(from, parseInt($('#participation_to').val(), 10) || from);
      total = to - from + 1;
    } else {
      total = Math.max(0, parseInt(@json((int) ($set->total_participations ?? 0)), 10) || 0);
      var toVal = parseInt($('#participation_to').val(), 10);
      if (Number.isFinite(toVal) && toVal > 0) total = toVal;
    }
    var docs = 1;
    if (String(docsMode) === '2' && total > 0 && pp > 0) {
      var ticketsPerDoc = pagesPerDoc * pp;
      docs = Math.max(1, Math.ceil(total / ticketsPerDoc));
    }
    $hint.text('(' + pp + ' participaciones por página, ' + docs + (docs === 1 ? ' documento' : ' documentos') + ')');
  }

  function ensurePortadaQrPlaceholder() {
    var $wrap = $('#containment-wrapper3');
    if (!$wrap.length) return;

    if ($wrap.find('.elements.qr').length === 0) {
      var qrMinPx = Math.ceil(9 * 96 / 25.4);
      var qrHtml = '<div class="elements element-critical qr cover-taco-qr" style="resize:both;overflow:hidden;position:absolute;bottom:50px;right:15px;width:'+qrMinPx+'px;height:'+qrMinPx+'px;min-width:'+qrMinPx+'px;min-height:'+qrMinPx+'px;background:#fff;border:2px solid #ccc;z-index:5;"><span></span></div>';
      $wrap.append(qrHtml);
    }

    ensurePortadaTacoLabelBar($wrap);
  }

  /** Asegura la barra inferior con el marcador taco_label (borradores locales antiguos pueden no tenerla). */
  function ensurePortadaTacoLabelBar($wrap) {
    $wrap = $wrap && $wrap.length ? $wrap : $('#containment-wrapper3');
    if (!$wrap.length) return;

    var labelToken = '{' + '{taco_label}' + '}';
    var $ctx = $wrap.find('.elements.context');
    var $withLabel = $ctx.filter(function() {
      var html = ($(this).html() || '');
      return html.indexOf(labelToken) !== -1 || html.indexOf('__TACO_LABEL__') !== -1;
    });
    if ($withLabel.length) {
      $withLabel.addClass('element-critical cover-taco-label');
      markCoverCriticalElements($wrap);
      return;
    }

    var $emptyBottom = $ctx.filter(function() {
      var text = $.trim($(this).find('span').first().text());
      var style = ($(this).attr('style') || '');
      return text === '' && (/bottom\s*:/i.test(style) || /inset\s*:/i.test(style));
    }).first();

    if ($emptyBottom.length) {
      $emptyBottom.addClass('element-critical cover-taco-label');
      $emptyBottom.find('span').first()
        .attr('style', 'padding: 8px; display: block; text-align: center; font-size: 12px; font-weight: 700;')
        .text(labelToken);
      markCoverCriticalElements($wrap);
      return;
    }

    $wrap.append(
      '<div class="elements element-critical context cover-taco-label" style="width: calc(100% - 60px); border-radius: 10px; height: 10%; resize: both; overflow: hidden; position: absolute; bottom: 20px; left: 0; right: 0; margin: auto; background-color: #dfdfdf; border: 2px solid #333;">'
      + '<span style="padding: 8px; display: block; text-align: center; font-size: 12px; font-weight: 700;">' + labelToken + '</span></div>'
    );
    markCoverCriticalElements($wrap);
  }

  function bindDesignToolbarActions() {
    $('.up-layer').off('click.designToolbar').on('click.designToolbar', function(e) {
      e.preventDefault();
      if (!selectedElement || !selectedElement.length) return;
      if (selectedElement.hasClass('element-critical')) return;
      var zindex = parseInt(selectedElement.css('z-index'), 10) || 0;
      if (zindex >= 9999) return;
      selectedElement.css('z-index', zindex + 1);
      markDesignDirty();
      saveHistoryState();
      updateUndoRedoButtons();
    });

    $('.down-layer').off('click.designToolbar').on('click.designToolbar', function(e) {
      e.preventDefault();
      if (!selectedElement || !selectedElement.length) return;
      var zindex = parseInt(selectedElement.css('z-index'), 10) || 0;
      if (zindex > 0) {
        selectedElement.css('z-index', zindex - 1);
        markDesignDirty();
        saveHistoryState();
        updateUndoRedoButtons();
      }
    });

    $('.delete-element-btn').off('click.designToolbar').on('click.designToolbar', function(e) {
      e.preventDefault();
      if (!selectedElement || !selectedElement.length) return;
      if (selectedElement.hasClass('element-critical')) {
        alert('Este elemento es obligatorio y no se puede eliminar.');
        return;
      }
      selectedElement.remove();
      selectedElement = null;
      $('.up-layer, .down-layer, .delete-element-btn, .text-style-btn').prop('disabled', true);
      markDesignDirty();
      saveHistoryState();
      updateUndoRedoButtons();
    });

    $(document).off('keydown.designDelete').on('keydown.designDelete', function(e) {
      if (e.key !== 'Delete' && e.key !== 'Backspace') return;
      if ($(e.target).closest('input, textarea, select, [contenteditable="true"]').length) return;
      if (!selectedElement || !selectedElement.length) return;
      if (selectedElement.hasClass('element-critical')) {
        e.preventDefault();
        alert('Este elemento es obligatorio y no se puede eliminar.');
        return;
      }
      e.preventDefault();
      selectedElement.remove();
      selectedElement = null;
      $('.up-layer, .down-layer, .delete-element-btn, .text-style-btn').prop('disabled', true);
      markDesignDirty();
      saveHistoryState();
      updateUndoRedoButtons();
    });
  }

  // Tarea 7: aplicar diseño cargado por reserva
  function applyLoadedDesign() {
    if (typeof window.__designLoad === 'undefined' || !window.__designLoad) return;
    var d = window.__designLoad;
    if (d.format != null) $('#format').val(d.format);
    if (d.page != null) $('#page').val(d.page);
    if (d.rows != null) $('#rows').val(d.rows);
    if (d.cols != null) $('#cols').val(d.cols);
    if (d.orientation != null) $('#orientation').val(d.orientation);
    if (d.margin_up != null) $('#margin-up').val(d.margin_up);
    if (d.margin_right != null) $('#margin-right').val(d.margin_right);
    if (d.margin_left != null) $('#margin-left').val(d.margin_left);
    if (d.margin_top != null) $('#margin-top').val(d.margin_top);
    if (d.identation != null) $('#identation').val(d.identation);
    if (d.cut_lines != null) $('#cut-lines').val(d.cut_lines);
    else if (d.identation != null) $('#cut-lines').val(d.identation);
    if (d.matrix_box != null) $('#matrix-box').val(d.matrix_box);
    if (d.margin_custom != null) $('#margin-custom').val(d.margin_custom);
    if (d.page_rigth != null) $('#page-rigth').val(d.page_rigth);
    if (d.page_bottom != null) $('#page-bottom').val(d.page_bottom);
    if (d.participation_html && $('#step-2 .format-box').length) {
      $('#step-2 .format-box').first().replaceWith(d.participation_html);
      enableDesignElementsResize($('#step-2 .format-box'));
      if (typeof ensureElementEditButtons === 'function') {
        ensureElementEditButtons($('#step-2 #containment-wrapper2'));
      }
      var inner = $('#step-2 #containment-wrapper2').html();
      if (inner) localStorage.setItem('step2', inner);
    }
    if (d.cover_html && $('#step-3 .format-box').length) {
      $('#step-3 .format-box').first().replaceWith(d.cover_html);
      enableDesignElementsResize($('#step-3 .format-box'));
      ensurePortadaQrPlaceholder();
      var inner3 = $('#step-3 #containment-wrapper3').html();
      if (inner3) localStorage.setItem('step3', inner3);
    }
    if (d.back_html && $('#step-4 .format-box').length) {
      $('#step-4 .format-box').first().replaceWith(d.back_html);
      enableDesignElementsResize($('#step-4 .format-box'));
      var inner4 = $('#step-4 #containment-wrapper4').html();
      if (inner4) localStorage.setItem('step4', inner4);
    }
    if (d.backgrounds && typeof d.backgrounds === 'object') {
      [2, 3, 4].forEach(function(i) {
        var step = 'step' + i;
        if (d.backgrounds[step]) {
          var color = d.backgrounds[step].color || '#dfdfdf';
          var img = (d.backgrounds[step].image != null && d.backgrounds[step].image !== '') ? d.backgrounds[step].image : '';
          localStorage.setItem('bg-step' + i, color);
          localStorage.setItem('bgimg-step' + i, img);
          var $cont = (typeof getBackgroundTargetEl === 'function')
            ? getBackgroundTargetEl(i)
            : ((i === 4) ? $('#design-back-bg') : $('#containment-wrapper' + i));
          if ($cont.length) {
            $cont.css('background-color', color);
            $cont.css('background-image', img ? 'url("' + String(img).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '")' : 'none');
            $cont.css('background-size', 'cover');
            $cont.css('background-position', 'center');
            $cont.css('background-repeat', 'no-repeat');
          }
        }
      });
    }
    updateTicketInfo();
    if (typeof updateDimensionsInfo === 'function') updateDimensionsInfo();
    configMargins();
  }

  // Llamar al cargar y al cambiar cualquier campo relevante
  $(document).ready(function() {
      bindDesignToolbarActions();

      const initDesignEditor = function() {
        applyLoadedDesign();
        updateTicketInfo();
        $('#format,#page,#rows,#cols,#orientation').off('change keyup').on('change keyup', updateTicketInfo);
        $('#margin-top,#margin-up,#margin-left,#margin-right,#identation,#cut-lines,#matrix-box,#margin-custom,#page-rigth,#page-bottom').off('change keyup').on('change keyup', function() {
          if (typeof configMargins === 'function') configMargins();
          else if (typeof updateDimensionsInfo === 'function') updateDimensionsInfo();
        });
        $('#participation_page,#participation_from,#participation_to').off('change keyup input.pagesHint').on('change keyup input.pagesHint', updatePagesPerDocumentHint);
        $('input[name="documents"],input[name="documents_mode"],input[name="generate"],input[name="generate_mode"]').off('change.pagesHint').on('change.pagesHint', updatePagesPerDocumentHint);
        updatePagesPerDocumentHint();

        if (window.__preferServerDesign) {
          markDesignSaved();
          persistDraftLocally();
        }

        if (restoredDraftStep) {
          const maxStep = isDigitalSet ? 2 : 5;
          const targetStep = Math.max(1, Math.min(maxStep, restoredDraftStep));
          step = targetStep;

          $('.form-card[id*="step-"]').addClass('d-none').removeClass('show');
          $('.form-card[id="step-'+step+'"]').removeClass('d-none fade').addClass('show');

          if (localStorage.getItem('step'+step)) {
            $('#containment-wrapper'+step).html(localStorage.getItem('step'+step));
          }
          enableDesignElementsResize($('#containment-wrapper' + step));
          if (step === 3) ensurePortadaQrPlaceholder();

          $('.form-wizard-element').removeClass('active');
          $('#bc-step-'+step).addClass('active');

          markDesignSaved();

          configMargins();
          initCanvasInteractions();
          if (typeof applyPendingRescaleIfStep2 === 'function') applyPendingRescaleIfStep2();
          if (step === 2 && typeof applyDigitalFormatBoxStep2 === 'function') applyDigitalFormatBoxStep2();
        }
      };

      if (pendingPersistentDraft && !window.__forceFreshDraft && !window.__preferServerDesign) {
        if (typeof bootstrap !== 'undefined' && document.getElementById('draft-choice-modal')) {
          const modalEl = document.getElementById('draft-choice-modal');
          const modal = new bootstrap.Modal(modalEl);
          $('#btn-draft-continue').off('click').on('click', function() {
            applyDraftSelection(true);
            modal.hide();
            initDesignEditor();
          });
          $('#btn-draft-discard').off('click').on('click', function() {
            applyDraftSelection(false);
            modal.hide();
            initDesignEditor();
          });
          modal.show();
        } else {
          const useDraft = confirm('Tenemos un borrador guardado para este set. Aceptar: continuar editando. Cancelar: empezar limpio.');
          applyDraftSelection(useDraft);
          initDesignEditor();
        }
      } else {
        initDesignEditor();
      }
  });
  // === FIN BLOQUE NUEVO ===

  // ... existing code ...
  // === FUNCIÓN PARA GUARDAR TODO EL DISEÑO ===
  function collectDesignData() {
    // Paso 1: Configuración de formato
    const format = $('#format').val();
    const page = $('#page').val();
    const rows = parseInt($('#rows').val());
    const cols = parseInt($('#cols').val());
    const orientation = $('#orientation').val();
    const margin_up = parseFloat($('#margin-up').val());
    const margin_right = parseFloat($('#margin-right').val());
    const margin_left = parseFloat($('#margin-left').val());
    const margin_top = parseFloat($('#margin-top').val());
    const identation = parseFloat($('#identation').val());
    const cut_lines = (function(){ var v = parseFloat($('#cut-lines').val()); return Number.isFinite(v) ? v : null; })();
    const matrix_box = parseFloat($('#matrix-box').val());
    const margin_custom = parseFloat($('#margin-custom').val());
    const horizontal_space = parseFloat($('#page-rigth').val());
    const vertical_space = parseFloat($('#page-bottom').val());

    const design_lottery_id = '{{ session('design_lottery_id') }}';
    const design_entity_id = '{{ session('design_entity_id') }}';

    // Paso 2, 3, 4: HTML sin resize (clon; el canvas en edición no se toca)
    enforceQrMinSize($('#step-2 .format-box'));
    enforceQrMinSize($('#step-3 .format-box'));
    if (!window.__backSkipped) {
      enforceQrMinSize($('#step-4 .format-box'));
    }
    const participation_html = getFormatBoxHtmlForSave('#step-2 .format-box');
    const cover_html = getFormatBoxHtmlForSave('#step-3 .format-box');
    const back_html = window.__backSkipped ? '' : getFormatBoxHtmlForSave('#step-4 .format-box');

    // Fondos: leer del DOM (lo que ve el usuario) para guardar siempre los valores reales
    function getBackgroundFromDom(stepNum) {
      var $el = (typeof getBackgroundTargetEl === 'function')
        ? getBackgroundTargetEl(stepNum)
        : ((stepNum === 4) ? $('#design-back-bg') : $('#containment-wrapper' + stepNum));
      if (!$el.length) return { color: '#dfdfdf', image: null };
      var color = $el.css('background-color');
      if (!color || color === 'rgba(0, 0, 0, 0)' || color === 'transparent') color = '#dfdfdf';
      if (color.indexOf('rgb') === 0) color = rgbToHex(color) || '#dfdfdf';
      var bgImage = $el.css('background-image');
      var image = null;
      if (bgImage && bgImage !== 'none') {
        var m = bgImage.match(/url\s*\(\s*['"]?([^'")]+)['"]?\s*\)/);
        if (m && m[1]) image = m[1].trim();
      }
      return { color: color, image: image };
    }
    function rgbToHex(rgb) {
      var m = rgb.match(/rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
      if (!m) return null;
      var r = ('0' + parseInt(m[1], 10).toString(16)).slice(-2);
      var g = ('0' + parseInt(m[2], 10).toString(16)).slice(-2);
      var b = ('0' + parseInt(m[3], 10).toString(16)).slice(-2);
      return '#' + r + g + b;
    }
    const backgrounds = {
      step2: getBackgroundFromDom(2),
      step3: getBackgroundFromDom(3),
      step4: getBackgroundFromDom(4)
    };

    // Paso 5: Configuración de salida
    const draw_guides = $('#guides').is(':checked');
    const guide_color = $('#guide_color').val();
    const guide_weight = parseFloat($('#guide_weight').val());
    const participations_per_book = parseInt($('#participation_number').val());
    const generate_mode = $('input[name="generate"]:checked').val();
    const participation_from = parseInt($('#participation_from').val());
    const participation_to = parseInt($('#participation_to').val());
    const documents_mode = $('input[name="documents"]:checked').val();
    const pages_per_document = parseInt($('#participation_page').val());

    return {
      set_id: {{ $set->id ?? 'null' }},
      design_id: (typeof window.__designId !== 'undefined' && window.__designId) ? window.__designId : null,
      expected_updated_at: (typeof window.__designUpdatedAt !== 'undefined' && window.__designUpdatedAt) ? window.__designUpdatedAt : null,
      format,
      page,
      rows,
      cols,
      orientation,
      margins: {
        up: margin_up,
        right: margin_right,
        left: margin_left,
        top: margin_top
      },
      identation,
      cut_lines,
      matrix_box,
      margin_custom,
      horizontal_space,
      vertical_space,
      snapshot_path,
      design_lottery_id,
      design_entity_id,
      participation_html,
      cover_html,
      back_html,
      back_skipped: !!window.__backSkipped,
      design_name: window.__pendingDesignName || (window.__designLoad && window.__designLoad.design_name) || null,
      backgrounds,
      output: {
        draw_guides,
        guide_color,
        guide_weight,
        participations_per_book,
        generate_mode,
        participation_from,
        participation_to,
        documents_mode,
        pages_per_document
      }
    };
  }

  // Asegura que todos los .elements sean redimensionables al cargar la vista
</script>

@include('design.partials.preview_pdf_button')

{{-- {{url('design/add/select')}} --}}

@endsection
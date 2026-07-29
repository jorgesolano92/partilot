@extends('layouts.layout')

@section('title','Editar Formato')

@section('content')

<link rel="stylesheet" href="{{ asset('assets/css/design-editor-ui.css') }}">
<link rel="stylesheet" href="{{ asset('assets/css/design-editor-fonts.css') }}">

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
    $useDefaultBackCanvas = !($isDigitalSet ?? false)
        && !($format->back_skipped ?? false)
        && empty(trim(strip_tags((string) ($format->back_html ?? ''))));
@endphp

<style>
    @include('design.partials.design_canvas_styles')

    input[disabled],select[disabled] {
        background-color: #cfcfcf !important;
    }
    /* Límites y borde visibles al seleccionar y redimensionar */
    .elements {
        box-sizing: border-box !important;
        min-width: 20px;
        min-height: 20px;
    }
    [id^="containment-wrapper"] .elements,
    #design-back-bg .elements {
        resize: both !important;
        overflow: hidden !important;
    }
    .elements.text {
        position: absolute;
        box-sizing: border-box !important;
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
    .qr span {
        width: 100%;
        height: 100%;
        display: block;
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
    
    /* Centrar el formato */
    .format-box {
        margin: auto !important;
        display: block;
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
    
    /* Mejorar visualización de imágenes de fondo */
    [id*="containment-wrapper"] {
        background-size: cover !important;
        /* background-size: calc(100% - 20px) calc(100% - 20px) !important; */
        background-repeat: no-repeat !important;
        background-position: center center !important;
        background-attachment: scroll !important;
        min-height: 200px;
        position: relative;
    }
    
    /* Asegurar que las imágenes de fondo se muestren correctamente */
    [id*="containment-wrapper"]:before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-size: inherit;
        background-repeat: inherit;
        background-position: inherit;
        background-image: inherit;
        z-index: -1;
        pointer-events: none;
    }
</style>

<div class="container-fluid design-editor-page">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        @if(!empty($printShopOrder))
                            <li class="breadcrumb-item"><a href="{{ route('print-shop.index') }}">Panel Imprenta</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('print-shop.orders.show', $printShopOrder->id) }}">{{ $printShopOrder->order_code }}</a></li>
                            <li class="breadcrumb-item active">Editar diseño</li>
                        @else
                            <li class="breadcrumb-item"><a href="javascript: void(0);">Diseño e Impresión</a></li>
                            <li class="breadcrumb-item active">Editar Formato</li>
                        @endif
                    </ol>
                </div>
                <h4 class="page-title">{{ !empty($printShopOrder) ? 'Editar diseño — '.$printShopOrder->order_code : 'Editar Formato' }}</h4>
            </div>
        </div>
    </div>
    <form method="POST" action="{{ $update_format_url ?? route('design.updateFormat', $format->id) }}" id="edit-format-form">
        @csrf
        @method('PUT')
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h4 class="header-title">
                            <div class="d-flex p-2" style=" align-items: center;justify-content: center;">
                                <div class="form-wizard-element active" style="width: 200px;" id="bc-step-1">
                                    <span style="top: -4px; margin-right: 8px;">1</span>
                                    <label>Configurar <br> Formato</label>
                                </div>
                                <div class="form-wizard-element" style="width: 200px;" id="bc-step-2">
                                    <span style="top: -4px; margin-right: 8px;">2</span>
                                    <label>Diseñar <br> Participación</label>
                                </div>
                                @if(!($isDigitalSet ?? false))
                                <div class="form-wizard-element" style="width: 200px;" id="bc-step-3">
                                    <span style="top: -4px; margin-right: 8px;">3</span>
                                    <label>Diseñar <br> Portada</label>
                                </div>
                                <div class="form-wizard-element" style="width: 200px;" id="bc-step-4">
                                    <span style="top: -4px; margin-right: 8px;">4</span>
                                    <label>Diseñar <br> Trasera</label>
                                </div>
                                <div class="form-wizard-element" style="width: 200px;" id="bc-step-5">
                                    <span style="top: -4px; margin-right: 8px;">5</span>
                                    <label>Configurar <br> Salida</label>
                                </div>
                                @endif
                            </div>
                        </h4>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-card fade show bs" id="step-1" style="min-height: 658px;">
                                    <h4 class="mb-0 mt-1">Configuración de Formato</h4>
                                    <small><i>Configura el formato de la página y las participaciones</i></small>
                                    <br><br>
                                    <div style="min-height: 656px;">
                                        <h4 class="mb-0 mt-1">Formato de la página</h4>
                                        <div class="row">
                                            <div class="col-9">
                                                <div class="row">
                                                    <div class="col-12">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Plantilla rápida</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <select class="form-control" name="format" id="format" style="border-radius: 30px;">
                                                                    <option value="a3-h-3x2" @if($format->format == 'a3-h-3x2') selected @endif>A3 - Apaisado - (3x2)</option>
                                                                    <option value="a3-h-4x2" @if($format->format == 'a3-h-4x2') selected @endif>A3 - Apaisado - (4x2)</option>
                                                                    <option value="a4-v-3x1" @if($format->format == 'a4-v-3x1') selected @endif>A4 - Vertical - (3x1)</option>
                                                                    <option value="a4-v-4x1" @if($format->format == 'a4-v-4x1') selected @endif>A4 - Vertical - (4x1)</option>
                                                                    <option value="custom" @if($format->format == 'custom') selected @endif>Personalizado</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Tamaño de la página</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <select class="form-control custom" name="page" id="page" style="border-radius: 30px;" @if($format->format != 'custom') disabled @endif>
                                                                    <option value="a3" @if($format->page == 'a3') selected @endif>A3 (297x420)</option>
                                                                    <option value="a4" @if($format->page == 'a4') selected @endif>A4 (210x297)</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Número de filas</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <input class="form-control custom" name="rows" value="{{ old('rows', $format->rows) }}" @if($format->format != 'custom') disabled @endif type="number" id="rows" min="1" max="5" style="border-radius: 30px;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Número de columnas</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <input class="form-control custom" name="cols" value="{{ old('cols', $format->cols) }}" @if($format->format != 'custom') disabled @endif type="number" id="cols" min="1" max="5" style="border-radius: 30px;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Orientación</label>
                                                            <div class="input-group input-group-merge group-form">
                                                                <select class="form-control custom" name="orientation" id="orientation" style="border-radius: 30px;" @if($format->format != 'custom') disabled @endif>
                                                                    <option value="h" @if($format->orientation == 'h') selected @endif>Apaisado</option>
                                                                    <option value="v" @if($format->orientation == 'v') selected @endif>Vertical</option>
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
                                                                <input class="form-control" name="margin_up" type="number" id="margin-up" value="{{ old('margin_up', $format->margins['up'] ?? $format->margin_up ?? 1) }}" step="0.1" placeholder="0,00" style="border-radius: 30px">
                                                            </div>
                                                            <div class="col-sm-2">
                                                                <input class="form-control" name="margin_right" type="number" id="margin-right" value="{{ old('margin_right', $format->margins['right'] ?? $format->margin_right ?? 1) }}" step="0.1" placeholder="0,00" style="border-radius: 30px">
                                                            </div>
                                                            <div class="col-sm-2">
                                                                <input class="form-control" name="margin_left" type="number" id="margin-left" value="{{ old('margin_left', $format->margins['left'] ?? $format->margin_left ?? 1) }}" step="0.1" placeholder="0,00" style="border-radius: 30px">
                                                            </div>
                                                            <div class="col-sm-2">
                                                                <input class="form-control" name="margin_top" type="number" id="margin-top" value="{{ old('margin_top', $format->margins['top'] ?? $format->margin_top ?? 1) }}" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <label class="col-form-label label-control col-4 text-end">Sangres de la imagen (mm)</label>
                                                            <div class="col-sm-2">
                                                                <input class="form-control" name="identation" type="number" id="identation" value="{{ old('identation', $format->identation ?? 0) }}" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <label class="col-form-label label-control col-4 text-end">Líneas de corte (mm)</label>
                                                            <div class="col-sm-2">
                                                                <input class="form-control" name="cut_lines" type="number" id="cut-lines" value="{{ old('cut_lines', $format->cut_lines ?? 2.5) }}" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <label class="col-form-label label-control col-4 text-end">Anchura de la matriz (mm)</label>
                                                            <div class="col-sm-2">
                                                                <input class="form-control" name="matrix_box" type="number" id="matrix-box" value="{{ old('matrix_box', $format->matrix_box ?? 40) }}" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <span class="d-block mt-1">(Incluyendo sangres)</span>
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <label class="col-form-label label-control col-4 text-end">Márgenes de la página (mm)</label>
                                                            <div class="col-sm-2">
                                                                <input class="form-control" id="margin-custom" name="margin_custom" type="number" value="{{ old('margin_custom', $format->margin_custom ?? 1) }}" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <label class="col-form-label label-control col-4 text-end">Espacio horizontal entre participaciones (mm)</label>
                                                            <div class="col-sm-2">
                                                                <input class="form-control" name="horizontal_space" type="number" id="page-rigth" value="{{ old('horizontal_space', $format->horizontal_space ?? 0) }}" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                            </div>
                                                        </div>
                                                        <div class="row mb-3">
                                                            <label class="col-form-label label-control col-4 text-end">Espacio vertical entre participaciones (mm)</label>
                                                            <div class="col-sm-2">
                                                                <input class="form-control" name="vertical_space" type="number" id="page-bottom" value="{{ old('vertical_space', $format->vertical_space ?? 0) }}" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                            </div>
                                                        </div>
                                                        <div class="row mt-3">
                                                            <div class="col-12 text-end">
                                                                <button type="submit" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2" id="btn-guardar-margenes">Guardar
                                                                    <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i></button>
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
                                    <strong>Set digital.</strong> Solo se diseña la participación; la venta usa imagen PNG (sin portada ni trasera).
                                </div>
                                @endif
                                <h4 class="mb-0 mt-1">Diseñar Participación</h4>
                                <small><i>Edita el diseño de la participación</i></small>
                                <br>
                                <div class="format-box-btn">
                                    <br>
                                    <div class="btn-group format-btn-group">
                                        <button type="button" class="btn btn-sm btn-secondary design-zoom-out" title="Alejar" data-step="2"><i class="ri-zoom-out-line"></i></button>
                                        <button type="button" class="btn btn-sm btn-secondary design-zoom-in" title="Acercar" data-step="2"><i class="ri-zoom-in-line"></i></button>
                                        <span class="align-self-center px-1 design-zoom-label" style="font-size: 12px;">100%</span>
                                        <button title="Agregar texto" class="btn btn-sm btn-dark add-text" data-id="2" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-edit-line"></i></button>
                                        <button title="Agregar imagen" class="btn btn-sm btn-dark add-image" data-id="2" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-image-line"></i></button>
                                        <button title="Fondo de la participación" class="btn btn-sm btn-dark" id="open-bg-modal" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-palette-line"></i></button>
                                        <button title="Solo campos obligatorios" class="btn btn-sm btn-outline-dark reset-mandatory-canvas" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-layout-grid-line"></i></button>
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
                                        @if($isDigitalSet ?? false)
                                        @php $matrixBoxMmEdit = (float)($format->matrix_box ?? 40); @endphp
                                        <div class="format-box-digital-wrap" style="width: calc(200mm - {{ $matrixBoxMmEdit }}mm); height: 92mm; margin: auto; position: relative; overflow: hidden;">
                                        @endif
                                        {!! $format->participation_html ?? '' !!}
                                        @if($isDigitalSet ?? false)
                                        </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-2 p-2 small text-muted border-top" id="dimensions-info-step2"></div>
                            </div>
                            @if(!($isDigitalSet ?? false))
                            <div class="form-card fade bs d-none" id="step-3" style="min-height: 658px;">
                                <h4 class="mb-0 mt-1">Diseñar Portada</h4>
                                <small><i>Edita el diseño de la portada</i></small>
                                <br>
                                <div class="format-box-btn">
                                    <br>
                                    <div class="btn-group format-btn-group">
                                            <button type="button" class="btn btn-sm btn-secondary design-zoom-out" title="Alejar" data-step="3"><i class="ri-zoom-out-line"></i></button>
                                            <button type="button" class="btn btn-sm btn-secondary design-zoom-in" title="Acercar" data-step="3"><i class="ri-zoom-in-line"></i></button>
                                            <span class="align-self-center px-1 design-zoom-label" style="font-size: 12px;">100%</span>
                                            <button title="Agregar texto" class="btn btn-sm btn-dark add-text" data-id="3" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-edit-line"></i></button>
                                            <button title="Agregar imagen" class="btn btn-sm btn-dark add-image" data-id="3" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-image-line"></i></button>
                                            <button title="Fondo de la participación" class="btn btn-sm btn-dark" id="open-bg-modal" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-palette-line"></i></button>
                                            <button title="Agregar barra superior" class="btn btn-sm btn-dark add-top" data-id="3" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-layout-top-line"></i></button>
                                            <button title="Agregar barra inferior" class="btn btn-sm btn-dark add-bottom" data-id="3" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-layout-bottom-line"></i></button>
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
                                        {!! $format->cover_html ?? '' !!}
                                    </div>
                                </div>
                                <div class="mt-2 p-2 small text-muted border-top" id="dimensions-info-step3"></div>
                            </div>
                                <div class="form-card fade bs d-none" id="step-4" style="min-height: 658px;">
                                    <div class="design-skip-back-banner alert py-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2" id="skip-back-banner">
                                        <span class="mb-0 skip-back-msg"><strong>¿No necesitas diseñar la trasera?</strong> Puedes omitir este paso.</span>
                                        <span class="mb-0 restore-back-msg d-none"><strong>Trasera omitida.</strong> Puedes volver a activarla para diseñar y generar PDF de traseras.</span>
                                        <button type="button" class="btn btn-dark btn-sm rounded-pill px-3" id="btn-skip-back-design"><i class="ri-skip-forward-line me-1"></i> Omitir trasera</button>
                                        <button type="button" class="btn btn-success btn-sm rounded-pill px-3 d-none" id="btn-restore-back-design"><i class="ri-arrow-go-back-line me-1"></i> Usar trasera</button>
                                    </div>
                                    <h4 class="mb-0 mt-1">Diseñar Trasera</h4>
                                    <small><i>Edita el diseño de la trasera</i></small>
                                    <br>
                                    <div class="format-box-btn">
                                        <br>
                                        <div class="btn-group format-btn-group">
                                            <button type="button" class="btn btn-sm btn-secondary design-zoom-out" title="Alejar" data-step="4"><i class="ri-zoom-out-line"></i></button>
                                            <button type="button" class="btn btn-sm btn-secondary design-zoom-in" title="Acercar" data-step="4"><i class="ri-zoom-in-line"></i></button>
                                            <span class="align-self-center px-1 design-zoom-label" style="font-size: 12px;">100%</span>
                                            <button title="Agregar texto" class="btn btn-sm btn-dark add-text" data-id="4" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-edit-line"></i></button>
                                            <button title="Agregar imagen" class="btn btn-sm btn-dark add-image" data-id="4" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-image-line"></i></button>
                                            <button title="Fondo de la participación" class="btn btn-sm btn-dark" id="open-bg-modal" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-palette-line"></i></button>
                                            <button title="Agregar barra superior" class="btn btn-sm btn-dark add-top" data-id="4" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-layout-top-line"></i></button>
                                            <button title="Agregar barra inferior" class="btn btn-sm btn-dark add-bottom" data-id="4" type="button" style="padding-left: 12px; padding-right: 12px;"><i class="ri-layout-bottom-line"></i></button>
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
                                        @if($useDefaultBackCanvas)
                                            @include('design.partials.default_back_canvas')
                                        @else
                                            {!! $format->back_html ?? '' !!}
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-2 p-2 small text-muted border-top" id="dimensions-info-step4"></div>
                            </div>
                                <div class="form-card fade bs d-none" id="step-5" style="min-height: 658px;">
                                    <h4 class="mb-0 mt-1">Configurar salida</h4>
                                    <small><i>Configura el formato de salida de las participaciones</i></small>
                                    <br><br>
                                    <div>
                                        <h4 class="mb-0 mt-1">Formato de la página</h4>
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="form-group mb-1">
                                                    <div class="form-check form-switch mt-3">
                                                        <input style="float: left;" class="form-check-input bg-dark" type="checkbox" role="switch" id="guides" name="draw_guides" @if($format->output['draw_guides'] ?? false) checked @endif>
                                                        <label style="float: left; margin-left: 50px;" class="form-check-label" for="guides"><b>Dibujar las guías de corte</b></label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="row mb-3">
                                                    <label class="col-form-label label-control col-6 text-start">Color de las guías</label>
                                                    <div class="col-sm-2">
                                                        <input class="form-control" type="color" id="guide_color" name="guide_color" value="{{ old('guide_color', $format->output['guide_color'] ?? '#000000') }}" style="border-radius: 30px">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="row mb-3">
                                                    <label class="col-form-label label-control col-6 text-start">Grosor de las guías (mm):</label>
                                                    <div class="col-sm-2">
                                                        <input class="form-control" type="number" id="guide_weight" name="guide_weight" value="{{ old('guide_weight', $format->output['guide_weight'] ?? '0.3') }}" step="0.1" placeholder="0.00" style="border-radius: 30px">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @if(!$set->digital_participations || ($set->physical_participations ?? 0) > 0)
                                        <h4 class="mb-0 mt-1">Participaciones por talonario</h4>
                                        <small><i>Elige la cantidad de participaciones por talonario</i></small>
                                        <div class="row mb-3">
                                            <label class="col-form-label label-control col-3 text-start">Cantidad de participaciónes:</label>
                                            <div class="col-sm-1">
                                                <input class="form-control" type="number" name="participations_per_book" value="{{ old('participations_per_book', $format->output['participations_per_book'] ?? 50) }}" id="participation_number" style="border-radius: 30px">
                                            </div>
                                        </div>
                                        <br>
                                        @else
                                        <p class="text-muted mb-3"><i>Set digital: una sola serie (no hay talonarios).</i></p>
                                        <input type="hidden" name="participations_per_book" id="participation_number" value="{{ $set->total_participations ?? 1 }}">
                                        @endif
                                        <h4 class="mb-0 mt-1">Participaciones a generar</h4>
                                        <div class="form-group mb-3">
                                            <div class="form-check form-switch mt-3">
                                                <input style="float: left;" class="form-check-input bg-dark" type="radio" name="generate_mode" value="1" role="switch" id="generate1" @if(($format->output['generate_mode'] ?? '1') == '1') checked @endif>
                                                <label style="float: left; margin-left: 50px;" class="form-check-label" for="generate1"><b>Generar todas las participaciones ({{ $set->total_participations ?? 0 }})</b></label>
                                            </div>
                                            <div class="form-check form-switch mt-3">
                                                <input style="float: left;" class="form-check-input bg-dark" type="radio" name="generate_mode" value="2" role="switch" id="generate" @if(($format->output['generate_mode'] ?? '1') == '2') checked @endif>
                                                <label style="float: left; margin-left: 50px;" class="form-check-label" for="generate"><b>Generar un rango de participaciones</b></label>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <label class="col-form-label label-control col-3 text-start">Generar de la participación:</label>
                                            <div class="col-sm-1">
                                                <input class="form-control" type="number" name="participation_from" value="{{ old('participation_from', $format->output['participation_from'] ?? 1) }}" id="participation_from" style="border-radius: 30px">
                                            </div>
                                            <label class="col-form-label label-control col-3 text-start">Hasta la participación:</label>
                                            <div class="col-sm-1">
                                                <input class="form-control" type="number" name="participation_to" value="{{ old('participation_to', $format->output['participation_to'] ?? $set->total_participations ?? '') }}" id="participation_to" style="border-radius: 30px">
                                            </div>
                                            <label class="col-form-label label-control col-4 text-start">(ambas incluidas)</label>
                                        </div>
                                        <br>
                                        <h4 class="mb-0 mt-1">Número de documentos</h4>
                                        <p class="text-muted small mb-2">Valores por defecto al imprimir. Al generar el PDF podrá elegir en ese momento si quiere un único documento o un ZIP (sin necesidad de reaprobar el diseño).</p>
                                        <div class="form-group mb-3">
                                            <div class="form-check form-switch mt-3">
                                                <input style="float: left;" class="form-check-input bg-dark" type="radio" name="documents_mode" value="1" role="switch" id="documents1" @if(($format->output['documents_mode'] ?? '1') == '1') checked @endif>
                                                <label style="float: left; margin-left: 50px;" class="form-check-label" for="documents1"><b>Generar un único documento</b></label>
                                            </div>
                                            <div class="form-check form-switch mt-3">
                                                <input style="float: left;" class="form-check-input bg-dark" type="radio" name="documents_mode" value="2" role="switch" id="documents" @if(($format->output['documents_mode'] ?? '1') == '2') checked @endif>
                                                <label style="float: left; margin-left: 50px;" class="form-check-label" for="documents"><b>Separar las participaciones en múltiples documentos</b></label>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <label class="col-form-label label-control col-3 text-start">Número de páginas por documento:</label>
                                            <div class="col-sm-1">
                                                <input class="form-control" type="number" name="pages_per_document" value="{{ old('pages_per_document', $format->output['pages_per_document'] ?? 150) }}" id="participation_page" min="1" style="border-radius: 30px">
                                            </div>
                                            <label class="col-form-label label-control col-8 text-start" id="pages-per-document-hint">(6 participaciones por página, 1 documento)</label>
                                        </div>
                                    </div>
                                </div>

                                @endif

                            <div class="row mt-3 mb-3">
                                  <div class="col-6 text-start">
                                      <a href="javascript:;" class="btn btn-md btn-light mt-2 prev-step design-wizard-nav-btn design-wizard-nav-btn--dark">
                                          <i class="ri-arrow-left-circle-line" aria-hidden="true"></i>
                                          <span>Atrás</span>
                                      </a>
                                  </div>
                                  <div class="col-6 text-end">
                                      <div class="d-inline-flex flex-wrap align-items-end justify-content-end gap-2">
                                      <button type="button" id="design-preview-pdf-btn" class="btn btn-md btn-light mt-2 d-none design-wizard-nav-btn design-wizard-nav-btn--dark" title="Vista previa del PDF del paso actual">
                                          <i class="ri-file-pdf-line" aria-hidden="true"></i>
                                          <span>Previsualizar PDF</span>
                                      </button>
                                      <div class="d-inline-flex flex-column align-items-end gap-1" id="design-step-actions" style="min-width: 200px;">
                                      <button type="button" id="step" class="btn btn-md btn-light mt-2 next-step design-wizard-nav-btn design-wizard-nav-btn--primary">
                                          <span>Siguiente</span>
                                          <i class="ri-arrow-right-circle-line" aria-hidden="true"></i>
                                      </button>
                                      <button type="button" id="save-step" class="btn btn-md btn-light mt-2 d-none design-wizard-nav-btn design-wizard-nav-btn--primary">
                                          <span>Guardar</span>
                                          <i class="ri-save-line" aria-hidden="true"></i>
                                      </button>
                                      <button type="button" id="save-continue-step" class="btn btn-md btn-light mt-2 d-none design-wizard-nav-btn design-wizard-nav-btn--continue">
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
    </form>
</div> <!-- container -->

<!-- Modales para edición visual -->
<div class="modal fade" id="ckeditor-modal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Editar Texto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="editor-container__editor"><div id="editor" style="height: 200px;"></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-danger deleteElements" data-bs-dismiss="modal">Eliminar elemento</button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-sm btn-primary accept-text">Aceptar</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="imagen-modal" tabindex="-1">
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
                <input class="" id="imageInput" type="file" accept="image/*">
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
<!-- Overlay de carga -->
<div id="design-loading-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center; flex-direction: column;">
  <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;"><span class="visually-hidden">Cargando...</span></div>
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
<div class="modal fade" id="background-modal" tabindex="-1" aria-labelledby="backgroundModalLabel" aria-hidden="true">
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

@endsection

@section('scripts')
<script src="{{ asset('assets/libs/html2canvas/html2canvas.min.js') }}"></script>
<script>
window.__formatBackgrounds = @json($format->backgrounds ?? []);
// --- Funciones de edición visual (copiadas de la vista original) ---
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
    return false;
}
function deleteElements(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    let element = $(this);
    if (element.hasClass('element-critical')) {
        alert('Este elemento es obligatorio y no se puede eliminar.');
        return false;
    }
    if (confirm('¿Desea eliminar el elemento seleccionado?')) {
        element.remove();
        markDesignDirty();
        $('#step-edit-next').addClass('d-none');
        saveHistoryState();
        updateUndoRedoButtons();
    }
    return false;
}
function changeImage(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const imageElement = $(this).closest('.elements.images');
    if (!imageElement.length) {
        return false;
    }
    // Guardar como objeto jQuery para mantener la referencia
    actualElement = imageElement;
    selectedElement = imageElement;
    // Guardar también en el modal usando data() para que no se pierda si actualElement se limpia
    $('#imagen-modal').data('imageElement', imageElement);
    // Seleccionar visualmente el elemento
    $('.elements').removeClass('selected');
    imageElement.addClass('selected');
    
    $('#imagen-modal').modal('show');
    return false;
}
function setQRtext(event) {
    actualElement = $(this);
    $('#qr-modal').modal('show');
}
// --- Sincronizar step con la edición ---

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
        console.log(w,h)
        {{-- $('.format-box').css({width: w+'mm', height: h+'mm'}); --}}
    }
    $('.preview-design').html(html);
});

  /** Inicializa la miniatura de participaciones con los valores actuales del formulario (sin resetear campos). Usar al cargar la pantalla de edición. */
  function initPreviewFromFormat() {
    var format = $('#format').val();
    var html = "";
    if (format == 'a3-h-3x2') {
      html = '<div class="a3"><div style="height: 72px;"></div><div style="height: 72px;"></div><div style="height: 72px;"></div><div style="height: 72px;"></div><div style="height: 72px;"></div><div style="height: 72px;"></div></div>';
    } else if (format == 'a3-h-4x2') {
      html = '<div class="a3"><div style="height: 54px;"></div><div style="height: 54px;"></div><div style="height: 54px;"></div><div style="height: 54px;"></div><div style="height: 54px;"></div><div style="height: 54px;"></div><div style="height: 54px;"></div><div style="height: 54px;"></div></div>';
    } else if (format == 'a4-v-3x1') {
      html = '<div class="a4"><div style="height: 72px;"></div><div style="height: 72px;"></div><div style="height: 72px;"></div></div>';
    } else if (format == 'a4-v-4x1') {
      html = '<div class="a4"><div style="height: 54px;"></div><div style="height: 54px;"></div><div style="height: 54px;"></div><div style="height: 54px;"></div></div>';
    } else if (format == 'custom') {
      var page = $('#page').val();
      var cls = (page == 'a4') ? 'a4' : 'a3';
      html = '<div class="' + cls + '"></div>';
      $('.preview-design').html(html);
      recalculateDesign();
      return;
    }
    $('.preview-design').html(html);
  }

  function restoreValues()
  {
    $('#page').prop('selectedIndex',0);
    $('#rows').val(3);
    $('#cols').val(2);
    $('#orientation').prop('selectedIndex',0);
  }

var step = 1;
var isDigitalSet = {{ ($isDigitalSet ?? false) ? 'true' : 'false' }};
window.__backSkipped = @json((bool) ($format->back_skipped ?? false));
window.__defaultDesignName = @json($format->design_name ?: ('Diseño ' . ($set->set_name ?? ('Set ' . $set->id)) . ' ' . now()->format('d/m/Y')));
window.__pendingDesignName = null;
var editor;
var actualElement;
var selectedElement = null;

// Reaplicar position/right/top/margin al .format-box del paso 2 en digital (el JS que actualiza width/height lo sobrescribe)
function applyDigitalFormatBoxStep2() {
  if (!isDigitalSet) return;
  var $fb = $('#step-2 .format-box');
  if (!$fb.length) return;
  var matrixMm = parseFloat($('#matrix-box').val()) || 40;
  $fb.css({ position: 'absolute', right: '0', top: '0', margin: '0' });
}

var designDirty = false;
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
    $('#step, #step-edit-next').addClass('d-none');
    $('#save-step, #save-continue-step').removeClass('d-none');
  } else {
    $('#step, #step-edit-next').removeClass('d-none');
    $('#save-step, #save-continue-step').addClass('d-none');
  }
  if (typeof window.syncDesignPreviewPdfButton === 'function') {
    window.syncDesignPreviewPdfButton();
  }
}

function markDesignDirty() {
  designDirty = true;
  updateDesignActionButtons();
}

function markDesignSaved() {
  designDirty = false;
  updateDesignActionButtons();
}

function confirmLeaveWithUnsaved(callback) {
  if (!designDirty) {
    callback();
    return;
  }
  if (confirm('Tienes cambios sin guardar en este paso. ¿Quieres continuar sin guardar?')) {
    callback();
  }
}

function stashStepHistory(stepNum) {
  if (stepNum < 2) return;
  historyByStep[stepNum] = historyStates.slice();
  historyIndexByStep[stepNum] = currentHistoryIndex;
}

function loadStepHistory(stepNum) {
  historyStates = (historyByStep[stepNum] || []).slice();
  currentHistoryIndex = typeof historyIndexByStep[stepNum] === 'number' ? historyIndexByStep[stepNum] : -1;
  updateUndoRedoButtons();
}

function ensureStepHistoryInitialized() {
  if (step < 2 || !$('#containment-wrapper' + step).length) return;
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
      '{{ asset('assets/css/design-editor-fonts.css') }}'
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

// Sistema de Undo/Redo limitado
var historyStates = [];
var currentHistoryIndex = -1;
var maxHistoryStates = 30;
var isRestoringState = false;
var resizeTimeout;

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
    // Texto vertical necesita overflow visible en CSS; no forzar hidden ahí.
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

// Zoom del diseño (pasos 2, 3, 4)
var designZoom = 1;
var designZoomSteps = [0.5, 0.75, 1, 1.25, 1.5, 2, 2.5, 3, 3.5, 4];
function applyDesignZoom() {
  var s = designZoom;
  // Aplicar zoom solo al contenedor del diseño, no a las herramientas
  $('.design-zoom-container').css('transform', 'scale(' + s + ')');
  $('.design-zoom-label').text(Math.round(s * 100) + '%');
  try { localStorage.setItem('designZoom', s); } catch (e) {}
}
try { designZoom = parseFloat(localStorage.getItem('designZoom')) || 1; } catch (e) {}
designZoom = designZoomSteps.indexOf(designZoom) >= 0 ? designZoom : 1;
applyDesignZoom();

// Funciones del sistema de Undo/Redo
function saveHistoryState() {
  if (isRestoringState) return;
  const canvasHtml = $('#containment-wrapper' + step).html();
  const canvasState = {
    html: canvasHtml,
    step: step,
    timestamp: Date.now()
  };
  if (currentHistoryIndex < historyStates.length - 1) {
    historyStates = historyStates.slice(0, currentHistoryIndex + 1);
  }
  historyStates.push(canvasState);
  currentHistoryIndex++;
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
  if (targetState.step === step) {
    $('#containment-wrapper' + step).html(targetState.html);
    reapplyElementEvents();
    currentHistoryIndex = targetIndex;
    historyByStep[step] = historyStates.slice();
    historyIndexByStep[step] = currentHistoryIndex;
    updateUndoRedoButtons();
  }
  isRestoringState = false;
}

function undo() {
  if (canUndo()) {
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

function uploadImage(file) {
  const formData = new FormData();
  formData.append('image', file);
  showDesignLoading('Subiendo imagen...');
  
  // Intentar obtener el elemento del modal primero (más confiable)
  let elementToUpdate = $('#imagen-modal').data('imageElement');
  
  // Si no está en el modal, intentar usar actualElement
  if (!elementToUpdate || !$(elementToUpdate).length) {
    elementToUpdate = actualElement;
  }
  
  // Si aún no está disponible, intentar usar selectedElement
  if (!elementToUpdate || !$(elementToUpdate).length) {
    elementToUpdate = selectedElement;
  }
  
  // Si aún no está disponible, buscar el elemento seleccionado visualmente
  if (!elementToUpdate || !$(elementToUpdate).length) {
    const selectedImages = $('.elements.images.selected');
    if (selectedImages.length) {
      elementToUpdate = selectedImages.first();
    }
  }
  
  if (!elementToUpdate || !$(elementToUpdate).length) {
    hideDesignLoading();
    alert('Error: No se encontró el elemento de imagen para actualizar. Por favor, seleccione el elemento de imagen y vuelva a intentar.');
    return;
  }
  
  // Asegurarse de que sea un objeto jQuery válido
  elementToUpdate = $(elementToUpdate);
  
  if (!elementToUpdate.length) {
    hideDesignLoading();
    alert('Error: El elemento de imagen no es válido.');
    return;
  }
  
  // Verificar que sea un elemento de imagen
  if (!elementToUpdate.hasClass('images')) {
    hideDesignLoading();
    alert('Error: El elemento seleccionado no es un elemento de imagen.');
    return;
  }
  
  // Verificar que el elemento tenga un img dentro
  const imgElement = elementToUpdate.find('img');
  if (!imgElement.length) {
    hideDesignLoading();
    alert('Error: No se encontró el elemento img dentro del contenedor.');
    return;
  }
  
  fetch(@json(route('design.uploadImage')), {
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
      imgElement.attr('src', data.url);
      hideDesignLoading(); // Ocultar loader después de actualizar la imagen
      // Limpiar el data del modal después de usar
      $('#imagen-modal').removeData('imageElement');
      $('#imagen-modal').modal('hide');
      const input = document.getElementById('imageInput');
      if (input) input.value = null;
      markDesignDirty();
      $('#step-edit-next').addClass('d-none');
      saveHistoryState();
      updateUndoRedoButtons();
      // Re-vincular eventos después de cambiar imagen
      reapplyElementEvents();
    } else {
      hideDesignLoading();
      alert('Error: No se recibió la URL de la imagen del servidor.');
    }
  })
  .catch(error => {
    hideDesignLoading();
    alert('Error al subir la imagen. Por favor, intente nuevamente.');
  });
}

function showStep(newStep) {
    $('.form-card[id*="step-"]').addClass('d-none').removeClass('show');
    $(`#step-${newStep}`).removeClass('d-none fade').addClass('show');
    $('.form-wizard-element').removeClass('active');
    $(`#bc-step-${newStep}`).addClass('active');
    if (newStep === 5) {
        {{-- paso final --}}
    } else {
        updateDesignActionButtons();
    }
    // Aplicar zoom al cambiar de paso
    if (typeof applyDesignZoom === 'function') {
        applyDesignZoom();
    }
    if (newStep === 2 && typeof applyDigitalFormatBoxStep2 === 'function') {
        applyDigitalFormatBoxStep2();
    }
    // Tarea 8: aplicar reescalado pendiente al entrar en paso 2 (participación)
    if (newStep === 2 && pendingRescale && $('#step-2 .format-box .elements').length > 0) {
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
}

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

function setTextElementVertical($el, enable) {
  if (!$el || !$el.length) return;
  var el = $el[0];
  var isOn = $el.hasClass('text-vertical') || $el.attr('data-text-vertical') === '1';
  if (enable === isOn) return;
  swapElementBoxPreserveCenter(el);
  if (enable) {
    $el.addClass('text-vertical').attr('data-text-vertical', '1');
  } else {
    $el.removeClass('text-vertical').removeAttr('data-text-vertical');
  }
}

function normalizeVerticalTextBoxes($scope) {
  var $root = ($scope && $scope.length) ? $scope : $(document);
  $root.find('.elements.text.text-vertical, .elements.text[data-text-vertical="1"]').each(function () {
    var el = this;
    var w = parseFloat(el.style.width);
    var h = parseFloat(el.style.height);
    var $el = $(el);
    if (!isFinite(w) || w <= 0) w = $el.outerWidth() || 0;
    if (!isFinite(h) || h <= 0) h = $el.outerHeight() || 0;
    if (w > h * 1.15) {
      swapElementBoxPreserveCenter(el);
      $el.addClass('text-vertical').attr('data-text-vertical', '1');
    }
  });
}

function selectDesignElement($el) {
    if (!$el || !$el.length) return;
    $el = $($el).closest('.elements');
    if (!$el.length) return;
    $('.elements').removeClass('selected');
    $el.addClass('selected');
    selectedElement = $el;
    actualElement = $el;
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

function addEventsElement() {
    $(document).off('mousedown.designSelect', '.elements');
    $(document).on('mousedown.designSelect', '.elements', function(e) {
      if (e.which !== 1) return;
      if ($(e.target).closest('.edit-btn').length) return;
      selectDesignElement($(this));
    });
    $(document).off('contextmenu.designElement', '.elements');
    $(document).on('contextmenu.designElement', '.elements', changePositionElement);
    
    // Deseleccionar al hacer clic fuera (pero no si el modal está abierto)
    $('body').off('click.deselect').on('click.deselect', function(e) {
      if ($('#imagen-modal').hasClass('show') || $('#ckeditor-modal').hasClass('show') || $('#qr-modal').hasClass('show') || $('#position-modal').hasClass('show') || $('#bar-options-modal').hasClass('show')) {
        return;
      }
      if (!$(e.target).closest('.elements').length && !$(e.target).closest('.up-layer, .down-layer, .text-style-btn, .delete-element-btn, .undo-btn, #bar-options-modal').length) {
        $('.elements').removeClass('selected');
        selectedElement = null;
        if (!$('#imagen-modal').data('imageElement')) {
          actualElement = null;
        }
        $('.up-layer, .down-layer, .text-style-btn, .delete-element-btn').prop('disabled', true);
      }
    });
}

function rgbToHex(rgb) {
  if (!rgb) return '#dfdfdf';
  if (typeof rgb === 'string' && rgb.charAt(0) === '#') return rgb;
  var m = rgb.match(/^rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/);
  if (m) return '#' + [1,2,3].map(function(x) { return ('0'+parseInt(m[x],10).toString(16)).slice(-2); }).join('');
  return '#dfdfdf';
}

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

function changePositionElement(event) {
    event.preventDefault();
    actualElement = $(this);
    $('#position-modal').modal('show');
}

{{-- $('#save-step').click(function(event) {

    if (step != 1) {

      
      let html = $('#containment-wrapper'+step).html();

      localStorage.setItem('step'+step,html);

      $('#step').removeClass('d-none');
      $('#save-step').addClass('d-none');

    }
}); --}}

var snapshot_path = null;

function parseDesignApiResponse(response) {
  return response.text().then(function(text) {
    var trimmed = (text || '').trim();
    if (!trimmed) return {};
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
  if (!result || typeof result !== 'object') return false;
  return result.success === true || result.success === 1 || result.success === '1' || result.success === 'true';
}

function submitEditFormatToServer(options) {
  options = options || {};
  var data = collectDesignData();
  if (options.fromStep5) {
    data.from_step_5 = true;
  }
  var loadingMsg = (typeof step !== 'undefined' && step === 1) ? 'Guardando márgenes...' : 'Guardando diseño...';
  showDesignLoading(loadingMsg);

  return fetch($('#edit-format-form').attr('action'), {
    method: 'PUT',
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
    var result = payload.result || {};
    if (!isDesignApiSuccess(result)) {
      console.error('Edit design save failed', payload);
      alert(result.message || 'Error al guardar el diseño.');
      return false;
    }

    markDesignSaved();

    if (options.fromStep5 && result.redirect) {
      window.location.href = result.redirect;
      return true;
    }

    var msg = (typeof step !== 'undefined' && step === 1) ? 'Márgenes aplicados correctamente.' : 'Diseño guardado correctamente.';
    if (!options.skipSuccessAlert) {
      alert(msg);
    }

    if (typeof options.onSuccess === 'function') {
      try {
        options.onSuccess();
      } catch (e) {
        console.error('Post-save callback failed', e);
      }
    }
    return true;
  })
  .catch(function(error) {
    console.error('Edit design save request failed', error);
    alert('Error al guardar el diseño.');
    return false;
  })
  .finally(function() {
    hideDesignLoading();
  });
}

function performLocalStepSave(options) {
  options = options || {};
  if (step == 1) {
    submitEditFormatToServer(options);
    return;
  }

  syncCurrentStepToLocalStorage();

  var finishSave = function() {
    syncCurrentStepToLocalStorage();
    submitEditFormatToServer($.extend({}, options, {
      skipSuccessAlert: typeof options.onSuccess === 'function'
    }));
  };

  if (step == 2) {
    html2canvas(document.querySelector('#step-2 .format-box')).then(function(canvas) {
      var isDigitalSetLocal = {{ ($set->digital_participations > 0 && (int)($set->physical_participations ?? 0) === 0) ? 'true' : 'false' }};
      if (!isDigitalSetLocal) {
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
      }
      var formData = new FormData();
      formData.append('design_id', {{ $set->id }});
      formData.append('snapshot', canvas.toDataURL('image/png'));
      $.ajax({
        type: 'POST',
        url: @json(route('design.saveSnapshot')),
        data: formData,
        contentType: false,
        processData: false,
        headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
          'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(response) {
          snapshot_path = response.path;
          finishSave();
        },
        error: function() {
          alert('Error al guardar snapshot');
        }
      });
    });
    return;
  }

  finishSave();
}

$('#save-step').click(function(event) {
  performLocalStepSave();
});

$('#save-continue-step').click(function(event) {
  performLocalStepSave({
    onSuccess: function() {
      $('.next-step').first().trigger('click');
    }
  });
});

/**/

function getMarginBoundsPx() {
    var $box = $('#step-' + step + ' .format-box');
    if (!$box.length) return null;
    var r = $box[0].getBoundingClientRect();
    var boxW = r.width, boxH = r.height;
    var ticketW = parseFloat($('#ticket-size').data('w')) || 200;
    var ticketH = parseFloat($('#ticket-size').data('h')) || 92;
    var scaleX = boxW / ticketW, scaleY = boxH / ticketH;
    var identation = parseIdentationMm();
    var matrix = parseFloat($('#matrix-box').val()) || 40;
    var minLeft = identation * scaleX;
    var minTop = identation * scaleY;
    var maxBottom = boxH - identation * scaleY;
    var maxRight = (step === 4) ? (boxW - (identation + matrix) * scaleX) : (boxW - identation * scaleX);
    return { minLeft: minLeft, minTop: minTop, maxRight: maxRight, maxBottom: maxBottom };
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
      var rightMm = identationMm + matrixMm;
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

    if (!$bg.length) {
      var bgColor = $wrap.css('background-color');
      var bgImg = $wrap.css('background-image');
      $wrap.prepend(
        '<div id="' + bgId + '" class="design-margin-bg" style="position:absolute;left:' + identationMm +
        'mm;top:' + identationMm + 'mm;right:' + identationMm + 'mm;bottom:' + identationMm +
        'mm;z-index:0;pointer-events:none;background-size:cover;background-position:center;background-repeat:no-repeat;"></div>'
      );
      $bg = $('#' + bgId);
      if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)' && bgColor !== 'transparent') {
        $bg.css('background-color', bgColor);
      }
      if (bgImg && bgImg !== 'none') $bg.css('background-image', bgImg);
      $wrap.css({ 'background-color': '#ffffff', 'background-image': 'none' });
    } else {
      $bg.css({
        left: identationMm + 'mm',
        top: identationMm + 'mm',
        right: identationMm + 'mm',
        bottom: identationMm + 'mm',
        position: 'absolute',
        zIndex: 0,
        pointerEvents: 'none'
      });
      $wrap.css({ 'background-image': 'none' });
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
    if (typeof updateDimensionsInfo === 'function') updateDimensionsInfo();
  }

  function updateDimensionsInfo() {
    var format = $('#format').val() || '';
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
      let zindex = $(actualElement).css('z-index');
      zindex = parseInt(zindex)+1;
      $(actualElement).css('z-index',zindex);
  });
  $('.dw-z').click(function (e) {
      e.preventDefault();
      let zindex = $(actualElement).css('z-index');
      zindex = parseInt(zindex)-1;
      $(actualElement).css('z-index',zindex);
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
    var docsMode = $('input[name="documents_mode"]:checked').val()
      || $('input[name="documents"]:checked').val()
      || '1';
    var genMode = $('input[name="generate_mode"]:checked').val()
      || $('input[name="generate"]:checked').val()
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

  // Llamar al cargar y al cambiar cualquier campo relevante
  $(document).ready(function() {
      updateTicketInfo();
      if (typeof updateDimensionsInfo === 'function') updateDimensionsInfo();
      if (typeof initPreviewFromFormat === 'function') initPreviewFromFormat();
      $('#format,#page,#rows,#cols,#orientation').on('change keyup', updateTicketInfo);
      $('#margin-top,#margin-up,#margin-left,#margin-right,#identation,#cut-lines,#matrix-box,#margin-custom,#page-rigth,#page-bottom').on('change keyup', function() {
        if (typeof configMargins === 'function') configMargins();
        else if (typeof updateDimensionsInfo === 'function') updateDimensionsInfo();
      });
      $('#participation_page,#participation_from,#participation_to').on('change keyup input', updatePagesPerDocumentHint);
      $('input[name="documents_mode"],input[name="documents"],input[name="generate_mode"],input[name="generate"]').on('change', updatePagesPerDocumentHint);
      updatePagesPerDocumentHint();
  });
  // === FIN BLOQUE NUEVO ===

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
  const generate_mode = $('input[name="generate_mode"]:checked').val();
  const participation_from = parseInt($('#participation_from').val());
  const participation_to = parseInt($('#participation_to').val());
  const documents_mode = $('input[name="documents_mode"]:checked').val();
  const pages_per_document = parseInt($('#participation_page').val());

  return {
    set_id: {{ $set->id ?? 'null' }},
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
    participation_html,
    snapshot_path,
    cover_html,
    back_html,
    back_skipped: !!window.__backSkipped,
    design_name: window.__pendingDesignName || window.__defaultDesignName || null,
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

function showDesignLoading(msg) {
  $('#design-loading-text').text(msg || 'Procesando...');
  $('#design-loading-overlay').css('display', 'flex').show();
}
function hideDesignLoading() {
  $('#design-loading-overlay').hide();
}

// --- Enviar datos al backend al guardar ---
$('#edit-format-form').on('submit', function(e) {
  e.preventDefault();
  var isFromStep5 = $(this).data('from-step-5') === true;
  $(this).data('from-step-5', false);
  submitEditFormatToServer({
    fromStep5: isFromStep5
  });
});

$(document).ready(function() {
    if (window.__formatBackgrounds && typeof window.__formatBackgrounds === 'object') {
      [2, 3, 4].forEach(function(i) {
        var stepKey = 'step' + i;
        if (window.__formatBackgrounds[stepKey]) {
          var color = window.__formatBackgrounds[stepKey].color || '#dfdfdf';
          var img = (window.__formatBackgrounds[stepKey].image != null && window.__formatBackgrounds[stepKey].image !== '') ? window.__formatBackgrounds[stepKey].image : '';
          localStorage.setItem('bg-step' + i, color);
          localStorage.setItem('bgimg-step' + i, img);
        }
      });
    }
    showStep(step);
    if (typeof configMargins === 'function') configMargins();
    loadExistingBackgrounds();
    // Vincular eventos inicialmente cuando se carga el contenido HTML
    setTimeout(function() {
        reapplyElementEvents();
        // Guardar estado inicial del paso
        if ($('#containment-wrapper'+step).length) {
            setTimeout(() => {
                saveHistoryState();
                updateUndoRedoButtons();
            }, 100);
        }
    }, 500);
    
    // --- Botones navegación ---
    $('.next-step').attr('type', 'button');
    $('.prev-step').attr('type', 'button');
    $('#step').attr('type', 'button');
    {{-- $('#save-step').attr('type', 'submit'); --}}
    $('.next-step').click(function(e) {
        e.preventDefault();
        if (step === 2 && isDigitalSet) {
            $('#edit-format-form').submit();
            return;
        }
        if (step < 5) {
            syncCurrentStepToLocalStorage();
            stashStepHistory(step);
            step++;
            loadStepHistory(step);
            showStep(step);
            setTimeout(function() {
                reapplyElementEvents();
                if ($('#containment-wrapper'+step).length) {
                    setTimeout(function() {
                        ensureStepHistoryInitialized();
                        updateUndoRedoButtons();
                    }, 100);
                }
            }, 100);

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
                    }

                    $('[id*="containment-wrapper"]').parent().css({
                        width: w+'mm',
                        height: h+'mm'
                    });
                }
                let matrix = $('#matrix-box').val() ?? 40;
                $('#containment-wrapper4').css('padding-right', matrix+'mm');
            }
        }else{
            var name = prompt('Nombre del diseño:', window.__defaultDesignName || '');
            if (name === null) return;
            window.__pendingDesignName = (name || '').trim() || window.__defaultDesignName;
            $('#edit-format-form').data('from-step-5', true);
            $('#edit-format-form').submit();
        }
    });
    $('.prev-step').click(function(e) {
        e.preventDefault();
        var navigateBack = function() {
            if (step > 1) {
                syncCurrentStepToLocalStorage();
                stashStepHistory(step);
                step--;
                loadStepHistory(step);
                showStep(step);
                setTimeout(function() {
                    reapplyElementEvents();
                    if ($('#containment-wrapper'+step).length) {
                        setTimeout(function() {
                            ensureStepHistoryInitialized();
                            updateUndoRedoButtons();
                        }, 100);
                    }
                }, 100);
            } else {
                window.open('{{url('design')}}','_self');
            }
        };
        confirmLeaveWithUnsaved(navigateBack);
    });
    $('.form-wizard-element').click(function() {
        const id = $(this).attr('id');
        const newStep = parseInt(id.replace('bc-step-', ''));
        step = newStep;
        showStep(step);
        setTimeout(function() {
            reapplyElementEvents();
            if ($('#containment-wrapper'+step).length) {
                setTimeout(() => {
                    saveHistoryState();
                    updateUndoRedoButtons();
                }, 100);
            }
        }, 100);
    });
    
    // Botón Desplegar/Ocultar márgenes
    $('#marginsCollapse').on('show.bs.collapse', function() {
        $('#btn-desplegar-margenes').text('Ocultar');
    }).on('hide.bs.collapse', function() {
        $('#btn-desplegar-margenes').text('Desplegar');
    });
    
    // Controles de zoom
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
    
    // --- Botones de agregar elementos ---
    $('.add-text').off('click').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $box = $('#step-' + step + ' .format-box');
        var boxW = $box.length ? $box.width() : 400;
        var boxH = $box.length ? $box.height() : 300;
        var left = Math.max(20, Math.round((boxW - 220) / 2));
        var top = Math.max(20, Math.round((boxH - 100) / 2));
        var $newEl = $(`<div class="elements text text-placeholder-new selected" style="padding: 12px; width: 220px; height: 100px; resize: both; overflow: hidden; position: absolute; top: ${top}px; left: ${left}px; z-index: 5000;"><button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button><span><strong>Escribe aquí...</strong></span></div>`);
        $('#containment-wrapper'+step).append($newEl);
        $('.elements').removeClass('selected');
        selectedElement = $newEl;
        $('.up-layer, .down-layer, .delete-element-btn').prop('disabled', false);
        $('.text-style-btn').prop('disabled', false);
        reapplyElementEvents();
        markDesignDirty();
        saveHistoryState();
        updateUndoRedoButtons();
        return false;
    });
    $('.reset-mandatory-canvas').off('click').on('click', function (e) {
        e.preventDefault();
        if (!confirm('¿Eliminar todos los elementos opcionales y dejar solo los campos obligatorios?')) return;
        $('#containment-wrapper' + step + ' .elements').not('.element-critical').remove();
        reapplyElementEvents();
        markDesignDirty();
        saveHistoryState();
        updateUndoRedoButtons();
    });
    function syncSkipBackBannerUi() {
        var skipped = !!window.__backSkipped;
        $('#skip-back-banner').removeClass('d-none');
        $('#skip-back-banner .skip-back-msg').toggleClass('d-none', skipped);
        $('#skip-back-banner .restore-back-msg').toggleClass('d-none', !skipped);
        $('#btn-skip-back-design').toggleClass('d-none', skipped);
        $('#btn-restore-back-design').toggleClass('d-none', !skipped);
    }

    $('#btn-skip-back-design').off('click').on('click', function (e) {
        e.preventDefault();
        if (!confirm('¿Omitir el diseño de trasera? No podrá descargar PDF de traseras.')) return;
        window.__backSkipped = true;
        $('#containment-wrapper4 .elements').remove();
        syncSkipBackBannerUi();
        markDesignDirty();
        step = 5;
        showStep(step);
        reapplyElementEvents();
    });

    $('#btn-restore-back-design').off('click').on('click', function (e) {
        e.preventDefault();
        window.__backSkipped = false;
        if (typeof ensureMarginBgLayer === 'function') {
            ensureMarginBgLayer(4);
        }
        syncSkipBackBannerUi();
        markDesignDirty();
    });

    if (window.__backSkipped) {
        syncSkipBackBannerUi();
    }
    $('.add-image').off('click').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const newImageElement = $(`<div class="elements images" style="resize: both; overflow: hidden; position: absolute; top: 0"><span><img style="width: 100%; height: 100%" src="{{url('default.jpg')}}" alt=""></span><button class="edit-btn" title="Cambiar imagen"><i class="ri-image-line"></i></button></div>`);
        $('#containment-wrapper'+step).append(newImageElement);
        
        // Establecer actualElement y selectedElement para la nueva imagen
        actualElement = newImageElement;
        selectedElement = newImageElement;
        $('.elements').removeClass('selected');
        newImageElement.addClass('selected');
        
        reapplyElementEvents();
        saveHistoryState();
        updateUndoRedoButtons();
        return false;
    });
    $('.add-top').off('click').on('click', function (e) {
        e.preventDefault();
        $('#containment-wrapper'+step).append(`<div class="elements context" style="width: calc(100% - 60px); border-radius: 10px; height: 10%; resize: both; overflow: hidden; position: absolute; top: 20px; left: 0; right: 0; margin: auto; background-color: #dfdfdf; border: 2px solid #333;"><span style="padding: 20px; display: block;"></span></div>`);
        reapplyElementEvents();
        saveHistoryState();
        updateUndoRedoButtons();
    });
    $('.add-bottom').off('click').on('click', function (e) {
        e.preventDefault();
        $('#containment-wrapper'+step).append(`<div class="elements context" style="width: calc(100% - 60px); border-radius: 10px; height: 10%; resize: both; overflow: hidden; position: absolute; bottom: 20px; left: 0; right: 0; margin: auto; background-color: #dfdfdf; border: 2px solid #333;"><span style="padding: 8px; display: block; text-align: center; font-size: 12px; font-weight: 700;">@{{taco_label}}</span></div>`);
        reapplyElementEvents();
        saveHistoryState();
        updateUndoRedoButtons();
    });
    // --- Botón de fondo ---
    $(document).on('click', '#open-bg-modal', function() {
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
    // --- Botón toggle-guide ---
    $('.toggle-guide').off('click').on('click', function (e) {
        e.preventDefault();
        let opacity = $('.guide'+step).css('opacity');
        $('.guide'+step).css('opacity', opacity == 1 ? 0 : 1);
    });
    // --- Botón color-guide ---
    $('.color-guide input').off('change').on('change', function (e) {
        e.preventDefault();
        localStorage.setItem('guide-step'+step,$(this).val());
        $('.guide'+step).css('border-color',$(this).val());
    });
    reapplyElementEvents();
    
    // Las funciones de undo/redo están definidas fuera de este bloque
    
    // Botones Undo/Redo
    $('.undo-btn').off('click').on('click', function(e) {
      e.preventDefault();
      undo();
    });
    
    $('.redo-btn').off('click').on('click', function(e) {
      e.preventDefault();
      redo();
    });
    
    // Botones de capas (up-layer, down-layer)
    $('.up-layer').off('click').on('click', function(e) {
      e.preventDefault();
      if (selectedElement) {
        if (selectedElement.hasClass('element-critical')) return;
        let zindex = parseInt(selectedElement.css('z-index')) || 0;
        if (zindex >= 9999) return;
        selectedElement.css('z-index', zindex + 1);
      }
    });
    
    $('.down-layer').off('click').on('click', function(e) {
      e.preventDefault();
      if (selectedElement) {
        let zindex = parseInt(selectedElement.css('z-index')) || 0;
        if (zindex > 0) selectedElement.css('z-index', zindex - 1);
      }
    });
    
    // Botón eliminar elemento
    $('.delete-element-btn').off('click').on('click', function(e) {
      e.preventDefault();
      if (selectedElement) {
        if (selectedElement.hasClass('element-critical')) {
          alert('Este elemento es obligatorio y no se puede eliminar.');
          return;
        }
        selectedElement.remove();
        selectedElement = null;
        $('.up-layer, .down-layer, .delete-element-btn, .text-style-btn').prop('disabled', true);
        markDesignDirty();
        $('#step-edit-next').addClass('d-none');
        saveHistoryState();
        updateUndoRedoButtons();
      }
    });
    
    // Botones de estilo de texto
    $('.bold-btn').off('click').on('click', function(e) {
      e.preventDefault();
      if (selectedElement && selectedElement.hasClass('text')) {
        selectedElement.find('span').toggleClass('text-bold');
      }
    });
    
    $('.italic-btn').off('click').on('click', function(e) {
      e.preventDefault();
      if (selectedElement && selectedElement.hasClass('text')) {
        selectedElement.find('span').toggleClass('text-italic');
      }
    });
    
    $('.underline-btn').off('click').on('click', function(e) {
      e.preventDefault();
      if (selectedElement && selectedElement.hasClass('text')) {
        selectedElement.find('span').toggleClass('text-underline');
      }
    });
    
    $('.strike-btn').off('click').on('click', function(e) {
      e.preventDefault();
      if (selectedElement && selectedElement.hasClass('text')) {
        selectedElement.find('span').toggleClass('text-strike');
      }
    });
    
    function applyTextElementAlignment(align) {
      if (!selectedElement || !selectedElement.hasClass('text')) {
        return;
      }
      syncTextElementAlignment(selectedElement, align);
      if (typeof markDesignDirty === 'function') {
        markDesignDirty();
      }
      if (typeof saveHistoryState === 'function') {
        saveHistoryState();
      }
    }

    $('.align-left-btn').off('click').on('click', function(e) {
      e.preventDefault();
      applyTextElementAlignment('left');
    });
    
    $('.align-center-btn').off('click').on('click', function(e) {
      e.preventDefault();
      applyTextElementAlignment('center');
    });
    
    $('.align-right-btn').off('click').on('click', function(e) {
      e.preventDefault();
      applyTextElementAlignment('right');
    });
    
    $('.font-size-up-btn').off('click').on('click', function(e) {
      e.preventDefault();
      if (selectedElement && selectedElement.hasClass('text')) {
        let span = selectedElement.find('span');
        let currentSize = parseInt(span.css('font-size')) || 14;
        span.css('font-size', (currentSize + 2) + 'px');
      }
    });
    
    $('.font-size-down-btn').off('click').on('click', function(e) {
      e.preventDefault();
      if (selectedElement && selectedElement.hasClass('text')) {
        let span = selectedElement.find('span');
        let currentSize = parseInt(span.css('font-size')) || 14;
        span.css('font-size', Math.max(8, currentSize - 2) + 'px');
      }
    });

    $('.text-vertical-btn').off('click').on('click', function(e) {
      e.preventDefault();
      if (!selectedElement || !selectedElement.hasClass('text')) {
        return;
      }
      var enable = !selectedElement.hasClass('text-vertical') && selectedElement.attr('data-text-vertical') !== '1';
      if (typeof setTextElementVertical === 'function') {
        setTextElementVertical(selectedElement, enable);
      } else {
        if (enable) {
          selectedElement.addClass('text-vertical').attr('data-text-vertical', '1');
        } else {
          selectedElement.removeClass('text-vertical').removeAttr('data-text-vertical');
        }
      }
      $('.text-vertical-btn').toggleClass('active', enable).attr('aria-pressed', enable ? 'true' : 'false');
      if (typeof markDesignDirty === 'function') {
        markDesignDirty();
      }
      if (typeof saveHistoryState === 'function') {
        saveHistoryState();
      }
    });
    
    // --- Subir/bajar z-index (mantener compatibilidad con actualElement) ---
    $('.up-z').off('click').on('click', function (e) {
        e.preventDefault();
        let zindex = $(actualElement).css('z-index') || 1;
        zindex = parseInt(zindex)+1;
        $(actualElement).css('z-index',zindex);
    });
    $('.dw-z').off('click').on('click', function (e) {
        e.preventDefault();
        let zindex = $(actualElement).css('z-index') || 1;
        zindex = parseInt(zindex)-1;
        $(actualElement).css('z-index',zindex);
    });
    // --- Eventos y funciones de guardado visual (copiados de format.blade.php) ---
    $('.deleteElements').off('click').on('click', function (e) {
        e.preventDefault();
        if (actualElement && actualElement.hasClass('element-critical')) {
            alert('Este elemento es obligatorio y no se puede eliminar.');
            return;
        }
        if (confirm('¿Desea eliminar el elemento seleccionado?')) {
            if (actualElement) actualElement.remove();
            markDesignDirty();
            $('#step-edit-next').addClass('d-none');
        }
    });
    // Suprimir / Backspace: eliminar elemento seleccionado (salvo en inputs)
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
        if (confirm('¿Desea eliminar el elemento seleccionado?')) {
            selectedElement.remove();
            selectedElement = null;
            actualElement = null;
            $('.up-layer, .down-layer, .delete-element-btn, .text-style-btn').prop('disabled', true);
            markDesignDirty();
            $('#step-edit-next').addClass('d-none');
            saveHistoryState();
            updateUndoRedoButtons();
        }
    });
    $('.accept-text').off('click').on('click', function(event) {
        if (editor && CKEDITOR.instances['editor']) {
            var data = CKEDITOR.instances['editor'].getData();
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
        $('#step-edit-next').addClass('d-none');
        saveHistoryState();
        updateUndoRedoButtons();
        // Re-vincular eventos después de editar
        reapplyElementEvents();
    });
    // Limpiar el data del modal cuando se cierra sin subir imagen
    $('#imagen-modal').on('hidden.bs.modal', function() {
        // Solo limpiar si no se subió una imagen (el data se limpia en uploadImage cuando se completa)
        if ($(this).data('imageElement')) {
            $(this).removeData('imageElement');
        }
    });
    
    // Handler para aceptar imagen usando delegación de eventos para asegurar que siempre funcione
    $(document).off('click', '.accept-image').on('click', '.accept-image', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const input = document.getElementById('imageInput');
        if (input && input.files && input.files[0]) {
            const file = input.files[0];
            if (file.type.startsWith('image/')) {
                uploadImage(file);
            } else {
                alert("El archivo seleccionado no es una imagen.");
            }
        } else {
            alert("Por favor, seleccione una imagen antes de aceptar.");
        }
        return false;
    });
    
    $('.accept-qr').off('click').on('click', function (e) {
        e.preventDefault();
        const formData = new FormData();
        formData.append('text', $('#qr-text').val());
        showDesignLoading('Generando código QR...');
        fetch(@json(route('design.generateQr')), {
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
                $('#step-edit-next').addClass('d-none');
            }
        })
        .catch(error => console.error('Error al subir la imagen:', error))
        .finally(() => hideDesignLoading());
    });
});

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

function reapplyElementEvents() {
    enableDesignElementsResize($('#containment-wrapper' + step));
    clearStaleTextPlaceholders($('#containment-wrapper' + step));
    destroyStepDraggables(step);

    // Asegurar que los botones edit-btn existan en los elementos
    $('#containment-wrapper' + step).find('.elements.text').each(function() {
      if ($(this).find('.edit-btn').length === 0) {
        $(this).prepend('<button class="edit-btn" title="Editar texto"><i class="ri-edit-line"></i></button>');
      }
    });
    
    $('#containment-wrapper' + step).find('.elements.images').each(function() {
      if ($(this).find('.edit-btn').length === 0) {
        $(this).append('<button class="edit-btn" title="Cambiar imagen"><i class="ri-image-line"></i></button>');
      }
    });
    
    // Compensación de arrastre con zoom
    var dragClickOffsetX, dragClickOffsetY;
    $('#containment-wrapper' + step).find('.elements').draggable({ 
      handle: 'span', 
      containment: "#containment-wrapper"+step, 
      scroll: false, 
      start: function(event, ui){
        selectDesignElement($(this));
        markDesignDirty();
        $('#step-edit-next').addClass('d-none');
        if (typeof designZoom !== 'undefined' && designZoom !== 1) {
          var el = ui.helper[0];
          var r = el.getBoundingClientRect();
          dragClickOffsetX = (event.clientX - r.left) / designZoom;
          dragClickOffsetY = (event.clientY - r.top) / designZoom;
        }
        saveHistoryState();
        updateUndoRedoButtons();
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
        if (step >= 2 && step <= 4 && ui && ui.helper && ui.helper[0]) clampElementToMargins(ui.helper[0]);
        saveHistoryState();
        updateUndoRedoButtons();
      }
    });
    $('.elements.participation, .elements.reference, .elements.qr, .elements.number, .elements.mini').addClass('element-critical');
    
    // Vincular eventos de los botones edit-btn (con prevención de propagación)
    $('.elements.text .edit-btn').off('click', editelements).on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        editelements.call(this, e);
        return false;
    });
    $('.elements.images .edit-btn').off('click', changeImage).on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        changeImage.call(this, e);
        return false;
    });
    
    // Vincular eventos de doble clic
    $('.elements.text').off('dblclick', editelements).on('dblclick', function(e) {
        e.preventDefault();
        e.stopPropagation();
        editelements.call(this, e);
        return false;
    });
    // Doble clic en barra abre modal de opciones (manejador delegado en document)
    $('.elements.images').off('dblclick', changeImage).on('dblclick', function(e) {
        e.preventDefault();
        e.stopPropagation();
        changeImage.call(this, e);
        return false;
    });
    $('.elements.qr').off('dblclick', setQRtext).on('dblclick', function(e) {
        e.preventDefault();
        e.stopPropagation();
        setQRtext.call(this, e);
        return false;
    });
    
    addEventsElement();
    configMargins();
}

// --- Funciones para el fondo de ticket (copiadas de format.blade.php) ---
function bgImageCssUrl(url) {
  if (!url) return 'none';
  return 'url("' + String(url).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '")';
}
$(document).on('input', '#background-color', function() {
  $('#bg-preview').css('background-color', $(this).val());
});
$(document).on('change', '#background-image', function(e) {
  if(this.files && this.files[0]) {
    const reader = new FileReader();
    reader.onload = function(ev) {
      $('#bg-preview').css('background-image', bgImageCssUrl(ev.target.result));
    };
    reader.readAsDataURL(this.files[0]);
  }
});
$(document).on('click', '#remove-bg-image', function() {
  $('#bg-preview').css('background-image', 'none');
  $('#background-image').val('');
  localStorage.removeItem('bgimg-step'+step);
});
$(document).on('click', '#apply-bg', function() {
  const color = $('#background-color').val();
  let img = '';
  if($('#background-image')[0].files && $('#background-image')[0].files[0]) {
    const file = $('#background-image')[0].files[0];
    const formData = new FormData();
    formData.append('image', file);
    showDesignLoading('Subiendo imagen...');
    fetch(@json(route('design.uploadImage')), {
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
  if (!$cont.length) $cont = $('#containment-wrapper'+step);
  $cont.css('background-color', color || '#ffffff');
  if(img) {
    let imageUrl = img;
    if (!imageUrl.startsWith('http') && !imageUrl.startsWith('/')) {
      imageUrl = '/' + imageUrl;
    }
    $cont.css('background-image', bgImageCssUrl(imageUrl));
    $cont.css('background-size', 'cover');
    $cont.css('background-position', 'center');
    $cont.css('background-repeat', 'no-repeat');
    if ($cont[0]) $cont[0].offsetHeight;
  } else {
    $cont.css('background-image', 'none');
  }
  if (typeof syncMarginBgLayers === 'function') syncMarginBgLayers();
}

enableDesignElementsResize();

// Cargar imágenes de fondo existentes al inicializar
function loadExistingBackgrounds() {
  // Cargar fondos para cada paso
  for (let i = 2; i <= 4; i++) {
    const color = localStorage.getItem('bg-step' + i) || '#dfdfdf';
    const img = localStorage.getItem('bgimg-step' + i);
    
    if (img || color !== '#dfdfdf') {
      var $cont = (typeof getBackgroundTargetEl === 'function')
        ? getBackgroundTargetEl(i)
        : ((i === 4) ? $('#design-back-bg') : $('#containment-wrapper' + i));
      if (!$cont.length) $cont = $('#containment-wrapper' + i);
      if ($cont.length) {
        $cont.css('background-color', color);
        if (img) {
          let imageUrl = img;
          if (!imageUrl.startsWith('http') && !imageUrl.startsWith('/')) {
            imageUrl = '/' + imageUrl;
          }
          $cont.css('background-image', bgImageCssUrl(imageUrl));
          $cont.css('background-size', 'cover');
          $cont.css('background-position', 'center');
          $cont.css('background-repeat', 'no-repeat');
        } else {
          $cont.css('background-image', 'none');
        }
      }
    }
  }
}

// loadExistingBackgrounds() se llama en el document.ready principal (tras configMargins)

// Función para debuggear problemas con imágenes de fondo
function debugBackgroundImage(step) {
  const $cont = $('#containment-wrapper' + step);
  const bgImage = $cont.css('background-image');
  const bgColor = $cont.css('background-color');
  const bgSize = $cont.css('background-size');
  
  console.log('Debug fondo paso ' + step + ':');
  console.log('- Imagen:', bgImage);
  console.log('- Color:', bgColor);
  console.log('- Tamaño:', bgSize);
  console.log('- Elemento:', $cont[0]);
}

// Agregar función de debug al modal de fondo
$(document).on('click', '#open-bg-modal', function() {
  debugBackgroundImage(step);
});
</script>
@include('design.partials.preview_pdf_button')
@endsection 
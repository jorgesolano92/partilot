@extends('layouts.layout')

@section('title','Nueva Devoluci?n')

@section('content')

<style>
    .form-wizard-element, .form-wizard-element label {
        cursor: pointer;
    }
    
    /* Asegurar que las clases active sean visibles */
    .form-wizard-element.active {
        background-color: #cccccc !important;
        color: #111 !important;
        filter: invert(1) !important;
    }
    .form-check-input:checked {
        border-color: #333;
    }

    .devolucion-paso {
        transition: all 0.3s ease;
    }

    .devolucion-paso table {
        margin-top: 20px;
    }

    .devolucion-paso .btn-seleccionar {
        border-radius: 20px;
        font-size: 12px;
        padding: 5px 15px;
    }

    .devolucion-paso .btn-volver {
        border-radius: 20px;
        font-size: 12px;
        padding: 5px 15px;
    }

    /* Animaci?n para transiciones entre pasos */
    .devolucion-paso.fade-in {
        animation: fadeIn 0.3s ease-in;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Estilos para las participaciones a devolver */
    .participacion-item {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s ease;
    }

    .participacion-item:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .participacion-icon {
        width: 40px;
        height: 40px;
        background: #333;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
    }

    .participacion-info {
        flex-grow: 1;
    }

    .participacion-numero {
        font-weight: bold;
        color: #333;
        margin-bottom: 4px;
    }

    .participacion-fecha {
        color: #666;
        font-size: 0.9em;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .participacion-estado {
        background: #28a745;
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        margin-top: 4px;
        display: inline-block;
    }

    .btn-eliminar-participacion {
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 8px;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-eliminar-participacion:hover {
        background: #c82333;
        transform: scale(1.1);
    }

    .grid-participaciones {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
    }

    /* Estilos para el resumen de devoluci?n */
    .resumen-devolucion {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .resumen-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #e9ecef;
    }

    .resumen-item:last-child {
        border-bottom: none;
        font-weight: bold;
        font-size: 1.1em;
    }

    /* Estilos para liquidaci?n */
    .liquidacion-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        transition: all 0.3s ease;
    }

    .liquidacion-card:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .liquidacion-header {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e9ecef;
    }

    .liquidacion-icon {
        width: 50px;
        height: 50px;
        background: #f8f9fa;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
    }

    .liquidacion-info h5 {
        margin: 0;
        color: #333;
    }

    .liquidacion-info small {
        color: #666;
    }

    .liquidacion-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }

    .stat-item {
        text-align: center;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 8px;
    }

    .stat-number {
        font-size: 1.5em;
        font-weight: bold;
        color: #333;
    }

    .stat-label {
        font-size: 0.8em;
        color: #666;
        margin-top: 5px;
    }

    .special-prize-card {
        border: 1px solid #f1c27d;
        border-radius: 14px;
        background: #fffdf8;
    }

    .special-prize-card .card-title {
        font-weight: 700;
    }

    .special-prize-restante {
        font-size: 1.1rem;
        font-weight: 700;
    }

    .special-prize-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .special-prize-table-wrap {
        max-height: 260px;
        overflow: auto;
    }

    .special-prize-side-panel {
        height: 100%;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .special-prize-side-panel .asignaciones-box {
        flex: 1 1 auto;
        min-height: 0;
    }

    .tabla-scroll-fill .dataTables_wrapper {
        min-height: 658px;
    }

    .tabla-scroll-fill .dataTables_scrollBody {
        height: auto !important;
        max-height: none !important;
        min-height: 0 !important;
        overflow-y: visible !important;
    }

    #pasos_devolucion .form-card.bs {
        overflow: visible !important;
    }
</style>

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('devolutions.index') }}">Devoluciones</a></li>
                        <li class="breadcrumb-item active">Nueva Devoluci?n</li>
                    </ol>
                </div>
                <h4 class="page-title">Devoluciones</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">
                        Nueva Devoluci?n de Entidad
                    </h4>

                    <br>

                    <div class="row">
                        <div class="col-md-3" style="position: relative;">
                            <!-- Pasos del proceso (din?micos seg?n tipo de devoluci?n) -->
                            <ul class="form-card bs mb-3 nav" id="wizard-steps">
                                <li class="nav-item">
                                    <div class="form-wizard-element active" id="step-1">
                                        <span>&nbsp;&nbsp;</span>
                                        <img src="{{url('assets/entidad.svg')}}" alt="">
                                        <label>Seleccionar Entidad</label>
                                    </div>
                                </li>
                                <li class="nav-item">
                                    <div class="form-wizard-element" id="step-2">
                                        <span>&nbsp;&nbsp;</span>
                                        <img src="{{url('icons_/usuarios.svg')}}" alt="">
                                        <label>Selec. Opci?n</label>
                                    </div>
                                </li>
                                <!-- Los pasos siguientes se cargar?n din?micamente -->
                            </ul>

                            <!-- Informaci?n de la entidad seleccionada -->
                            <div class="form-card bs mb-3" id="entity-info" style="display: none;">
                                <div class="row">
                                    <div class="col-4">
                                        <div class="photo-preview-3 logo-round" id="entity-info-image">
                                            <i class="ri-building-line"></i>
                                        </div>
                                    </div>
                                    <div class="col-8 text-center mt-2">
                                        <h3 class="mt-2 mb-0" id="entity-name">Entidad</h3>
                                        <i class="ri-map-pin-line"></i> <span id="entity-location">Ubicaci?n</span>
                                    </div>
                                </div>
                            </div>

                            <a href="{{ route('devolutions.index') }}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> 
                                <span style="display: block; margin-left: 16px;">Atr?s</span>
                            </a>
                        </div>

                        <div class="col-md-9">
                            <div class="tabbable">
                                <div class="tab-content p-0">
                                    
                                    <!-- Paso 1: Selecci?n de Entidad -->
                                    <div class="tab-pane fade active show" id="paso-entidad">
                                        <div class="form-card bs" style="min-height: 658px;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h4 class="mb-0 mt-1">Seleccionar Entidad</h4>
                                                    <small><i>Elige la entidad para la devoluci?n</i></small>
                                                </div>
                                            </div>

                                            <br>

                                            <div class="table-responsive tabla-scroll-fill">
                                                <table id="tabla-entidades" class="table table-striped nowrap w-100">
                                                    <thead>
                                                        <tr>
                                                            <th>Imagen</th>
                                                            <th>ID</th>
                                                            <th>Entidad</th>
                                                            <th>Provincia</th>
                                                            <th>Localidad</th>
                                                            <th>Administraci?n</th>
                                                            <th>Estado</th>
                                                            <th class="d-none">Seleccionar</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <!-- Los datos se cargar?n din?micamente -->
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div class="row">
                                                <div class="col-12 text-end">
                                                    <button type="button" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2" id="btn-siguiente-entidad" disabled>
                                                        Siguiente
                                                        <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Paso 2: Selecci?n de Opci?n -->
                                    <div class="tab-pane fade" id="paso-opcion">
                                        <div class="form-card bs" style="min-height: 658px;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h4 class="mb-0 mt-1">Seleccionar Tipo de Devoluci?n</h4>
                                                    <small><i>Elige el tipo de devoluci?n a realizar</i></small>
                                                </div>
                                                <div id="back-to-option-buttons">
                                                    <button type="button" class="btn btn-secondary btn-sm" id="back-option-button" style="border-radius: 30px;">
                                                        <i class="ri-arrow-left-line"></i> Volver a entidades
                                                    </button>
                                                </div>
                                            </div>

                                            <br>

                                            <!-- Mostrar informaci?n de la entidad -->
                                            <div class="form-group mt-2 mb-3 admin-box">
                                                <div class="row">
                                                    <div class="col-1">
                                                        <div class="photo-preview-3">
                                                            <i class="ri-building-line"></i>
                                                        </div>
                                                        <div style="clear: both;"></div>
                                                    </div>
                                                    <div class="col-4 text-center mt-3">
                                                        <h4 class="mt-0 mb-0" id="opcion-entity-name">Entidad</h4>
                                                        <small id="opcion-entity-province">Provincia</small> <br>
                                                        <small id="opcion-entity-admin">Administraci?n</small>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="mt-3">
                                                            <span>Provincia: <span id="opcion-entity-province-2">N/A</span></span> <br>
                                                            <span>Direcci?n: <span id="opcion-entity-address">N/A</span></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-3">
                                                        <div class="mt-3">
                                                            <span>Ciudad: <span id="opcion-entity-city">N/A</span></span> <br>
                                                            <span>Tel: <span id="opcion-entity-phone">N/A</span></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <br>

                                            <!-- Opciones de tipo de devoluci?n -->
                                            <div id="all-options-devolution">
                                                <div class="mt-4 text-center">
                                                    <div id="devolution-type-buttons">
                                                        <button class="btn btn-light btn-xl text-center m-2 bs" id="btn-devolucion-vendedor" style="border: 1px solid #f0f0f0; padding: 16px; width: 160px; border-radius: 16px;">
                                                            <img class="mt-2 mb-1" src="{{url('assets/vendedor.svg')}}" alt="" width="60%">
                                                            <h4 class="mb-0">Devoluci?n <br> Vendedor</h4>
                                                        </button>

                                                        <button class="btn btn-light btn-xl text-center m-2 bs" id="btn-devolucion-administracion" style="border: 1px solid #f0f0f0; padding: 16px; width: 180px; border-radius: 16px; position: relative;">
                                                            {{-- <img class="mt-2 mb-1" src="{{url('assets/admin.svg')}}" alt="" width="60%"> --}}
                                                            <img class="mt-2 mb-1" src="{{url('assets/vendedor.svg')}}" alt="" width="60%">
                                                            <h4 class="mb-0">Devoluci?n <br> Administraci?n</h4>
                                                        </button>

                                                        <button class="btn btn-light btn-xl text-center m-2 bs" id="btn-anulacion-participaciones" style="border: 1px solid #f0f0f0; padding: 16px; width: 180px; border-radius: 16px; position: relative;">
                                                            {{-- <img class="mt-2 mb-1" src="{{url('assets/cancel.svg')}}" alt="" width="60%"> --}}
                                                            <img class="mt-2 mb-1" src="{{url('assets/vendedor.svg')}}" alt="" width="60%">
                                                            <h4 class="mb-0">Anulaci?n <br> Participaciones</h4>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Paso 3 (Vendedor): Selecci?n de Vendedor -->
                                    <div class="tab-pane fade" id="paso-vendedor">
                                        <div class="form-card bs" style="min-height: 658px;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h4 class="mb-0 mt-1">Seleccionar Vendedor</h4>
                                                    <small><i>Elige el vendedor para la devoluci?n</i></small>
                                                </div>
                                                <button id="btn-volver-opcion" class="btn btn-secondary btn-sm">
                                                    <i class="ri-arrow-left-line"></i> Volver a Opciones
                                                </button>
                                            </div>

                                            <br>

                                            <div class="table-responsive tabla-scroll-fill">
                                                <table id="tabla-vendedores" class="table table-striped nowrap w-100">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Nombre</th>
                                                            <th>Email</th>
                                                            <th>Tel?fono</th>
                                                            <th>Estado</th>
                                                            <th class="d-none">Seleccionar</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <!-- Los datos se cargar?n din?micamente -->
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div class="row">
                                                <div class="col-12 text-end">
                                                    <button type="button" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2" id="btn-siguiente-vendedor" disabled>
                                                        Siguiente
                                                        <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Paso: Selecci?n de Sorteo -->
                                    <div class="tab-pane fade" id="paso-sorteo">
                                        <div class="form-card bs" style="min-height: 658px;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h4 class="mb-0 mt-1">Seleccionar Sorteo</h4>
                                                    <small><i>Elige el sorteo para la devoluci?n</i></small>
                                                </div>
                                                <button id="btn-volver-desde-sorteo" class="btn btn-secondary btn-sm">
                                                    <i class="ri-arrow-left-line"></i> <span id="btn-volver-desde-sorteo-text">Volver a Opciones</span>
                                                </button>
                                            </div>

                                            <br>

                                            <div class="table-responsive tabla-scroll-fill">
                                                <table id="tabla-sorteos" class="table table-striped nowrap w-100">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Nombre Sorteo</th>
                                                            <th>Fecha Sorteo</th>
                                                            <th>Descripci?n</th>
                                                            <th class="d-none">Seleccionar</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <!-- Los datos se cargar?n din?micamente -->
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div class="row">
                                                <div class="col-12 text-end">
                                                    <button type="button" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2" id="btn-siguiente-sorteo" disabled>
                                                        Siguiente
                                                        <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Paso 3: Asignaci?n de Participaciones -->
                                    <div class="tab-pane fade" id="paso-participaciones">
                                        <div class="form-card bs" style="min-height: 658px;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h4 class="mb-0 mt-1">Indica las participaciones</h4>
                                                    <small><i>Individual o por rango</i></small>
                                                </div>
                                                <button id="btn-volver-sorteo-desde-participaciones" class="btn btn-secondary btn-sm">
                                                    <i class="ri-arrow-left-line"></i> Volver a Sorteos
                                                </button>
                                            </div>

                                            <br>

                                            <div class="row">
                                                <!-- Secci?n: Selecci?n de Reserva (si hay m?s de una se muestra el selector) -->
                                                <div class="col-md-12 mb-3" id="wrapper-seleccion-reserva">
                                                    <div class="form-card bs">
                                                        <div class="d-flex align-items-center p-3">
                                                            <div class="me-3">
                                                                <img src="{{url('icons_/sets.svg')}}" alt="" width="40px">
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h4 class="m-0 fw-bold">Seleccionar Reserva</h4>
                                                                <small class="text-muted">Elige la reserva de participaciones</small>
                                                                <br>
                                                                <small class="text-info"><i class="ri-information-line"></i> Si solo hay una reserva se selecciona autom?ticamente</small>
                                                            </div>
                                                            <div style="width: 40%;" id="contenedor-selector-reserva">
                                                                <label class="form-label small mb-1">Reserva</label>
                                                                <div class="input-group input-group-merge group-form">
                                                                    <select class="form-select" id="selector-reserva" style="border-radius: 30px;">
                                                                        <option value="">Seleccionar reserva...</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Participaciones disponibles para devolver (entidad o vendedor) -->
                                                <div class="col-md-12 mb-3" id="bloque-disponibles-devolver" style="display: none;">
                                                    <div class="form-card bs border-primary">
                                                        <div class="d-flex align-items-center p-3">
                                                            <div class="me-3">
                                                                <i class="ri-information-line text-primary" style="font-size: 1.5rem;"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h5 class="m-0 fw-bold text-dark">Participaciones disponibles para devolver</h5>
                                                                <small class="text-muted" id="texto-tipo-disponibles">Como entidad en esta reserva</small>
                                                                <div class="mt-2">
                                                                    <span id="disponibles-devolver-total" class="fw-bold fs-5 text-primary">0</span>
                                                                    <span class="text-muted" id="disponibles-devolver-etiqueta-total"> total</span>
                                                                    <span class="ms-2 text-muted" id="disponibles-devolver-desglose">(<span id="disponibles-devolver-fisicas">0</span> f?sicas, <span id="disponibles-devolver-digitales">0</span> digitales)</span>
                                                                </div>
                                                                <small class="text-muted d-block mt-1" id="nota-disponibles-devolver">No se pueden devolver las ya vendidas.</small>
                                                                <div class="alert alert-info py-2 mt-2 mb-0 small" id="mensaje-digitales-entidad" style="display: none;">
                                                                    Las participaciones <strong>digitales</strong> no se asignan a vendedores (pool de la entidad).
                                                                    Al confirmar la devoluci?n a administraci?n, las digitales <strong>no vendidas</strong> de esta reserva se marcar?n autom?ticamente como devueltas.
                                                                    Las digitales <strong>ya vendidas</strong> se incluir?n en la liquidaci?n.
                                                                    En este paso solo seleccionas manualmente las <strong>f?sicas</strong> a devolver.
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Secci?n: Participaciones Por Rango -->
                                                <div class="col-md-12 mb-3">
                                                    <div class="form-card bs">
                                                        <div class="d-flex align-items-center p-3">
                                                            <div class="me-3">
                                                                <img src="{{url('icons_/participaciones.svg')}}" alt="" width="40px">
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h4 class="m-0 fw-bold">Participaciones</h4>
                                                                <small class="text-muted">Por Rango</small>
                                                            </div>
                                                            <div class="d-flex gap-2" style="width: 70%;">
                                                                <div class="flex-fill">
                                                                    <label class="form-label small mb-1">Desde</label>
                                                                    <div class="input-group input-group-merge group-form">
                                                                        <input type="number" class="form-control" id="rango-desde" placeholder="N?mero inicial" style="border-radius: 30px;">
                                                                    </div>
                                                                </div>
                                                                <div class="flex-fill">
                                                                    <label class="form-label small mb-1">Hasta</label>
                                                                    <div class="input-group input-group-merge group-form">
                                                                        <input type="number" class="form-control" id="rango-hasta" placeholder="N?mero final" style="border-radius: 30px;">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex align-items-center p-3">
                                                            <div class="me-3">
                                                                <img src="{{url('icons_/participaciones.svg')}}" alt="" width="40px">
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h4 class="m-0 fw-bold">Participaci?n</h4>
                                                                <small class="text-muted">Participaci?n Unidad</small>
                                                            </div>
                                                            <div class="d-flex gap-2 align-items-end" style="width: 70%;">
                                                                <div style="width: 50%;">
                                                                    <label class="form-label small mb-1">Participaci?n</label>
                                                                    <div class="input-group input-group-merge group-form">
                                                                        <input type="number" class="form-control" id="participacion-unidad" placeholder="N?mero de participaci?n" style="border-radius: 30px;">
                                                                    </div>
                                                                </div>
                                                                <div style="width: 50%;">
                                                                    <button type="button" class="btn btn-warning w-100" id="btn-asignar-participacion" style="border-radius: 30px; background-color: #e78307; color: #333; font-weight: bold;">
                                                                        Asignar
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div id="resumen-asignacion" style="display: block;">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h4 class="mb-0 mt-1">Resumen Asignaci?n</h4>
                                                        <small><i>comprueba que la asignaci?n sea la correcta</i></small>
                                                    </div>
                                                </div>
                                                
                                                <br>
                                                
                                                <!-- Estado vac?o -->
                                                <div id="estado-vacio-resumen" class="d-flex align-items-center gap-1">
                                                    <div class="empty-tables">
                                                        <div>
                                                            <img src="{{url('icons_/participaciones.svg')}}" alt="" width="80px" style="margin-top: 10px;">
                                                        </div>
                                                        <h3 class="mb-0">No hay Participaciones</h3>
                                                        <small>Asigna Participaciones</small>
                                                    </div>
                                                </div>

                                                <!-- Lista de participaciones asignadas -->
                                                <div id="lista-participaciones-asignadas" style="display: none;">
                                                    <div class="form-card bs" style="max-height: 400px; overflow-y: auto;">
                                                        <div class="grid-participaciones" id="grid-participaciones">
                                                            <!-- Las participaciones se cargar?n din?micamente aqu? -->
                                                        </div>
                                                    </div>
                                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="fw-bold">Total Asignadas:</span>
                                            <div class="form-card bs px-3 py-2">
                                                <span id="total-asignadas" class="fw-bold fs-4">0</span>
                                            </div>
                                            <span class="text-muted">|</span>
                                            <span class="fw-bold">Sets:</span>
                                            <div class="form-card bs px-3 py-2">
                                                <span id="total-sets" class="fw-bold fs-4">0</span>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn" id="btn-aceptar-solo-devolucion" style="border-radius: 30px; background-color: #333; color: #fff; font-weight: bold; padding: 10px 30px;">
                                                Aceptar
                                            </button>
                                            <button type="button" class="btn btn-warning" id="btn-terminar-asignacion" style="border-radius: 30px; background-color: #e78307; color: #333; font-weight: bold; padding: 10px 30px;">
                                                Siguiente
                                            </button>
                                        </div>
                                    </div>
                                                </div>

                                                <!-- Bot?n para continuar sin participaciones -->
                                                <div id="btn-continuar-sin-participaciones-container" class="text-end mt-3" style="display: none;">
                                                    <button type="button" class="btn btn-info" id="btn-continuar-sin-participaciones" style="border-radius: 30px; font-weight: bold; padding: 10px 30px;">
                                                        <i class="ri-arrow-right-line"></i> Continuar sin participaciones
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Paso: Anulaci?n -->
                                    <div class="tab-pane fade" id="paso-anulacion">
                                        <div class="form-card bs" style="min-height: 658px;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h4 class="mb-0 mt-1">Confirmar Anulaci?n</h4>
                                                    <small><i>Revisa las participaciones a anular</i></small>
                                                </div>
                                                <button id="btn-volver-anulacion" class="btn btn-secondary btn-sm">
                                                    <i class="ri-arrow-left-line"></i> Volver
                                                </button>
                                            </div>

                                            <hr>

                                            <!-- Resumen de Participaciones a Anular -->
                                            <div class="row mb-4">
                                                <div class="col-12">
                                                    <h5>Participaciones Seleccionadas para Anular</h5>
                                                    <div id="anulacion-resumen-participaciones">
                                                        <!-- Se llenar? din?micamente -->
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Informaci?n de la Anulaci?n -->
                                            <div class="row mb-4">
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">Informaci?n de la Anulaci?n</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <p><strong>Entidad:</strong> <span id="anulacion-entidad-nombre">-</span></p>
                                                            <p><strong>Sorteo:</strong> <span id="anulacion-sorteo-nombre">-</span></p>
                                                            <p><strong>Set:</strong> <span id="anulacion-set-nombre">-</span></p>
                                                            <p><strong>Total Participaciones:</strong> <span id="anulacion-total-participaciones" class="fw-bold">0</span></p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">Impacto en la Reserva</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <p><strong>Monto Liberado:</strong> <span id="anulacion-monto-liberado" class="text-success fw-bold">0.00���</span></p>
                                                            <p><strong>Cr?dito Disponible:</strong> <span id="anulacion-credito-disponible" class="text-info fw-bold">0.00���</span></p>
                                                            <small class="text-muted">Este monto podr? ser utilizado para crear nuevos sets</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Motivo de Anulaci?n -->
                                            <div class="row mb-4">
                                                <div class="col-12">
                                                    <label class="form-label fw-bold">Motivo de la Anulaci?n</label>
                                                    <textarea class="form-control" id="anulacion-motivo" rows="3" placeholder="Describe el motivo de la anulaci?n de estas participaciones..."></textarea>
                                                </div>
                                            </div>

                                            <!-- Botones de Acci?n -->
                                            <div class="row">
                                                <div class="col-12 text-end">
                                                    <button id="btn-cancelar-anulacion" class="btn btn-secondary me-2">
                                                        <i class="ri-close-line"></i> Cancelar
                                                    </button>
                                                    <button id="btn-confirmar-anulacion" class="btn btn-danger">
                                                        <i class="ri-check-line"></i> Confirmar Anulaci?n
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Paso: Liquidaci?n -->
                                    <div class="tab-pane fade" id="paso-liquidacion">
                                        <div class="form-card bs" style="min-height: 658px;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h4 class="mb-0 mt-1" id="liquidacion-titulo">Liquidaci?n</h4>
                                                    <small id="liquidacion-subtitulo"><i>Procesa la liquidaci?n</i></small>
                                                </div>
                                                <button id="btn-volver-participaciones-final" class="btn btn-secondary btn-sm">
                                                    <i class="ri-arrow-left-line"></i> <span id="btn-volver-text">Volver</span>
                                                </button>
                                            </div>

                                            <hr>

                                            <!-- Contenedor para liquidaci?n de VENDEDOR -->
                                            <div id="liquidacion-vendedor-container" style="display: none;">
                                                
                                                <!-- Selector de Sorteo -->
                                                <div class="row mb-4">
                                                    <div class="col-md-6">
                                                        <label class="form-label fw-bold">Selecciona un Sorteo</label>
                                                        <select class="form-select" id="vendedor-selector-sorteo-liquidacion">
                                                            <option value="">-- Selecciona un sorteo --</option>
                                                        </select>
                                                    </div>
                                                </div>

                                                <!-- Resumen de Liquidaci?n -->
                                                <div id="vendedor-resumen-liquidacion-container" style="display: none;">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="card">
                                                            <div class="card-header">Resumen de Participaciones</div>
                                                            <div class="card-body">
                                                                <p><strong>Total Participaciones Asignadas:</strong> <span id="vendedor-settlement-total-participations" class="fw-bold fs-4">0</span></p>
                                                                <p><strong>Precio por Participaci?n:</strong> <span id="vendedor-settlement-price-per-participation">0.00���</span></p>
                                                                <p><strong>Total a Liquidar:</strong> <span id="vendedor-settlement-total-amount" class="text-danger fw-bold">0.00���</span></p>
                                                </div>
                                                </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="card">
                                                            <div class="card-header">Liquidaci?n Actual</div>
                                                            <div class="card-body">
                                                                <p><strong>Total Pagado:</strong> <span id="vendedor-settlement-total-paid" class="text-success fw-bold">0.00���</span></p>
                                                                <p><strong>Participaciones Liquidadas:</strong> <span id="vendedor-settlement-liquidated-participations">0</span></p>
                                                                <p><strong>Pendiente por Liquidar:</strong> <span id="vendedor-settlement-pending-amount" class="text-warning fw-bold">0.00���</span></p>
                                                                <p><strong>Participaciones Pendientes:</strong> <span id="vendedor-settlement-pending-participations">0</span></p>
                                                            </div>
                                                        </div>
                                                </div>
                                            </div>

                                                <!-- Formas de Pago -->
                                                <div class="card mt-3">
                                                    <div class="card-body">
                                                        <h5 class="card-title">Registrar Pagos</h5>
                                                        <small class="text-muted">Puedes registrar m?ltiples formas de pago</small>
                                                        
                                                        <div class="row mt-3">
                                                            <div class="col-8">
                                                                <!-- Pago en Efectivo -->
                                                                <div class="d-flex align-items-center mb-3 p-2 border rounded">
                                                                    <div class="me-3">
                                                                        <i class="ri-wallet-line text-success" style="font-size: 24px;"></i>
                                            </div>
                                                                    <div class="flex-grow-1">
                                                                        <strong>Pago en Efectivo</strong>
                                                                    </div>
                                                                    <div class="col-3">
                                                                        <input type="number" step="0.01" class="form-control vendedor-settlement-payment-input" placeholder="0.00" id="vendedor-settlement-pago-efectivo">
                                        </div>
                                    </div>

                                                                <!-- Pago por Bizum -->
                                                                <div class="d-flex align-items-center mb-3 p-2 border rounded">
                                                                    <div class="me-3">
                                                                        <i class="ri-percent-line text-info" style="font-size: 24px;"></i>
                                                </div>
                                                                    <div class="flex-grow-1">
                                                                        <strong>Pago por Bizum</strong>
                                                                    </div>
                                                                    <div class="col-3">
                                                                        <input type="number" step="0.01" class="form-control vendedor-settlement-payment-input" placeholder="0.00" id="vendedor-settlement-pago-bizum">
                                                                    </div>
                                                                </div>

                                                                <!-- Pago por Transferencia -->
                                                                <div class="d-flex align-items-center mb-3 p-2 border rounded">
                                                                    <div class="me-3">
                                                                        <i class="ri-building-line text-primary" style="font-size: 24px;"></i>
                                                                    </div>
                                                                    <div class="flex-grow-1">
                                                                        <strong>Pago por Transferencia</strong>
                                                                    </div>
                                                                    <div class="col-3">
                                                                        <input type="number" step="0.01" class="form-control vendedor-settlement-payment-input" placeholder="0.00" id="vendedor-settlement-pago-transferencia">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-4">
                                                                <div class="text-center">
                                                                    <div class="border rounded p-3 mb-3 bg-light">
                                                                        <small class="text-muted">Pendiente a Pagar</small>
                                                                        <div class="text-danger h4" id="vendedor-settlement-pendiente-display">0,00���</div>
                                                                    </div>
                                                                    <div class="border rounded p-3 mb-3 bg-success bg-opacity-10">
                                                                        <small class="text-muted">A Pagar Ahora</small>
                                                                        <div class="text-success h4" id="vendedor-settlement-pagar-ahora">0,00���</div>
                                                                    </div>
                                                                    <div class="border rounded p-3 mb-3" id="vendedor-settlement-quedara-pendiente-container">
                                                                        <small class="text-muted">Quedar? Pendiente</small>
                                                                        <div class="h5" id="vendedor-settlement-quedara-pendiente">0,00���</div>
                                                                    </div>
                                                                    <button type="button" class="btn btn-warning" id="btn-registrar-liquidacion-vendedor" style="border-radius: 30px; width: 100%;">
                                                                        <i class="ri-add-line"></i> Registrar Liquidaci?n
                                                </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                            </div>

                                                <!-- Historial de Liquidaciones -->
                                                <div class="card mt-3">
                                                    <div class="card-header">
                                                        <h5 class="card-title mb-0">Historial de Liquidaciones</h5>
                                                    </div>
                                                    <div class="card-body">
                                                        <div id="vendedor-historial-liquidaciones-container">
                                                            <p class="text-muted text-center">Selecciona un sorteo para ver el historial</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                </div> <!-- Cierre vendedor-resumen-liquidacion-container -->
                                            </div> <!-- Cierre liquidacion-vendedor-container -->

                                            <!-- Contenedor para liquidaci?n de ADMINISTRACI��N -->
                                            <div id="liquidacion-administracion-container" style="display: none;">
                                                    <!-- Resumen Devoluci?n -->
                                                    <div class="card mb-3">
                                                        <div class="card-body">
                                                            <h5 class="card-title">Resumen Devoluci?n</h5>
                                                            <small class="text-muted" id="liquidacion-resumen-subtitulo">Resumen Devoluci?n Administraci?n</small>
                                                            <div class="alert alert-info py-2 mt-2 mb-0 small">
                                                                <strong>Digitales (pool de la entidad):</strong> las no vendidas de la reserva se devuelven autom?ticamente a administraci?n al confirmar.
                                                                Las vendidas entran en la liquidaci?n. Las f?sicas no devueltas se liquidan como vendidas.
                                                            </div>
                                                            
                                                            <div class="text-center my-3">
                                                                <img src="{{url('assets/ticket.svg')}}" alt="" width="60px">
                                                                <div class="mt-2">
                                                                    <strong id="liquidacion-ticket-number">-</strong>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-6">
                                                                    <div class="mb-2">
                                                                        <strong>Total Participaciones:</strong>
                                                                        <span id="liquidacion-total-participaciones">0</span>
                                                                    </div>
                                                                    <div class="mb-2">
                                                                        <strong>Ventas registradas:</strong>
                                                                        <span id="liquidacion-ventas-registradas">0</span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6">
                                                                    <div class="mb-2">
                                                                        <strong>Participaciones devueltas:</strong>
                                                                        <span id="liquidacion-participaciones-devueltas">0</span>
                                                                        <small class="text-muted d-block" id="liquidacion-devueltas-detalle"></small>
                                                                    </div>
                                                                    <div class="mb-2">
                                                                        <strong>F?sicas pendientes:</strong>
                                                                        <span id="liquidacion-disponibles">0</span>
                                                                        <small class="text-muted d-block">Solo f?sicas; las digitales del pool van en devueltas</small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Liquidaci?n Actual -->
                                                    <div class="card mb-3">
                                                        <div class="card-body">
                                                            <h5 class="card-title">Liquidaci?n Actual</h5>
                                                            <div class="row">
                                                                <div class="col-4">
                                                                    <div class="mb-2">
                                                                        <strong>Total Liquidaci?n:</strong>
                                                                        <span id="liquidacion-total-liquidacion">0,00���</span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-4">
                                                                    <div class="mb-2">
                                                                        <strong>Pagos Registrados:</strong>
                                                                        <span id="liquidacion-pagos-registrados">0,00���</span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-4">
                                                                    <div class="mb-2">
                                                                        <strong>Total a Pagar:</strong>
                                                                        <span id="liquidacion-total-pagar">0,00���</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Asignaci?n obligatoria para premio especial -->
                                                    <div class="card mb-3 special-prize-card" id="bloque-premio-especial" style="display:none;">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                                <div>
                                                                    <h5 class="card-title mb-1">Premio especial: Serie y fracci?n</h5>
                                                                    <small class="text-muted" id="special-prize-resumen-text">Debes registrar series/fracciones vendidas para poder cerrar la liquidaci?n.</small>
                                                                </div>
                                                                <span class="badge bg-warning text-dark" id="special-prize-badge">Opcional</span>
                                                            </div>
                                                            <div class="alert alert-info py-2 mb-3" id="special-prize-requirement-alert">
                                                                Puedes registrar esta informaci?n de forma opcional en este sorteo.
                                                            </div>

                                                            <div class="row g-3">
                                                                <div class="col-md-9">
                                                                    <div class="form-card bs mb-2" style="background:#f3f3f3;">
                                                                        <div class="d-flex align-items-center p-3">
                                                                            <div class="me-3">
                                                                                <img src="{{url('icons_/participaciones.svg')}}" alt="" width="28px">
                                                                            </div>
                                                                            <div class="flex-grow-1">
                                                                                <h5 class="m-0 fw-bold">Series Completas</h5>
                                                                                <small class="text-muted">Por Rango</small>
                                                                            </div>
                                                                            <div class="d-flex gap-2" style="width:70%;">
                                                                                <div class="flex-fill">
                                                                                    <label class="form-label small mb-1">Desde</label>
                                                                                    <input type="number" min="1" class="form-control" id="special-serie-rango-desde" placeholder="N?mero inicial" style="border-radius: 30px;">
                                                                                </div>
                                                                                <div class="flex-fill">
                                                                                    <label class="form-label small mb-1">Hasta</label>
                                                                                    <input type="number" min="1" class="form-control" id="special-serie-rango-hasta" placeholder="N?mero final" style="border-radius: 30px;">
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="d-flex align-items-center p-3 pt-0">
                                                                            <div class="me-3">
                                                                                <img src="{{url('icons_/participaciones.svg')}}" alt="" width="28px">
                                                                            </div>
                                                                            <div class="flex-grow-1">
                                                                                <h5 class="m-0 fw-bold">Serie</h5>
                                                                                <small class="text-muted">Por Unidad</small>
                                                                            </div>
                                                                            <div class="d-flex gap-2 align-items-end" style="width:70%;">
                                                                                <div style="width:50%;">
                                                                                    <label class="form-label small mb-1">Serie</label>
                                                                                    <input type="number" min="1" class="form-control" id="special-serie-unidad" placeholder="N?mero de serie" style="border-radius: 30px;">
                                                                                </div>
                                                                                <div style="width:50%;">
                                                                                    <button type="button" class="btn btn-warning w-100" id="btn-special-add-serie-unidad" style="border-radius: 30px; background-color: #e78307; color: #333; font-weight: bold;">Asignar</button>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="form-card bs" style="background:#f3f3f3;">
                                                                        <div class="d-flex align-items-center p-3">
                                                                            <div class="me-3">
                                                                                <img src="{{url('icons_/participaciones.svg')}}" alt="" width="28px">
                                                                            </div>
                                                                            <div class="flex-grow-1">
                                                                                <h5 class="m-0 fw-bold">Fracciones</h5>
                                                                                <small class="text-muted">Por Rango</small>
                                                                            </div>
                                                                            <div class="d-flex gap-2" style="width:70%;">
                                                                                <div class="flex-fill">
                                                                                    <label class="form-label small mb-1">Desde</label>
                                                                                    <input type="number" min="1" max="10" class="form-control" id="special-fraccion-rango-desde" placeholder="N?mero inicial" style="border-radius: 30px;">
                                                                                </div>
                                                                                <div class="flex-fill">
                                                                                    <label class="form-label small mb-1">Hasta</label>
                                                                                    <input type="number" min="1" max="10" class="form-control" id="special-fraccion-rango-hasta" placeholder="N?mero final" style="border-radius: 30px;">
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="d-flex align-items-center p-3 pt-0">
                                                                            <div class="me-3">
                                                                                <img src="{{url('icons_/participaciones.svg')}}" alt="" width="28px">
                                                                            </div>
                                                                            <div class="flex-grow-1">
                                                                                <h5 class="m-0 fw-bold">Fracci?n</h5>
                                                                                <small class="text-muted">Por Unidad</small>
                                                                            </div>
                                                                            <div class="d-flex gap-2 align-items-end" style="width:70%;">
                                                                                <div style="width:50%;">
                                                                                    <label class="form-label small mb-1">Fracci?n</label>
                                                                                    <input type="number" min="1" max="10" class="form-control" id="special-fraccion-unidad" placeholder="N?mero de fracci?n" style="border-radius: 30px;">
                                                                                </div>
                                                                                <div style="width:50%;">
                                                                                    <button type="button" class="btn btn-warning w-100" id="btn-special-add-fraccion-unidad" style="border-radius: 30px; background-color: #e78307; color: #333; font-weight: bold;">Asignar</button>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-3">
                                                                    <div class="special-prize-side-panel">
                                                                        <div class="form-card bs asignaciones-box" style="background:#f8f8f8;">
                                                                            <div class="special-prize-table-wrap mb-0">
                                                                                <table class="table table-sm align-middle mb-0">
                                                                                    <thead>
                                                                                        <tr>
                                                                                            <th>SERIE</th>
                                                                                            <th>FRACCIONES</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody id="special-prize-table-body">
                                                                                        <tr><td colspan="2" class="text-center text-muted">Sin asignaciones</td></tr>
                                                                                    </tbody>
                                                                                </table>
                                                                            </div>
                                                                        </div>

                                                                        <div class="form-card bs" style="background:#f8f8f8;">
                                                                            <div class="text-center fw-bold mb-2">RESTANTE</div>
                                                                            <div class="d-flex justify-content-between">
                                                                                <small class="text-muted">D��CIMOS</small>
                                                                                <small class="text-muted">IMPORTE RESTANTE</small>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between align-items-center">
                                                                                <div id="special-prize-restante-decimos" class="fw-bold fs-5 text-dark">0</div>
                                                                                <div id="special-prize-restante-importe" class="fw-bold fs-4 text-success">0,00���</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-3 offset-md-9">
                                                                    <div>
                                                                        <button type="button" class="btn btn-warning w-100" id="btn-special-reset" style="border-radius: 30px; background-color: #e78307; color: #333; font-weight: bold;">Limpiar</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Forma de Pago -->
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h5 class="card-title">Formas de Pago</h5>
                                                            <small class="text-muted">Puedes registrar m?ltiples formas de pago</small>
                                                            
                                                            <div class="row mt-3">
                                                                <div class="col-8">
                                                                    <!-- Pago en Efectivo -->
                                                                    <div class="d-flex align-items-center mb-3 p-2 border rounded">
                                                                        <div class="me-3">
                                                                            <i class="ri-wallet-line text-success" style="font-size: 24px;"></i>
                                                                        </div>
                                                                        <div class="flex-grow-1">
                                                                            <strong>Pago en Efectivo</strong>
                                                                        </div>
                                                                        <div class="col-3">
                                                                            <input type="number" step="0.01" class="form-control payment-input" placeholder="0.00" id="pago-efectivo-monto">
                                                                        </div>
                                                                    </div>

                                                                    <!-- Pago por Bizum -->
                                                                    <div class="d-flex align-items-center mb-3 p-2 border rounded">
                                                                        <div class="me-3">
                                                                            <i class="ri-percent-line text-info" style="font-size: 24px;"></i>
                                                                        </div>
                                                                        <div class="flex-grow-1">
                                                                            <strong>Pago por Bizum</strong>
                                                                        </div>
                                                                        <div class="col-3">
                                                                            <input type="number" step="0.01" class="form-control payment-input" placeholder="0.00" id="pago-bizum-monto">
                                                                        </div>
                                                                    </div>

                                                                    <!-- Pago por Transferencia -->
                                                                    <div class="d-flex align-items-center mb-3 p-2 border rounded">
                                                                        <div class="me-3">
                                                                            <i class="ri-building-line text-primary" style="font-size: 24px;"></i>
                                                                        </div>
                                                                        <div class="flex-grow-1">
                                                                            <strong>Pago por Transferencia</strong>
                                                                        </div>
                                                                        <div class="col-3">
                                                                            <input type="number" step="0.01" class="form-control payment-input" placeholder="0.00" id="pago-transferencia-monto">
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-4">
                                                                    <div class="text-center">
                                                                        <div class="border rounded p-3 mb-3 bg-light">
                                                                            <small class="text-muted">Total a Pagar</small>
                                                                            <div class="text-danger h4" id="liquidacion-importe-total">0,00���</div>
                                                                        </div>
                                                                        <div class="border rounded p-3 mb-3 bg-success bg-opacity-10">
                                                                            <small class="text-muted">Total Pagado</small>
                                                                            <div class="text-success h4" id="total-pagado">0,00���</div>
                                                                        </div>
                                                                        <div class="border rounded p-3 mb-3" id="pendiente-container" style="display: none;">
                                                                            <small class="text-muted">Pendiente</small>
                                                                            <div class="h5" id="total-pendiente">0,00���</div>
                                                                        </div>
                                                                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                                                                            <button type="button" class="btn btn-warning" id="btn-aceptar-liquidacion" style="border-radius: 30px;">
                                                                                Aceptar Liquidaci?n
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="modal-premio-especial-obligatorio" tabindex="-1" aria-labelledby="modal-premio-especial-obligatorio-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <div class="alert alert-danger mb-3">
                    <h5 class="mb-0" id="modal-premio-especial-obligatorio-label">Atenci?n: se requiere serie y fracci?n</h5>
                </div>
                <p class="mb-2">Este sorteo incluye un premio especial ?nico y la informaci?n de series/fracciones vendidas es obligatoria.</p>
                <p class="mb-0">Completa la asignaci?n para poder cerrar la liquidaci?n.</p>
                <div class="d-flex justify-content-center gap-2 mt-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-warning" id="btn-modal-premio-especial-gestionar">Gestionar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-modalidad-pago-premios" tabindex="-1" aria-labelledby="modal-modalidad-pago-premios-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-modalidad-pago-premios-label">Modalidad de pago de premios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Selecciona c?mo se gestionar?n los premios de las participaciones vendidas tras el escrutinio. Esta elecci?n no podr? modificarse por la entidad una vez confirmada la devoluci?n.</p>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="card h-100 border p-3 mb-0" style="cursor: pointer;">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="prize_payment_mode" id="prize-mode-presencial" value="presencial">
                                <span class="form-check-label fw-semibold">Opción A — Pago presencial</span>
                            </div>
                            <small class="text-muted d-block mt-2">La entidad paga en sus instalaciones (app gestor). Sin ingreso en PARTILOT salvo participaciones digitales vendidas.</small>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="card h-100 border p-3 mb-0" style="cursor: pointer;">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="prize_payment_mode" id="prize-mode-online" value="online" data-online-payer="partilot">
                                <span class="form-check-label fw-semibold">Opción B — Pago online (PARTILOT)</span>
                            </div>
                            <small class="text-muted d-block mt-2">PARTILOT gestiona la remesa. Requiere ingreso del 100% del importe premiado, contrato y activación por superadministrador.</small>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="card h-100 border p-3 mb-0" style="cursor: pointer;">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="prize_payment_mode" id="prize-mode-online-entity" value="online" data-online-payer="entity">
                                <span class="form-check-label fw-semibold">Opción C — Pago online (entidad)</span>
                            </div>
                            <small class="text-muted d-block mt-2">La entidad gestiona sus remesas desde su panel. Los usuarios cobran online tras el escrutinio, sin bloqueo PARTILOT.</small>
                        </label>
                    </div>
                </div>

                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="prize-payment-mode-confirm">
                    <label class="form-check-label" for="prize-payment-mode-confirm">
                        Confirmo la modalidad seleccionada para el cobro de premios de este sorteo.
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btn-confirmar-modalidad-pago" disabled>Aceptar y liquidar</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')

<script>
function manejarAdvertenciaLiquidacionVendedores(xhr, payload, reenviarFn) {
    var data = xhr.responseJSON;
    if (xhr.status === 409 && data && data.requires_confirmation
        && data.warning_code === 'seller_liquidation_pending'
        && !payload.acknowledge_seller_liquidation_warning) {
        if (window.confirm((data.message || 'Hay vendedores con liquidaci?n pendiente.') + '\n\n?Deseas continuar de todas formas?')) {
            payload.acknowledge_seller_liquidation_warning = true;
            reenviarFn(payload);
        }
        return true;
    }
    return false;
}

$(document).ready(function() {
    // Variables globales
    let entidadSeleccionada = null;
    let sorteoSeleccionado = null;
    let setSeleccionado = null; // legacy / anulacion
    let reservaSeleccionada = null; // para asignaci?n/devoluci?n por reserva
    let participacionesAsignadas = [];
    let tipoDevolucion = null; // 'vendedor' o 'administracion'
    let vendedorSeleccionado = null;
    let liquidacionSummaryActual = null;
    let specialPrizeRequirement = { required: false, max_series: 0 };
    let specialPrizeRequirementKey = null;
    let specialPrizeAssignments = {};
    let specialPrizeIntroShown = false;
    let pendingLiquidacionData = null;
    
    // DataTables
    let tablaEntidades = null;
    let tablaSorteos = null;
    let tablaVendedores = null;

    // Asegurar que el primer paso est? activo al cargar
    $('#step-1').addClass('active');
    
    // Inicializar DataTable de entidades al cargar la p?gina
    inicializarDataTableEntidades();

    // Variable global para rastrear el paso actual
    let pasoActualGlobal = 'paso-entidad';
    
    // Funci?n para construir din?micamente los pasos del wizard
    function construirWizardPasos() {
        const wizardSteps = $('#wizard-steps');
        
        // Mantener los primeros 2 pasos siempre
        // Limpiar solo desde el paso 3 en adelante
        wizardSteps.find('li:gt(1)').remove();
        
        if (tipoDevolucion === 'vendedor') {
            // Flujo: Entidad -> Opci?n -> Vendedor -> Sorteo -> Participaciones -> Liquidaci?n
            wizardSteps.append(`
                <li class="nav-item">
                    <div class="form-wizard-element" id="step-3">
                        <span>&nbsp;&nbsp;</span>
                        <img src="{{url('icons_/usuarios.svg')}}" alt="">
                        <label>Selec. Vendedor</label>
                    </div>
                </li>
                <li class="nav-item">
                    <div class="form-wizard-element" id="step-4">
                        <span>&nbsp;&nbsp;</span>
                        <img src="{{url('icons_/participaciones.svg')}}" alt="">
                        <label>Seleccionar Sorteo</label>
                    </div>
                </li>
                <li class="nav-item">
                    <div class="form-wizard-element" id="step-5">
                        <span>&nbsp;&nbsp;</span>
                        <img src="{{url('icons_/participaciones.svg')}}" alt="">
                        <label>Seleccionar Participaciones</label>
                    </div>
                </li>
                <li class="nav-item">
                    <div class="form-wizard-element" id="step-6">
                        <span>&nbsp;&nbsp;</span>
                        <img src="{{url('icons_/dinero.svg')}}" alt="">
                        <label>Liquidaci?n</label>
                    </div>
                </li>
            `);
        } else if (tipoDevolucion === 'administracion') {
            // Flujo: Entidad -> Opci?n -> Sorteo -> Participaciones -> Liquidaci?n
            wizardSteps.append(`
                <li class="nav-item">
                    <div class="form-wizard-element" id="step-3">
                        <span>&nbsp;&nbsp;</span>
                        <img src="{{url('icons_/participaciones.svg')}}" alt="">
                        <label>Seleccionar Sorteo</label>
                    </div>
                </li>
                <li class="nav-item">
                    <div class="form-wizard-element" id="step-4">
                        <span>&nbsp;&nbsp;</span>
                        <img src="{{url('icons_/participaciones.svg')}}" alt="">
                        <label>Seleccionar Participaciones</label>
                    </div>
                </li>
                <li class="nav-item">
                    <div class="form-wizard-element" id="step-5">
                        <span>&nbsp;&nbsp;</span>
                        <img src="{{url('icons_/usuarios.svg')}}" alt="">
                        <label>Liquidaci?n</label>
                    </div>
                </li>
            `);
        } else if (tipoDevolucion === 'anulacion') {
            // Flujo: Entidad -> Opci?n -> Sorteo -> Participaciones -> Confirmar Anulaci?n
            wizardSteps.append(`
                <li class="nav-item">
                    <div class="form-wizard-element" id="step-3">
                        <span>&nbsp;&nbsp;</span>
                        <img src="{{url('icons_/participaciones.svg')}}" alt="">
                        <label>Seleccionar Sorteo</label>
                    </div>
                </li>
                <li class="nav-item">
                    <div class="form-wizard-element" id="step-4">
                        <span>&nbsp;&nbsp;</span>
                        <img src="{{url('icons_/participaciones.svg')}}" alt="">
                        <label>Seleccionar Participaciones</label>
                    </div>
                </li>
                <li class="nav-item">
                    <div class="form-wizard-element" id="step-5">
                        <span>&nbsp;&nbsp;</span>
                        <img src="{{url('icons_/cancel.svg')}}" alt="">
                        <label>Confirmar Anulaci?n</label>
                    </div>
                </li>
            `);
        }
    }

    // Funci?n para mostrar un paso espec?fico
    function mostrarPaso(pasoId) {
        console.log('=== MOSTRAR PASO ===');
        console.log('Paso solicitado:', pasoId);
        console.log('Paso anterior:', pasoActualGlobal);
        
        $('.tab-pane').removeClass('active show');
        $('#' + pasoId).addClass('active show');
        
        // Actualizar la variable global del paso actual
        pasoActualGlobal = pasoId;
        console.log('pasoActualGlobal actualizado a:', pasoActualGlobal);
        
        // Actualizar indicadores de pasos con l?gica de progreso
        actualizarIndicadoresPasos(pasoId);
        
        // En paso sorteo: texto del bot?n volver seg?n tipo (Vendedor ��� Vendedores, Administraci?n/Anulaci?n ��� Opciones)
        if (pasoId === 'paso-sorteo') {
            const textoVolver = tipoDevolucion === 'vendedor' ? 'Volver a Vendedores' : 'Volver a Opciones';
            $('#btn-volver-desde-sorteo-text').text(textoVolver);
        }
        // En paso participaciones: actualizar conteo de disponibles para devolver (entidad o vendedor)
        if (pasoId === 'paso-participaciones' && typeof actualizarDisponiblesParaDevolver === 'function') {
            actualizarDisponiblesParaDevolver();
        }
        
        // Inicializar DataTables seg?n el paso
        if (pasoId === 'paso-entidad' && !tablaEntidades) {
            inicializarDataTableEntidades();
        }
        
        console.log('=== FIN MOSTRAR PASO ===');
    }

    // Funci?n para actualizar los indicadores de pasos (din?mico seg?n tipo de devoluci?n)
    function actualizarIndicadoresPasos(pasoActual) {
        console.log('=== ACTUALIZANDO INDICADORES ===');
        console.log('Paso actual recibido:', pasoActual);
        console.log('Tipo devoluci?n:', tipoDevolucion);
        
        let pasosOrden = [];
        
        if (tipoDevolucion === 'vendedor') {
            // Flujo con vendedor: Entidad -> Opci?n -> Vendedor -> Sorteo -> Participaciones -> Liquidaci?n
            pasosOrden = [
                'paso-entidad',
                'paso-opcion',
                'paso-vendedor',
                'paso-sorteo',
                'paso-participaciones',
                'paso-liquidacion'
            ];
        } else if (tipoDevolucion === 'administracion') {
            // Flujo sin vendedor: Entidad -> Opci?n -> Sorteo -> Participaciones -> Liquidaci?n
            pasosOrden = [
                'paso-entidad',
                'paso-opcion',
                'paso-sorteo',
                'paso-participaciones',
                'paso-liquidacion'
            ];
        } else if (tipoDevolucion === 'anulacion') {
            // Flujo anulaci?n: Entidad -> Opci?n -> Sorteo -> Participaciones -> Confirmar Anulaci?n
            pasosOrden = [
                'paso-entidad',
                'paso-opcion',
                'paso-sorteo',
                'paso-participaciones',
                'paso-anulacion'
            ];
        } else {
            // Flujo inicial (solo mostrar entidad y opci?n)
            pasosOrden = [
                'paso-entidad',
                'paso-opcion'
            ];
        }
        
        // Encontrar el ?ndice del paso actual
        const indiceActual = pasosOrden.indexOf(pasoActual);
        console.log('��ndice encontrado:', indiceActual);
        
        if (indiceActual === -1) {
            console.error('Paso no encontrado:', pasoActual);
            return;
        }
        
        // Limpiar todas las clases activas primero
        $('.form-wizard-element').removeClass('active');
        
        // Activar SOLO el paso actual
        const stepId = `step-${indiceActual + 1}`;
        const elemento = $('#' + stepId);
        
        console.log('Activando elemento:', stepId, 'Elemento encontrado:', elemento.length > 0);
        
        if (elemento.length > 0) {
            elemento.addClass('active');
        }
        
        console.log('=== FIN ACTUALIZACI��N ===');
    }


    // Funci?n para inicializar DataTable de entidades
    function inicializarDataTableEntidades() {
        if (tablaEntidades) return;
        
        tablaEntidades = $('#tabla-entidades').DataTable({
            "select": { style: "single" },
            "ordering": true,
            "sorting": true,
            "scrollX": true,
            "scrollCollapse": true,
            "language": {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
            "ajax": {
                "url": "{{ route('devolutions.entities') }}",
                "type": "GET",
                "dataSrc": "entities"
            },
            "columns": [
                {
                    "data": "image",
                    "orderable": false,
                    "render": function(data, type, row) {
                        if (data) {
                            const url = "{{ asset('uploads/') }}/" + data;
                            return `<div class="photo-preview-3 logo-round" style="width: 40px; height: 40px; min-width: 40px; min-height: 40px; background-image: url('${url}');"></div>`;
                        }
                        return `<div class="photo-preview-3 logo-round" style="width: 40px; height: 40px; min-width: 40px; min-height: 40px;"><img src="{{ url('assets/entidad.svg') }}" alt="" width="24" style="object-fit: contain;"></div>`;
                    }
                },
                { "data": "id" },
                { "data": "name" },
                { "data": "province" },
                { "data": "city" },
                { "data": "administration_name", "defaultContent": "N/A" },
                { 
                    "data": "status",
                    "render": function(data, type, row) {
                        const badgeClass = data === 'activo' ? 'bg-success' : (data === 'inactivo' ? 'bg-danger' : 'bg-secondary');
                        return `<span class="badge ${badgeClass}">${data}</span>`;
                    }
                },
                {
                    "data": null,
                    "render": function(data, type, row) {
                        const isActive = row.status === 'activo';
                        const disabled = isActive ? '' : ' disabled';
                        return `
                            <div class="form-check">
                                <input class="form-check-input seleccionar-entidad" type="radio" name="entity_id" value="${row.id}" id="entity_${row.id}" data-entity-id="${row.id}"${disabled}>
                                <label class="form-check-label" for="entity_${row.id}">Seleccionar</label>
                            </div>
                        `;
                    },
                    "orderable": false
                }
            ],
            "columnDefs": [
                { "targets": -1, "className": "d-none" }
            ],
            "createdRow": function(row, data) {
                $(row).addClass('selectable-row');
                if (data.status !== 'activo') {
                    $(row).addClass('entity-inactive').css('cursor', 'not-allowed');
                } else {
                    $(row).css('cursor', 'pointer');
                }
            },
            "initComplete": function(settings, json) {
                actualizarIndicadoresPasos(pasoActualGlobal);
            },
            "drawCallback": function(settings) {
                actualizarIndicadoresPasos(pasoActualGlobal);
                @if(!empty($autoSelectEntityId))
                if (!entidadSeleccionada && tablaEntidades) {
                    const $radio = $('#tabla-entidades .seleccionar-entidad[value="{{ (int) $autoSelectEntityId }}"]:not(:disabled)').first();
                    if ($radio.length) {
                        $radio.prop('checked', true).trigger('change');
                        $('#btn-siguiente-entidad').prop('disabled', false);
                        setTimeout(function() { $('#btn-siguiente-entidad').trigger('click'); }, 150);
                    }
                }
                @endif
            }
        });
    }

    function inicializarDataTableSorteos() {
        if (tablaSorteos) return;
        
        tablaSorteos = $('#tabla-sorteos').DataTable({
            "select": { style: "single" },
            "ordering": true,
            "sorting": true,
            "scrollX": true,
            "scrollCollapse": true,
            "language": {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
            "ajax": {
                "url": "{{ route('devolutions.lotteries') }}",
                "type": "GET",
                "data": function(d) {
                    d.entity_id = entidadSeleccionada.id;
                },
                "dataSrc": "lotteries"
            },
            "columns": [
                { "data": "id" },
                { "data": "name" },
                { 
                    "data": "draw_date",
                    "render": function(data, type, row) {
                        return data ? new Date(data).toLocaleDateString('es-ES') : 'N/A';
                    }
                },
                { "data": "description", "defaultContent": "N/A" },
                {
                    "data": null,
                    "render": function(data, type, row) {
                        return `
                            <div class="form-check">
                                <input class="form-check-input seleccionar-sorteo" type="radio" name="lottery_id" value="${row.id}" id="lottery_${row.id}" data-lottery-id="${row.id}" data-lottery-name="${row.name || ''}">
                                <label class="form-check-label" for="lottery_${row.id}">Seleccionar</label>
                            </div>
                        `;
                    },
                    "orderable": false
                }
            ],
            "columnDefs": [
                { "targets": -1, "className": "d-none" }
            ],
            "createdRow": function(row) {
                $(row).addClass('selectable-row').css('cursor', 'pointer');
            },
            "initComplete": function(settings, json) {
                actualizarIndicadoresPasos(pasoActualGlobal);
            },
            "drawCallback": function(settings) {
                actualizarIndicadoresPasos(pasoActualGlobal);
            }
        });
    }

    // Funci?n para inicializar DataTable de vendedores
    function inicializarDataTableVendedores() {
        if (tablaVendedores) {
            tablaVendedores.ajax.reload();
            return;
        }
        
        tablaVendedores = $('#tabla-vendedores').DataTable({
            "select": { style: "single" },
            "ordering": true,
            "sorting": true,
            "scrollX": true,
            "scrollCollapse": true,
            "language": {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
            "ajax": {
                "url": "{{ route('devolutions.sellers') }}",
                "type": "GET",
                "data": function(d) {
                    d.entity_id = entidadSeleccionada.id;
                },
                "dataSrc": "sellers"
            },
            "columns": [
                { "data": "id" },
                { 
                    "data": null,
                    "render": function(data, type, row) {
                        const nombre = row.user ? `${row.user.name || ''} ${row.user.last_name || ''}`.trim() : 'N/A';
                        return nombre || 'Sin nombre';
                    }
                },
                { 
                    "data": null,
                    "render": function(data, type, row) {
                        return row.user && row.user.email ? row.user.email : 'N/A';
                    }
                },
                { 
                    "data": null,
                    "render": function(data, type, row) {
                        return row.user && row.user.phone ? row.user.phone : 'N/A';
                    }
                },
                { 
                    "data": "status",
                    "render": function(data, type, row) {
                        const statusMap = { active: ['Activo', 'bg-success'], pending: ['Pendiente', 'bg-warning text-dark'], blocked: ['Bloqueado', 'bg-secondary'], inactive: ['Inactivo', 'bg-danger'] };
                        const [statusText, badgeClass] = statusMap[data] || ['Inactivo', 'bg-danger'];
                        return `<span class="badge ${badgeClass}">${statusText}</span>`;
                    }
                },
                {
                    "data": null,
                    "render": function(data, type, row) {
                        return `
                            <div class="form-check">
                                <input class="form-check-input seleccionar-vendedor" type="radio" name="seller_id" value="${row.id}" id="seller_${row.id}" data-seller-id="${row.id}">
                                <label class="form-check-label" for="seller_${row.id}">Seleccionar</label>
                            </div>
                        `;
                    },
                    "orderable": false
                }
            ],
            "columnDefs": [
                { "targets": -1, "className": "d-none" }
            ],
            "createdRow": function(row) {
                $(row).addClass('selectable-row').css('cursor', 'pointer');
            },
            "initComplete": function(settings, json) {
                actualizarIndicadoresPasos(pasoActualGlobal);
            },
            "drawCallback": function(settings) {
                actualizarIndicadoresPasos(pasoActualGlobal);
            }
        });
    }

    // Event listeners
    $(document).on('change', '.seleccionar-entidad', function() {
        const entityId = $(this).data('entity-id');
        const row = $(this).closest('tr');
        const rowData = tablaEntidades && tablaEntidades.row(row).data ? tablaEntidades.row(row).data() : null;
        const entityData = {
            id: entityId,
            name: rowData ? rowData.name : row.find('td:eq(2)').text(),
            province: rowData ? rowData.province : row.find('td:eq(3)').text(),
            city: rowData ? rowData.city : row.find('td:eq(4)').text(),
            administration: rowData ? rowData.administration_name : row.find('td:eq(5)').text(),
            image: rowData ? rowData.image : null,
            address: 'N/A',
            phone: 'N/A'
        };
        
        entidadSeleccionada = entityData;
        
        // Mostrar informaci?n de la entidad en varias secciones
        $('#entity-name').text(entityData.name);
        $('#entity-location').text(`${entityData.province}, ${entityData.city}`);
        const $imgDiv = $('#entity-info-image');
        if (entityData.image) {
            $imgDiv.css('background-image', "url('{{ asset('uploads/') }}/" + entityData.image.replace(/'/g, "\\'") + "')");
            $imgDiv.find('i').remove();
        } else {
            $imgDiv.css('background-image', '');
            if (!$imgDiv.find('i').length) $imgDiv.append('<i class="ri-building-line"></i>');
        }
        $('#entity-info').show();
        
        // Tambi?n en la secci?n de opciones
        $('#opcion-entity-name').text(entityData.name);
        $('#opcion-entity-province').text(entityData.province);
        $('#opcion-entity-admin').text(entityData.administration);
        $('#opcion-entity-province-2').text(entityData.province);
        $('#opcion-entity-city').text(entityData.city);
        $('#opcion-entity-address').text(entityData.address);
        $('#opcion-entity-phone').text(entityData.phone);
        
        $('#btn-siguiente-entidad').prop('disabled', false);
    });

    $(document).on('change', '.seleccionar-vendedor', function() {
        const sellerId = $(this).data('seller-id');
        const row = $(this).closest('tr');
        vendedorSeleccionado = {
            id: sellerId,
            name: row.find('td:eq(1)').text(),
            email: row.find('td:eq(2)').text()
        };
        $('#btn-siguiente-vendedor').prop('disabled', false);
    });

    $(document).on('change', '.seleccionar-sorteo', function() {
        const lotteryId = $(this).data('lottery-id');
        const lotteryName = $(this).data('lottery-name');
        sorteoSeleccionado = { 
            id: lotteryId,
            name: lotteryName,
            lottery_name: lotteryName
        };
        console.log('Sorteo seleccionado:', sorteoSeleccionado);
        $('#btn-siguiente-sorteo').prop('disabled', false);
    });

    // Clic en fila para seleccionar (tablas con selectable-row); no permitir seleccionar entidad inactiva
    $(document).on('click', '#tabla-entidades tbody tr.selectable-row', function(e) {
        if ($(this).hasClass('entity-inactive')) return;
        if (!$(e.target).closest('.form-check').length) {
            $(this).find('.seleccionar-entidad').prop('checked', true).trigger('change');
        }
    });
    $(document).on('click', '#tabla-vendedores tbody tr.selectable-row', function(e) {
        if (!$(e.target).closest('.form-check').length) {
            $(this).find('.seleccionar-vendedor').prop('checked', true).trigger('change');
        }
    });
    $(document).on('click', '#tabla-sorteos tbody tr.selectable-row', function(e) {
        if (!$(e.target).closest('.form-check').length) {
            $(this).find('.seleccionar-sorteo').prop('checked', true).trigger('change');
        }
    });

    // Navegaci?n entre pasos
    $('#btn-siguiente-entidad').click(function() {
        if (entidadSeleccionada) {
            mostrarPaso('paso-opcion');
        }
    });

    // Botones de selecci?n de tipo de devoluci?n
    $('#btn-devolucion-vendedor').click(function() {
        // Resetear participaciones seleccionadas al cambiar tipo
        participacionesAsignadas = [];
        actualizarResumenAsignacion();
        
        tipoDevolucion = 'vendedor';
        construirWizardPasos();
        mostrarPaso('paso-vendedor');
        inicializarDataTableVendedores();
    });

    $('#btn-devolucion-administracion').click(function() {
        // Resetear participaciones seleccionadas al cambiar tipo
        participacionesAsignadas = [];
        actualizarResumenAsignacion();
        
        tipoDevolucion = 'administracion';
        construirWizardPasos();
        mostrarPaso('paso-sorteo');
        inicializarDataTableSorteos();
    });

    $('#btn-anulacion-participaciones').click(function() {
        // Resetear participaciones seleccionadas al cambiar tipo
        participacionesAsignadas = [];
        actualizarResumenAsignacion();
        
        tipoDevolucion = 'anulacion';
        construirWizardPasos();
        mostrarPaso('paso-sorteo');
        inicializarDataTableSorteos();
    });

    $('#btn-siguiente-vendedor').click(function() {
        if (vendedorSeleccionado) {
            // Para vendedor, despu?s de seleccionar vendedor pasamos a sorteo
            mostrarPaso('paso-sorteo');
            inicializarDataTableSorteos();
        }
    });

    $('#btn-siguiente-sorteo').click(function() {
        if (sorteoSeleccionado) {
            mostrarPaso('paso-participaciones');
            // Cargar sets seg?n el tipo de devoluci?n
            if (tipoDevolucion === 'vendedor') {
                cargarReservasVendedor();
            } else {
            cargarReservasEntidad();
            }
        }
    });

    // Botones de volver
    $('#btn-volver-entidad').click(function() {
        mostrarPaso('paso-entidad');
    });

    // Desde paso sorteo: volver a vendedores (si tipo vendedor) o a opciones (si administraci?n/anulaci?n)
    $('#btn-volver-desde-sorteo').click(function() {
        if (tipoDevolucion === 'vendedor') {
            mostrarPaso('paso-vendedor');
        } else {
            mostrarPaso('paso-opcion');
        }
    });

    $('#btn-volver-opcion').click(function() {
        mostrarPaso('paso-opcion');
    });

    $('#back-option-button').click(function() {
        mostrarPaso('paso-entidad');
    });

    $('#btn-volver-sorteo-desde-participaciones').click(function() {
        mostrarPaso('paso-sorteo');
    });

    $('#btn-volver-participaciones-final').click(function() {
        // Siempre volver a participaciones (vendedor) o a opciones (administraci?n), seg?n el texto del bot?n
        if ($('#btn-volver-text').text().indexOf('Opciones') !== -1) {
            mostrarPaso('paso-opcion');
        } else {
            mostrarPaso('paso-participaciones');
        }
    });

    // Funcionalidad de asignaci?n de participaciones
    function actualizarResumenAsignacion() {
        $('#resumen-asignacion').show();
        $('#estado-vacio-resumen').addClass('d-none');
        
        if (participacionesAsignadas.length === 0) {
            $('#estado-vacio-resumen').removeClass('d-none');
            $('#lista-participaciones-asignadas').hide();
            // Mostrar bot?n para continuar sin participaciones si hay reserva o set seleccionado
            if (reservaSeleccionada || setSeleccionado) {
                $('#btn-continuar-sin-participaciones-container').show();
            } else {
                $('#btn-continuar-sin-participaciones-container').hide();
            }
            actualizarResumenLiquidacion();
        } else {
            $('#lista-participaciones-asignadas').show();
            $('#btn-continuar-sin-participaciones-container').hide();
            $('#total-asignadas').text(participacionesAsignadas.length);
            
            // Calcular cu?ntos sets diferentes se han seleccionado
            const setsUnicos = [...new Set(participacionesAsignadas.map(p => p.set_id))];
            $('#total-sets').text(setsUnicos.length);
            
            // Actualizar resumen de liquidaci?n
            actualizarResumenLiquidacion();
            
            // Generar grid de participaciones
            const gridHtml = participacionesAsignadas.map(participation => {
                const fecha = new Date(participation.assigned_at);
                const fechaStr = fecha.toLocaleDateString('es-ES');
                const horaStr = fecha.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
                
                return `
                    <div class="participacion-item">
                        <div class="d-flex align-items-center">
                            <div class="participacion-icon">
                                <img src="{{url('assets/ticket.svg')}}" alt="" width="20px">
                            </div>
                            <div class="participacion-info">
                                <div class="participacion-numero">${participation.participation_code}</div>
                                <div class="participacion-fecha">
                                    <i class="ri-calendar-line"></i>
                                    <span>${fechaStr} - ${horaStr}h</span>
                                </div>
                                <div class="participacion-fecha">
                                    <i class="ri-folder-line"></i>
                                    <span style="font-size: 0.85em; color: #888;">${participation.set_name || 'Set desconocido'}</span>
                                </div>
                                <span class="participacion-estado">Asignada</span>
                            </div>
                        </div>
                        <button class="btn-eliminar-participacion" onclick="eliminarParticipacion('${participation.participation_code}')">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    </div>
                `;
            }).join('');

            $('#grid-participaciones').html(gridHtml);
        }
    }

    // Funci?n para eliminar participaci?n
    window.eliminarParticipacion = function(codigo) {
        participacionesAsignadas = participacionesAsignadas.filter(p => p.participation_code !== codigo);
        actualizarResumenAsignacion();
    };

    // Funci?n para actualizar el resumen de liquidaci?n
    function actualizarResumenLiquidacion() {
        console.log('=== ACTUALIZANDO RESUMEN LIQUIDACI��N ===');
        console.log('entidadSeleccionada:', entidadSeleccionada);
        console.log('sorteoSeleccionado:', sorteoSeleccionado);
        console.log('participacionesAsignadas:', participacionesAsignadas);
        
        // Validar solo que haya entidad y sorteo seleccionados (las participaciones pueden estar vac?as)
        if (!entidadSeleccionada || !sorteoSeleccionado) {
            console.log('Limpiando resumen - no hay entidad o sorteo seleccionado');
            // Limpiar resumen si no hay datos b?sicos
            $('#liquidacion-total-participaciones').text('0');
            $('#liquidacion-ventas-registradas').text('0');
            $('#liquidacion-participaciones-devueltas').text('0');
            $('#liquidacion-disponibles').text('0');
            $('#liquidacion-total-liquidacion').text('0,00���');
            $('#liquidacion-pagos-registrados').text('0,00���');
            $('#liquidacion-total-pagar').text('0,00���');
            $('#liquidacion-importe-total').text('0,00���');
            liquidacionSummaryActual = null;
            specialPrizeRequirement = { required: false, max_series: 0 };
            specialPrizeRequirementKey = null;
            specialPrizeAssignments = {};
            specialPrizeIntroShown = false;
            renderSpecialPrizeSection();
            return;
        }

        // Permitir continuar sin participaciones (solo para registrar pago)
        const participationIds = participacionesAsignadas.length > 0 ? participacionesAsignadas.map(p => p.id) : [];
        console.log('Enviando IDs de participaciones:', participationIds);
        console.log('Set seleccionado:', setSeleccionado);

        // Preparar datos para el resumen: con reserva usamos solo reserve_id para que los totales sean siempre de la reserva completa
        const datosResumen = {
            entity_id: entidadSeleccionada.id,
            lottery_id: sorteoSeleccionado.id,
            set_id: reservaSeleccionada ? null : (setSeleccionado ? setSeleccionado.id : null),
            reserve_id: reservaSeleccionada ? reservaSeleccionada.id : null,
            participations: participationIds
        };

        // Agregar seller_id y tipo_devolucion si es devoluci?n de vendedor (para c?lculo correcto del resumen)
        if (tipoDevolucion === 'vendedor' && vendedorSeleccionado) {
            datosResumen.seller_id = vendedorSeleccionado.id;
            datosResumen.tipo_devolucion = 'vendedor';
        }

        // Obtener resumen del servidor
        $.ajax({
            url: "{{ route('devolutions.liquidation-summary') }}",
            method: 'GET',
            data: datosResumen,
            success: function(response) {
                console.log('Respuesta del servidor:', response);
                if (response.success) {
                    const summary = response.summary;
                    liquidacionSummaryActual = summary;
                    console.log('Resumen calculado:', summary);
                    
                    $('#liquidacion-total-participaciones').text(summary.total_participations);
                    $('#liquidacion-ventas-registradas').text(summary.ventas_registradas !== undefined ? summary.ventas_registradas : summary.sold_participations);
                    $('#liquidacion-participaciones-devueltas').text(summary.returned_participations);
                    $('#liquidacion-disponibles').text(summary.available_participations);
                    if (tipoDevolucion !== 'vendedor' && (summary.returned_digitales_auto > 0 || summary.returned_fisicas_manual > 0)) {
                        const partes = [];
                        if (summary.returned_fisicas_manual > 0) {
                            partes.push(summary.returned_fisicas_manual + ' f?sicas seleccionadas');
                        }
                        if (summary.returned_digitales_auto > 0) {
                            partes.push(summary.returned_digitales_auto + ' digitales autom?ticas (pool)');
                        }
                        $('#liquidacion-devueltas-detalle').text(partes.join(' ? '));
                    } else {
                        $('#liquidacion-devueltas-detalle').text('');
                    }
                    $('#liquidacion-total-liquidacion').text(summary.total_liquidation.toFixed(2) + '���');
                    $('#liquidacion-pagos-registrados').text(summary.registered_payments.toFixed(2) + '���');
                    $('#liquidacion-total-pagar').text(summary.total_to_pay.toFixed(2) + '���');
                    $('#liquidacion-importe-total').text(summary.total_to_pay.toFixed(2) + '���');
                    
                    // Actualizar informaci?n del ticket
                    $('#liquidacion-ticket-number').text('#' + summary.total_participations);
                    specialPrizeRequirement = summary.special_prize_requirement || { required: false, max_series: 0 };
                    const nextRequirementKey = JSON.stringify({
                        lottery: sorteoSeleccionado?.id || null,
                        numero: specialPrizeRequirement.premio_especial_numero || null,
                        serie: specialPrizeRequirement.premio_especial_serie || null,
                        fraccion: specialPrizeRequirement.premio_especial_fraccion || null,
                        maxSeries: specialPrizeRequirement.max_series || 0,
                    });
                    if (specialPrizeRequirementKey !== nextRequirementKey) {
                        specialPrizeAssignments = {};
                        specialPrizeIntroShown = false;
                        specialPrizeRequirementKey = nextRequirementKey;
                    }
                    if (!specialPrizeRequirement.required) {
                        specialPrizeAssignments = {};
                        specialPrizeIntroShown = false;
                    }
                    renderSpecialPrizeSection();
                    
                    console.log('Resumen actualizado en la interfaz');
                } else {
                    console.error('Error en respuesta del servidor:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar resumen de liquidaci?n:', error);
                console.error('Detalles del error:', xhr.responseText);
            }
        });
    }

    // Funci?n para cargar reservas de la entidad (asignaci?n/devoluci?n por reserva)
    function cargarReservasEntidad() {
        if (!entidadSeleccionada || !sorteoSeleccionado) return;
        
        $.ajax({
            url: "{{ route('devolutions.reserves-by-entity') }}",
            method: 'GET',
            data: {
                entity_id: entidadSeleccionada.id,
                lottery_id: sorteoSeleccionado.id
            },
            success: function(response) {
                const selector = $('#selector-reserva');
                selector.empty().append('<option value="">Seleccionar reserva...</option>');
                reservaSeleccionada = null;
                
                if (response.success && response.reserves && response.reserves.length > 0) {
                    response.reserves.forEach(reserve => {
                        selector.append(`<option value="${reserve.id}" data-display-label="${reserve.display_label}">${reserve.display_label}</option>`);
                    });
                    if (response.reserves.length === 1) {
                        reservaSeleccionada = { id: response.reserves[0].id, display_label: response.reserves[0].display_label };
                        selector.val(reservaSeleccionada.id);
                        $('#wrapper-seleccion-reserva').addClass('d-none');
                    } else {
                        $('#wrapper-seleccion-reserva').removeClass('d-none');
                        $('#contenedor-selector-reserva').removeClass('d-none');
                    }
                } else {
                    $('#wrapper-seleccion-reserva').removeClass('d-none');
                    $('#contenedor-selector-reserva').removeClass('d-none');
                    mostrarMensaje('No hay reservas disponibles para este sorteo', 'warning');
                }
                actualizarResumenAsignacion();
                if (typeof actualizarDisponiblesParaDevolver === 'function') actualizarDisponiblesParaDevolver();
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar reservas:', error);
                mostrarMensaje('Error al cargar las reservas de la entidad', 'error');
            }
        });
    }

    // Funci?n para cargar reservas del vendedor (devoluci?n vendedor ��� entidad)
    function cargarReservasVendedor() {
        if (!entidadSeleccionada || !sorteoSeleccionado || !vendedorSeleccionado) return;
        
        $.ajax({
            url: "{{ route('devolutions.reserves-by-entity') }}",
            method: 'GET',
            data: {
                entity_id: entidadSeleccionada.id,
                lottery_id: sorteoSeleccionado.id,
                seller_id: vendedorSeleccionado.id
            },
            success: function(response) {
                const selector = $('#selector-reserva');
                selector.empty().append('<option value="">Seleccionar reserva...</option>');
                reservaSeleccionada = null;
                
                if (response.success && response.reserves && response.reserves.length > 0) {
                    response.reserves.forEach(reserve => {
                        selector.append(`<option value="${reserve.id}" data-display-label="${reserve.display_label}">${reserve.display_label}</option>`);
                    });
                    if (response.reserves.length === 1) {
                        reservaSeleccionada = { id: response.reserves[0].id, display_label: response.reserves[0].display_label };
                        selector.val(reservaSeleccionada.id);
                        $('#wrapper-seleccion-reserva').addClass('d-none');
                    } else {
                        $('#wrapper-seleccion-reserva').removeClass('d-none');
                        $('#contenedor-selector-reserva').removeClass('d-none');
                    }
                } else {
                    $('#wrapper-seleccion-reserva').removeClass('d-none');
                    $('#contenedor-selector-reserva').removeClass('d-none');
                    mostrarMensaje('No hay reservas con participaciones de este vendedor', 'warning');
                }
                actualizarResumenAsignacion();
                if (typeof actualizarDisponiblesParaDevolver === 'function') actualizarDisponiblesParaDevolver();
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar reservas del vendedor:', error);
                mostrarMensaje('Error al cargar las reservas del vendedor', 'error');
            }
        });
    }

    // Event listener para selecci?n de reserva
    $('#selector-reserva').on('change', function() {
        const reserveId = $(this).val();
        const selectedOption = $(this).find('option:selected');
        
        if (reserveId) {
            reservaSeleccionada = {
                id: reserveId,
                display_label: selectedOption.data('display-label') || selectedOption.text()
            };
        } else {
            reservaSeleccionada = null;
        }
        actualizarResumenAsignacion();
        actualizarDisponiblesParaDevolver();
    });

    // Actualizar bloque "Participaciones disponibles para devolver" (entidad o vendedor en la reserva)
    function actualizarDisponiblesParaDevolver() {
        const $bloque = $('#bloque-disponibles-devolver');
        if (!reservaSeleccionada || !entidadSeleccionada || !sorteoSeleccionado) {
            $bloque.hide();
            return;
        }
        if (tipoDevolucion === 'vendedor' && !vendedorSeleccionado) {
            $bloque.hide();
            return;
        }
        const datos = {
            entity_id: entidadSeleccionada.id,
            lottery_id: sorteoSeleccionado.id,
            reserve_id: reservaSeleccionada.id,
            participations: []
        };
        if (tipoDevolucion === 'vendedor' && vendedorSeleccionado) {
            datos.seller_id = vendedorSeleccionado.id;
            datos.tipo_devolucion = 'vendedor';
        }
        const esDevolucionVendedor = tipoDevolucion === 'vendedor';
        $('#texto-tipo-disponibles').text(
            esDevolucionVendedor
                ? 'Solo f?sicas asignadas al vendedor (las digitales no se asignan)'
                : 'F?sicas seleccionables; digitales en pool de la entidad'
        );
        $.ajax({
            url: "{{ route('devolutions.liquidation-summary') }}",
            method: 'GET',
            data: datos,
            success: function(response) {
                if (response.success && response.summary) {
                    const s = response.summary;
                    const total = s.available_to_return !== undefined ? s.available_to_return : s.available_participations;
                    const fisicas = s.available_to_return_fisicas !== undefined ? s.available_to_return_fisicas : (s.total_fisicas || 0);
                    const digitales = s.available_to_return_digitales !== undefined ? s.available_to_return_digitales : (s.total_digitales || 0);
                    if (esDevolucionVendedor) {
                        $('#disponibles-devolver-total').text(fisicas);
                        $('#disponibles-devolver-desglose').hide();
                        $('#mensaje-digitales-entidad').hide();
                    } else {
                        $('#disponibles-devolver-total').text(total);
                        $('#disponibles-devolver-fisicas').text(fisicas);
                        $('#disponibles-devolver-digitales').text(digitales);
                        $('#disponibles-devolver-desglose').show();
                        $('#mensaje-digitales-entidad').show();
                    }
                    $bloque.show();
                } else {
                    $bloque.hide();
                }
            },
            error: function() {
                $bloque.hide();
            }
        });
    }

    // Funci?n para validar participaciones (por reserva o por set legacy)
    function validarParticipacionesDisponibles(desde, hasta, participationId) {
        return new Promise((resolve, reject) => {
            const datosValidacion = {
                    entity_id: entidadSeleccionada.id,
                    lottery_id: sorteoSeleccionado.id,
                    desde: desde,
                    hasta: hasta,
                    participation_id: participationId,
                    _token: '{{ csrf_token() }}'
            };
            if (reservaSeleccionada) {
                datosValidacion.reserve_id = reservaSeleccionada.id;
            } else if (setSeleccionado) {
                datosValidacion.set_id = setSeleccionado.id;
            }

            // Agregar seller_id si es devoluci?n de vendedor
            if (tipoDevolucion === 'vendedor' && vendedorSeleccionado) {
                datosValidacion.seller_id = vendedorSeleccionado.id;
            }

            $.ajax({
                url: "{{ route('devolutions.validate') }}",
                method: 'POST',
                data: datosValidacion,
                success: function(response) {
                    if (response.success) {
                        resolve(response);
                    } else {
                        reject(response.message || 'Error al validar participaciones');
                    }
                },
                error: function(xhr, status, error) {
                    reject('Error de conexi?n: ' + error);
                }
            });
        });
    }

    // Event listener para asignar participaciones (requiere reserva o set seleccionado)
    $('#btn-asignar-participacion').click(function() {
        if (!reservaSeleccionada && !setSeleccionado) {
            mostrarMensaje('Por favor selecciona una reserva antes de asignar participaciones', 'warning');
            return;
        }

        const desde = $('#rango-desde').val();
        const hasta = $('#rango-hasta').val();
        const unidad = $('#participacion-unidad').val();
        const displayName = reservaSeleccionada ? reservaSeleccionada.display_label : (setSeleccionado ? setSeleccionado.name : '');

        if (desde && hasta) {
            $('#btn-asignar-participacion').prop('disabled', true).text('Validando...');
            
            validarParticipacionesDisponibles(desde, hasta, null)
                .then(response => {
                    if (response.participations && response.participations.length > 0) {
                        response.participations.forEach(participation => {
                            const participacionExistente = participacionesAsignadas.find(p => p.id === participation.id);
                            if (!participacionExistente) {
                                participacionesAsignadas.push({
                                    id: participation.id,
                                    number: participation.number,
                                    participation_code: participation.participation_code,
                                    set_id: participation.set_id || (setSeleccionado && setSeleccionado.id),
                                    set_name: participation.set_name || displayName,
                                    assigned_at: new Date().toISOString()
                                });
                            }
                        });
                        actualizarResumenAsignacion();
                        mostrarMensaje('Participaciones asignadas correctamente', 'success');
                    } else {
                        mostrarMensaje('No hay participaciones disponibles en ese rango', 'warning');
                    }
                })
                .catch(error => {
                    mostrarMensaje(error, 'error');
                })
                .finally(() => {
                    $('#btn-asignar-participacion').prop('disabled', false).text('Asignar');
                    $('#rango-desde').val('');
                    $('#rango-hasta').val('');
                });
        } else if (unidad) {
            $('#btn-asignar-participacion').prop('disabled', true).text('Validando...');
            
            validarParticipacionesDisponibles(null, null, unidad)
                .then(response => {
                    if (response.participations && response.participations.length > 0) {
                        const participation = response.participations[0];
                        const participacionExistente = participacionesAsignadas.find(p => p.id === participation.id);
                        
                        if (!participacionExistente) {
                            participacionesAsignadas.push({
                                id: participation.id,
                                number: participation.number,
                                participation_code: participation.participation_code,
                                set_id: participation.set_id || (setSeleccionado && setSeleccionado.id),
                                set_name: participation.set_name || displayName,
                                assigned_at: new Date().toISOString()
                            });
                            actualizarResumenAsignacion();
                            mostrarMensaje('Participaci?n asignada correctamente', 'success');
                        } else {
                            mostrarMensaje('Esta participaci?n ya est? asignada', 'warning');
                        }
                    } else {
                        mostrarMensaje('La participaci?n no est? disponible', 'warning');
                    }
                })
                .catch(error => {
                    mostrarMensaje(error, 'error');
                })
                .finally(() => {
                    $('#btn-asignar-participacion').prop('disabled', false).text('Asignar');
                    $('#participacion-unidad').val('');
                });
        } else {
            alert('Debes especificar un rango o una participaci?n individual');
        }
    });

    // Event listener para terminar asignaci?n (ir directo a liquidaci?n)
    $('#btn-terminar-asignacion').click(function() {
        // Para anulaci?n, ir al paso de anulaci?n
        if (tipoDevolucion === 'anulacion') {
            mostrarPaso('paso-anulacion');
            return;
        }
        
        // Para devoluci?n de vendedor, no se requieren participaciones espec?ficas
        if (tipoDevolucion === 'vendedor') {
            mostrarPaso('paso-liquidacion');
            configurarLiquidacionPorTipo();
            return;
        }

        // Para devoluci?n de administraci?n, permitir continuar sin participaciones
        if (participacionesAsignadas.length === 0) {
            const confirmContinue = confirm('No has seleccionado participaciones para devolver. ?Deseas continuar solo para registrar una liquidaci?n?');
            if (!confirmContinue) {
                return;
            }
        }

        // Ir directo a liquidaci?n
        mostrarPaso('paso-liquidacion');
        configurarLiquidacionPorTipo();
    });

    // Event listener para continuar sin participaciones (ir directo a liquidaci?n)
    $('#btn-continuar-sin-participaciones').click(function() {
        if (tipoDevolucion === 'vendedor') {
            mostrarPaso('paso-liquidacion');
            configurarLiquidacionPorTipo();
            return;
        }

        const confirmContinue = confirm('?Deseas continuar sin seleccionar participaciones? Solo podr?s registrar un pago de liquidaci?n.');
        if (!confirmContinue) {
            return;
        }

        // Ir directo a liquidaci?n
        mostrarPaso('paso-liquidacion');
        configurarLiquidacionPorTipo();
    });

    // Funci?n para configurar la liquidaci?n seg?n el tipo
    function configurarLiquidacionPorTipo() {
        if (tipoDevolucion === 'vendedor') {
            // Devoluci?n vendedor: liquidar por las participaciones que QUEDAN con el vendedor (ej. 90 �� 6��� = 540���)
            $('#liquidacion-titulo').text('Liquidaci?n de Vendedor');
            $('#liquidacion-subtitulo').html('<i>Registra pagos por las participaciones que siguen asignadas al vendedor</i>');
            $('#liquidacion-resumen-subtitulo').text('Resumen Devoluci?n Vendedor');
            $('#btn-volver-text').text('Volver a Participaciones');
            $('#liquidacion-vendedor-container').hide();
            $('#liquidacion-administracion-container').show();
            cargarParticipacionesParaLiquidacion();
            actualizarResumenLiquidacion();
        } else {
            // Liquidaci?n de administraci?n: volver a participaciones (igual que vendedor)
            $('#liquidacion-titulo').text('Liquidaci?n de Administraci?n');
            $('#liquidacion-subtitulo').html('<i>Procesa la liquidaci?n de participaciones</i>');
            $('#liquidacion-resumen-subtitulo').text('Resumen Devoluci?n Administraci?n');
            $('#btn-volver-text').text('Volver a Participaciones');
            $('#liquidacion-vendedor-container').hide();
            $('#liquidacion-administracion-container').show();
            cargarParticipacionesParaLiquidacion();
            actualizarResumenLiquidacion();
        }
    }

    // Funci?n para cargar participaciones para liquidaci?n
    function cargarParticipacionesParaLiquidacion() {
        let html = '';
        
        if (participacionesAsignadas.length === 0) {
            html = `
                <div class="alert alert-info" role="alert">
                    <i class="ri-information-line me-2"></i>
                    No hay participaciones seleccionadas. Puedes continuar para registrar solo un pago de liquidaci?n.
                </div>
            `;
        } else {
            participacionesAsignadas.forEach(participation => {
                html += `
                    <div class="liquidacion-card">
                        <div class="liquidacion-header">
                            <div class="liquidacion-icon">
                                <img src="{{url('assets/ticket.svg')}}" alt="" width="25px">
                            </div>
                            <div class="liquidacion-info">
                                <h5>${participation.participation_code}</h5>
                                <small>Participaci?n #${participation.number}</small>
                            </div>
                        </div>
                        <div class="liquidacion-stats">
                            <div class="stat-item">
                                <div class="stat-number">5���</div>
                                <div class="stat-label">Valor</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <div class="form-check">
                                <input class="form-check-input liquidacion-option" type="radio" name="liquidacion_${participation.id}" value="devolver" id="devolver_${participation.id}" checked>
                                <label class="form-check-label" for="devolver_${participation.id}">
                                    <i class="ri-arrow-go-back-line me-1"></i>Devolver
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input liquidacion-option" type="radio" name="liquidacion_${participation.id}" value="vender" id="vender_${participation.id}">
                                <label class="form-check-label" for="vender_${participation.id}">
                                    <i class="ri-money-dollar-circle-line me-1"></i>Vender
                                </label>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        $('#liquidacion-participaciones').html(html);
    }

    // Event listener para procesar liquidaci?n
    $('#btn-procesar-liquidacion').click(function() {
        // Permitir procesar liquidaci?n sin participaciones (solo para registrar pago)
        $('#btn-procesar-liquidacion').prop('disabled', true).text('Procesando...');

        const liquidacion = {
            devolver: [],
            vender: []
        };

        participacionesAsignadas.forEach(participation => {
            const opcion = $(`input[name="liquidacion_${participation.id}"]:checked`).val();
            if (opcion === 'devolver') {
                liquidacion.devolver.push(participation.id);
            } else if (opcion === 'vender') {
                liquidacion.vender.push(participation.id);
            }
        });

        // Enviar datos al servidor: liquidacion.devolver y liquidacion.vender desde los radios; reserve_id si hay reserva
        const payload = {
            entity_id: entidadSeleccionada.id,
            lottery_id: sorteoSeleccionado.id,
            participations: participacionesAsignadas.map(p => p.id),
            return_reason: 'Devoluci?n de entidad a administraci?n',
            tipo_devolucion: tipoDevolucion || 'administracion',
            liquidacion: liquidacion,
            _token: '{{ csrf_token() }}'
        };
        if (reservaSeleccionada) payload.reserve_id = reservaSeleccionada.id;
        // El backend exige liquidacion.pagos con al menos un pago con importe; si no hay, enviar array vac?o y el backend puede rechazar
        if (!payload.liquidacion.pagos) payload.liquidacion.pagos = [];
        function enviarLiquidacionEntidad(payloadData) {
            $.ajax({
                url: "{{ route('devolutions.store') }}",
                method: 'POST',
                data: payloadData,
                success: function(response) {
                    if (response.queued && response.success) {
                        try {
                            sessionStorage.setItem('partilot_bg_job_started', JSON.stringify({
                                title: 'Tramitaci?n en segundo plano',
                                text: 'La operaci?n est? en cola. Puedes seguir navegando; al terminar ver?s un aviso y el listado se actualizar?.',
                                type: 'notice'
                            }));
                        } catch (e) {}
                        window.location.href = "{{ route('devolutions.index') }}";
                        return;
                    }
                    if (response.success) {
                        mostrarMensaje('Liquidaci?n procesada correctamente', 'success');
                        setTimeout(() => {
                            window.location.href = "{{ route('devolutions.index') }}";
                        }, 2000);
                    } else {
                        mostrarMensaje(response.message || 'Error al procesar la liquidaci?n', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    if (manejarAdvertenciaLiquidacionVendedores(xhr, payloadData, enviarLiquidacionEntidad)) {
                        return;
                    }
                    mostrarMensaje(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error de conexi?n al procesar la liquidaci?n', 'error');
                },
                complete: function() {
                    $('#btn-procesar-liquidacion').prop('disabled', false).text('Procesar Liquidaci?n');
                }
            });
        }
        enviarLiquidacionEntidad(payload);
    });

    // Funci?n para mostrar mensajes
    function mostrarMensaje(mensaje, tipo) {
        const alertClass = tipo === 'success' ? 'alert-success' : 
                          tipo === 'warning' ? 'alert-warning' : 
                          tipo === 'error' ? 'alert-danger' : 'alert-info';
       
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        $('.page-title-box').after(alertHtml);
        
        setTimeout(() => {
            $('.alert').fadeOut();
        }, 5000);
    }

    // Event listeners para inputs
    $('#rango-desde, #rango-hasta').on('input', function() {
        const desde = $('#rango-desde').val();
        const hasta = $('#rango-hasta').val();
        
        if (desde && hasta) {
            $('#participacion-unidad').val('').prop('disabled', true);
        } else {
            $('#participacion-unidad').prop('disabled', false);
        }
    });

    $('#participacion-unidad').on('input', function() {
        const unidad = $(this).val();
        
        if (unidad) {
            $('#rango-desde, #rango-hasta').val('').prop('disabled', true);
        } else {
            $('#rango-desde, #rango-hasta').prop('disabled', false);
        }
    });

    // Funci?n para actualizar el total pagado
    function actualizarTotalPagado() {
        const efectivoMonto = parseFloat($('#pago-efectivo-monto').val()) || 0;
        const bizumMonto = parseFloat($('#pago-bizum-monto').val()) || 0;
        const transferenciaMonto = parseFloat($('#pago-transferencia-monto').val()) || 0;
        
        const totalPagado = efectivoMonto + bizumMonto + transferenciaMonto;
        $('#total-pagado').text(totalPagado.toFixed(2) + '���');
        
        // Calcular pendiente
        const totalAPagar = parseFloat($('#liquidacion-total-pagar').text().replace('���', '').replace(',', '.')) || 0;
        const pendiente = totalAPagar - totalPagado;
        
        $('#total-pendiente').text(pendiente.toFixed(2) + '���');
        
        if (totalPagado > 0) {
            $('#pendiente-container').show();
            // Cambiar color seg?n si est? completo o no
            if (pendiente <= 0) {
                $('#total-pendiente').removeClass('text-warning').addClass('text-success');
            } else {
                $('#total-pendiente').removeClass('text-success').addClass('text-warning');
            }
        } else {
            $('#pendiente-container').hide();
        }
    }

    function obtenerTotalDecimosObjetivo() {
        const disponibles = parseInt(liquidacionSummaryActual?.available_participations || 0, 10);
        if (!Number.isNaN(disponibles) && disponibles >= 0) {
            return disponibles;
        }
        return Math.max(0, parseInt(liquidacionSummaryActual?.total_participations || 0, 10));
    }

    function obtenerImportePorDecimo() {
        const totalToPay = parseFloat(liquidacionSummaryActual?.total_to_pay || 0);
        const totalDecimos = obtenerTotalDecimosObjetivo();
        if (!totalDecimos) return 0;
        return totalToPay / totalDecimos;
    }

    function obtenerDecimosAsignados() {
        let total = 0;
        Object.values(specialPrizeAssignments).forEach(fracciones => {
            total += fracciones.size;
        });
        return total;
    }

    function renderSpecialPrizeTable() {
        const $tbody = $('#special-prize-table-body');
        const series = Object.keys(specialPrizeAssignments)
            .map(Number)
            .sort((a, b) => a - b);

        if (!series.length) {
            $tbody.html('<tr><td colspan="2" class="text-center text-muted">Sin asignaciones</td></tr>');
            return;
        }

        const rows = series.map(serie => {
            const fraccionesOrdenadas = Array.from(specialPrizeAssignments[serie]).sort((a, b) => a - b);
            return `<tr>
                <td>${serie}</td>
                <td>${fraccionesOrdenadas.join('-')}</td>
            </tr>`;
        });
        $tbody.html(rows.join(''));
    }

    function renderSpecialPrizeSection() {
        const required = !!specialPrizeRequirement?.required;
        const $bloque = $('#bloque-premio-especial');
        if (!liquidacionSummaryActual) {
            $bloque.hide();
            return;
        }

        $bloque.show();
        const maxSeries = parseInt(specialPrizeRequirement.max_series || 0, 10);
        const objetivo = obtenerTotalDecimosObjetivo();
        const asignados = obtenerDecimosAsignados();
        const restantes = Math.max(0, objetivo - asignados);
        const importePorDecimo = obtenerImportePorDecimo();
        const importeRestante = restantes * importePorDecimo;

        if (required) {
            $('#special-prize-badge').removeClass('bg-warning text-dark bg-info').addClass('bg-danger').text('Obligatorio');
            $('#special-prize-requirement-alert')
                .removeClass('alert-info')
                .addClass('alert-warning')
                .text(`Debes completar ${objetivo} d?cimos para cerrar la liquidaci?n. Premio especial ${specialPrizeRequirement.premio_especial_numero || '-'} ? Serie ${specialPrizeRequirement.premio_especial_serie || '-'} ? Fracci?n ${specialPrizeRequirement.premio_especial_fraccion || '-'} ? Series v?lidas: 1-${maxSeries}`);
            $('#special-prize-resumen-text').text('Este sorteo requiere informar serie/fracci?n para completar la liquidaci?n.');
        } else {
            $('#special-prize-badge').removeClass('bg-danger bg-info').addClass('bg-warning text-dark').text('Opcional');
            $('#special-prize-requirement-alert')
                .removeClass('alert-warning')
                .addClass('alert-info')
                .text('Puedes completar esta asignaci?n de forma opcional en este sorteo.');
            $('#special-prize-resumen-text').text(`Rango de series permitido: 1-${maxSeries > 0 ? maxSeries : 'N/A'}.`);
        }
        $('#special-prize-restante-decimos').text(restantes);
        $('#special-prize-restante-importe').text(importeRestante.toFixed(2).replace('.', ',') + '���');

        renderSpecialPrizeTable();

        if (required && !specialPrizeIntroShown) {
            const modalEl = document.getElementById('modal-premio-especial-obligatorio');
            if (modalEl && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
            specialPrizeIntroShown = true;
        }
    }

    function agregarFraccionesASerie(serie, fracciones) {
        const maxSeries = parseInt(specialPrizeRequirement.max_series || 0, 10);
        if (serie < 1 || serie > maxSeries) {
            mostrarMensaje(`La serie ${serie} no es v?lida. Debe estar entre 1 y ${maxSeries}.`, 'warning');
            return false;
        }

        if (!specialPrizeAssignments[serie]) {
            specialPrizeAssignments[serie] = new Set();
        }

        for (const fraccion of fracciones) {
            if (fraccion < 1 || fraccion > 10) {
                mostrarMensaje(`La fracci?n ${fraccion} no es v?lida. Debe estar entre 1 y 10.`, 'warning');
                return false;
            }
            if (specialPrizeAssignments[serie].has(fraccion)) {
                mostrarMensaje(`La serie ${serie}, fracci?n ${fraccion}, ya fue asignada.`, 'warning');
                return false;
            }
        }

        const objetivo = obtenerTotalDecimosObjetivo();
        const asignados = obtenerDecimosAsignados();
        if ((asignados + fracciones.length) > objetivo) {
            mostrarMensaje(`No puedes superar los ${objetivo} d?cimos requeridos.`, 'warning');
            return false;
        }

        fracciones.forEach(f => specialPrizeAssignments[serie].add(f));
        renderSpecialPrizeSection();
        return true;
    }

    function construirPayloadPremioEspecial() {
        const assignments = Object.keys(specialPrizeAssignments).map(serie => ({
            serie: parseInt(serie, 10),
            fracciones: Array.from(specialPrizeAssignments[serie]).sort((a, b) => a - b),
        })).sort((a, b) => a.serie - b.serie);

        if (!assignments.length) {
            return null;
        }

        return {
            assignments: assignments,
            required_fractions: obtenerTotalDecimosObjetivo(),
            assigned_fractions: obtenerDecimosAsignados(),
            max_series: parseInt(specialPrizeRequirement.max_series || 0, 10),
        };
    }

    function validarPremioEspecialCompleto() {
        if (!specialPrizeRequirement?.required) return true;
        const objetivo = obtenerTotalDecimosObjetivo();
        const asignados = obtenerDecimosAsignados();
        return objetivo > 0 && asignados === objetivo;
    }
    
    // Event listeners para actualizar total al cambiar montos
    $('.payment-input').on('input', actualizarTotalPagado);

    $('#btn-special-add-serie-unidad').on('click', function() {
        const desde = parseInt($('#special-serie-rango-desde').val(), 10);
        const hasta = parseInt($('#special-serie-rango-hasta').val(), 10);
        if (desde || hasta) {
            if (!desde || !hasta || hasta < desde) {
                mostrarMensaje('Indica un rango de series v?lido.', 'warning');
                return;
            }
            for (let s = desde; s <= hasta; s++) {
                if (!agregarFraccionesASerie(s, [1,2,3,4,5,6,7,8,9,10])) {
                    return;
                }
            }
            $('#special-serie-rango-desde').val('');
            $('#special-serie-rango-hasta').val('');
            return;
        }

        const serie = parseInt($('#special-serie-unidad').val(), 10);
        if (!serie) {
            mostrarMensaje('Indica una serie v?lida.', 'warning');
            return;
        }
        if (agregarFraccionesASerie(serie, [1,2,3,4,5,6,7,8,9,10])) {
            $('#special-serie-unidad').val('');
        }
    });

    $('#btn-special-add-fraccion-unidad').on('click', function() {
        const desde = parseInt($('#special-fraccion-rango-desde').val(), 10);
        const hasta = parseInt($('#special-fraccion-rango-hasta').val(), 10);
        let fracciones = [];
        if (desde || hasta) {
            if (!desde || !hasta || hasta < desde || desde < 1 || hasta > 10) {
                mostrarMensaje('Indica un rango de fracciones v?lido (1 a 10).', 'warning');
                return;
            }
            for (let f = desde; f <= hasta; f++) fracciones.push(f);
            $('#special-fraccion-rango-desde').val('');
            $('#special-fraccion-rango-hasta').val('');
        } else {
            const unidad = parseInt($('#special-fraccion-unidad').val(), 10);
            if (!unidad || unidad < 1 || unidad > 10) {
                mostrarMensaje('Indica una fracci?n v?lida (1 a 10).', 'warning');
                return;
            }
            fracciones = [unidad];
            $('#special-fraccion-unidad').val('');
        }

        const textoFracciones = fracciones.join('-');
        const seriePrompt = window.prompt(`?A qu? serie pertenecen las fracciones ${textoFracciones}?`);
        const serie = parseInt(seriePrompt, 10);
        if (!serie) {
            mostrarMensaje('Debes indicar una serie v?lida para asignar fracciones.', 'warning');
            return;
        }
        agregarFraccionesASerie(serie, fracciones);
    });

    $('#btn-special-reset').on('click', function() {
        specialPrizeAssignments = {};
        renderSpecialPrizeSection();
    });

    $('#btn-modal-premio-especial-gestionar').on('click', function() {
        const modalEl = document.getElementById('modal-premio-especial-obligatorio');
        if (modalEl && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
        $('html, body').animate({ scrollTop: $('#bloque-premio-especial').offset().top - 120 }, 250);
    });

    // Event listener para solo devoluci?n (sin liquidar) ��� bot?n "Aceptar" #333
    $('#btn-aceptar-solo-devolucion').click(function() {
        if (participacionesAsignadas.length === 0) {
            mostrarMensaje('Selecciona al menos una participaci?n para devolver', 'warning');
            return;
        }
        const liquidacionData = {
            entity_id: entidadSeleccionada.id,
            lottery_id: sorteoSeleccionado.id,
            return_reason: tipoDevolucion === 'vendedor' ? 'Devoluci?n de vendedor a entidad' : 'Devoluci?n de entidad a administraci?n',
            tipo_devolucion: tipoDevolucion,
            solo_devolucion: true,
            liquidacion: {
                devolver: participacionesAsignadas.map(p => p.id),
                vender: [],
                pagos: []
            },
            _token: '{{ csrf_token() }}'
        };
        if (tipoDevolucion === 'vendedor' && vendedorSeleccionado) {
            liquidacionData.seller_id = vendedorSeleccionado.id;
        }
        $(this).prop('disabled', true).text('Procesando...');
        function enviarSoloDevolucion(payloadData) {
            $.ajax({
                url: "{{ route('devolutions.store') }}",
                method: 'POST',
                data: payloadData,
                success: function(response) {
                    if (response.queued && response.success) {
                        try {
                            sessionStorage.setItem('partilot_bg_job_started', JSON.stringify({
                                title: 'Tramitaci?n en segundo plano',
                                text: 'La operaci?n est? en cola. Te llevamos al listado; al terminar ver?s un aviso.',
                                type: 'notice'
                            }));
                        } catch (e) {}
                        window.location.href = "{{ route('devolutions.index') }}";
                        return;
                    }
                    if (response.success) {
                        mostrarMensaje('Devoluci?n registrada correctamente (sin liquidar)', 'success');
                        if (response.devolution_id) {
                            window.location.href = "{{ route('devolutions.index') }}/" + response.devolution_id;
                        } else {
                            setTimeout(() => { window.location.href = "{{ route('devolutions.index') }}"; }, 1500);
                        }
                    } else {
                        mostrarMensaje(response.message || 'Error al registrar la devoluci?n', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    if (manejarAdvertenciaLiquidacionVendedores(xhr, payloadData, enviarSoloDevolucion)) {
                        return;
                    }
                    console.error('Error en solo devoluci?n:', error);
                    mostrarMensaje(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error al registrar la devoluci?n', 'error');
                },
                complete: function() {
                    $('#btn-aceptar-solo-devolucion').prop('disabled', false).text('Aceptar');
                }
            });
        }
        enviarSoloDevolucion(liquidacionData);
    });

    // Event listener para aceptar liquidaci?n
    $('#btn-aceptar-liquidacion').click(function() {
        if (specialPrizeRequirement?.required && !validarPremioEspecialCompleto()) {
            const modalEl = document.getElementById('modal-premio-especial-obligatorio');
            if (modalEl && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
            mostrarMensaje('Debes completar la asignaci?n de serie/fracci?n del premio especial antes de aceptar la liquidaci?n.', 'warning');
            return;
        }

        // Recopilar todos los pagos
        const pagos = [];
        
        // Pago en efectivo
        const efectivoMonto = parseFloat($('#pago-efectivo-monto').val()) || 0;
        if (efectivoMonto > 0) {
            pagos.push({
                payment_method: 'efectivo',
                amount: efectivoMonto
            });
        }
        
        // Pago por Bizum
        const bizumMonto = parseFloat($('#pago-bizum-monto').val()) || 0;
        if (bizumMonto > 0) {
            pagos.push({
                payment_method: 'bizum',
                amount: bizumMonto
            });
        }
        
        // Pago por transferencia
        const transferenciaMonto = parseFloat($('#pago-transferencia-monto').val()) || 0;
        if (transferenciaMonto > 0) {
            pagos.push({
                payment_method: 'transferencia',
                amount: transferenciaMonto
            });
        }

        // Validar que al menos haya participaciones o un pago
        if (participacionesAsignadas.length === 0 && pagos.length === 0) {
            mostrarMensaje('Debes seleccionar participaciones o registrar al menos un pago', 'warning');
            return;
        }

        // Preparar datos para la liquidaci?n: liquidacion.devolver con las IDs a devolver; reserve_id si hay reserva
        const liquidacionData = {
            entity_id: entidadSeleccionada.id,
            lottery_id: sorteoSeleccionado.id,
            set_id: reservaSeleccionada ? null : (setSeleccionado ? setSeleccionado.id : null),
            reserve_id: reservaSeleccionada ? reservaSeleccionada.id : null,
            return_reason: tipoDevolucion === 'vendedor' ? 'Devoluci?n de vendedor a entidad' : 'Devoluci?n de entidad a administraci?n',
            tipo_devolucion: tipoDevolucion,
            participations: participacionesAsignadas.map(p => p.id),
            liquidacion: {
                pagos: pagos,
                devolver: participacionesAsignadas.map(p => p.id),
                vender: [],
                special_prize: construirPayloadPremioEspecial()
            },
            _token: '{{ csrf_token() }}'
        };

        // Agregar seller_id si es devoluci?n de vendedor
        if (tipoDevolucion === 'vendedor' && vendedorSeleccionado) {
            liquidacionData.seller_id = vendedorSeleccionado.id;
        }

        if (tipoDevolucion === 'administracion') {
            pendingLiquidacionData = liquidacionData;
            $('input[name="prize_payment_mode"]').prop('checked', false);
            $('#prize-payment-mode-confirm').prop('checked', false);
            $('#btn-confirmar-modalidad-pago').prop('disabled', true);
            const modalEl = document.getElementById('modal-modalidad-pago-premios');
            if (modalEl && window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else {
                mostrarMensaje('No se pudo abrir el selector de modalidad de pago.', 'error');
            }
            return;
        }

        $(this).prop('disabled', true).text('Procesando...');
        enviarLiquidacionCompleta(liquidacionData, $(this));
    });

    function actualizarBotonModalidadPago() {
        const modo = $('input[name="prize_payment_mode"]:checked').val();
        const confirmado = $('#prize-payment-mode-confirm').is(':checked');
        $('#btn-confirmar-modalidad-pago').prop('disabled', !(modo && confirmado));
    }

    $(document).on('change', 'input[name="prize_payment_mode"], #prize-payment-mode-confirm', actualizarBotonModalidadPago);

    $('#btn-confirmar-modalidad-pago').click(function() {
        if (!pendingLiquidacionData) {
            return;
        }
        const modo = $('input[name="prize_payment_mode"]:checked').val();
        if (!modo || !$('#prize-payment-mode-confirm').is(':checked')) {
            mostrarMensaje('Debes seleccionar la modalidad de pago y confirmarla.', 'warning');
            return;
        }
        pendingLiquidacionData.prize_payment_mode = modo;
        const $checked = $('input[name="prize_payment_mode"]:checked');
        const onlinePayer = $checked.data('online-payer');
        if (modo === 'online' && onlinePayer) {
            pendingLiquidacionData.online_payer = onlinePayer;
        }
        const modalEl = document.getElementById('modal-modalidad-pago-premios');
        if (modalEl && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
        const $btn = $('#btn-aceptar-liquidacion');
        $btn.prop('disabled', true).text('Procesando...');
        enviarLiquidacionCompleta(pendingLiquidacionData, $btn);
        pendingLiquidacionData = null;
    });

    function enviarLiquidacionCompleta(payloadData, $triggerBtn) {
        $.ajax({
            url: "{{ route('devolutions.store') }}",
            method: 'POST',
            data: payloadData,
            success: function(response) {
                if (response.queued && response.success) {
                    try {
                        sessionStorage.setItem('partilot_bg_job_started', JSON.stringify({
                            title: 'Tramitacion en segundo plano',
                            text: 'La operacion esta en cola. Te llevamos al listado; al terminar veras un aviso.',
                            type: 'notice'
                        }));
                    } catch (e) {}
                    window.location.href = "{{ route('devolutions.index') }}";
                    return;
                }
                if (response.success) {
                    mostrarMensaje('Liquidacion procesada correctamente', 'success');
                    setTimeout(() => {
                        window.location.href = "{{ route('devolutions.index') }}";
                    }, 2000);
                } else {
                    mostrarMensaje(response.message || 'Error al procesar la liquidacion', 'error');
                }
            },
            error: function(xhr, status, error) {
                if (manejarAdvertenciaLiquidacionVendedores(xhr, payloadData, function (p) {
                    enviarLiquidacionCompleta(p, $triggerBtn);
                })) {
                    return;
                }
                console.error('Error en liquidacion:', error);
                mostrarMensaje(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error al procesar la liquidacion', 'error');
            },
            complete: function() {
                if ($triggerBtn && $triggerBtn.length) {
                    $triggerBtn.prop('disabled', false).text('Aceptar Liquidacion');
                }
            }
        });
    }

    // Event listener para cerrar liquidaci?n
    $('#btn-cerrar-liquidacion').click(function() {
        window.location.href = "{{ route('devolutions.index') }}";
    });

    // ==================== LIQUIDACI��N DE VENDEDOR ====================
    
    let sorteoSeleccionadoLiquidacionVendedor = null;

    // Funci?n para cargar sorteos disponibles del vendedor
    function cargarSorteosVendedor() {
        if (!vendedorSeleccionado || !entidadSeleccionada) {
            console.error('Falta vendedor o entidad seleccionada');
            return;
        }

        $.ajax({
            url: '{{ route("devolutions.lotteries") }}',
            method: 'GET',
            data: {
                entity_id: entidadSeleccionada.id,
                seller_id: vendedorSeleccionado.id
            },
            success: function(response) {
                if (response.success && response.lotteries) {
                    const selector = $('#vendedor-selector-sorteo-liquidacion');
                    selector.empty().append('<option value="">-- Selecciona un sorteo --</option>');
                    
                    response.lotteries.forEach(lottery => {
                        selector.append(`<option value="${lottery.id}">${lottery.name} - ${lottery.description}</option>`);
                    });
                } else {
                    mostrarMensaje('No hay sorteos disponibles para este vendedor', 'warning');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar sorteos:', error);
                mostrarMensaje('Error al cargar sorteos', 'error');
            }
        });
    }

    // Event listener para cambio de sorteo en liquidaci?n de vendedor
    $('#vendedor-selector-sorteo-liquidacion').on('change', function() {
        const lotteryId = $(this).val();
        
        if (lotteryId) {
            sorteoSeleccionadoLiquidacionVendedor = lotteryId;
            cargarResumenLiquidacionVendedor();
            cargarHistorialLiquidacionesVendedor();
            $('#vendedor-resumen-liquidacion-container').show();
        } else {
            sorteoSeleccionadoLiquidacionVendedor = null;
            $('#vendedor-resumen-liquidacion-container').hide();
        }
    });

    // Funci?n para cargar resumen de liquidaci?n de vendedor (COPIA EXACTA DE SELLERS)
    function cargarResumenLiquidacionVendedor() {
        if (!sorteoSeleccionadoLiquidacionVendedor) return;

        $.ajax({
            url: '{{ route("sellers.settlement-summary") }}',
            method: 'GET',
            data: {
                seller_id: vendedorSeleccionado.id,
                lottery_id: sorteoSeleccionadoLiquidacionVendedor
            },
            success: function(response) {
                console.log('=== RESPUESTA SETTLEMENT SUMMARY ===');
                console.log(response);
                
                if (response.success) {
                    const summary = response.summary;
                    console.log('Summary:', summary);
                    
                    // Parsear valores a n?meros
                    const pricePerParticipation = parseFloat(summary.price_per_participation) || 0;
                    const totalAmount = parseFloat(summary.total_amount) || 0;
                    const totalPaid = parseFloat(summary.total_paid) || 0;
                    const pendingAmount = parseFloat(summary.pending_amount) || 0;
                    const liquidatedParticipations = parseFloat(summary.liquidated_participations) || 0;
                    const pendingParticipations = parseFloat(summary.pending_participations) || 0;
                    
                    $('#vendedor-settlement-total-participations').text(summary.total_participations);
                    $('#vendedor-settlement-price-per-participation').text(pricePerParticipation.toFixed(2) + '���');
                    $('#vendedor-settlement-total-amount').text(totalAmount.toFixed(2) + '���');
                    $('#vendedor-settlement-total-paid').text(totalPaid.toFixed(2) + '���');
                    $('#vendedor-settlement-liquidated-participations').text(liquidatedParticipations.toFixed(2));
                    $('#vendedor-settlement-pending-amount').text(pendingAmount.toFixed(2) + '���');
                    $('#vendedor-settlement-pending-participations').text(pendingParticipations.toFixed(2));
                    $('#vendedor-settlement-pendiente-display').text(pendingAmount.toFixed(2) + '���');
                    
                    console.log('Datos actualizados en la vista');
                    
                    // Resetear campos de pago
                    actualizarTotalPagarAhoraSettlementVendedor();
                } else {
                    console.error('Response no exitoso:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar resumen:', error);
                mostrarMensaje('Error al cargar el resumen de liquidaci?n', 'error');
            }
        });
    }

    // Funci?n para actualizar total a pagar ahora (COPIA EXACTA DE SELLERS)
    function actualizarTotalPagarAhoraSettlementVendedor() {
        const efectivo = parseFloat($('#vendedor-settlement-pago-efectivo').val()) || 0;
        const bizum = parseFloat($('#vendedor-settlement-pago-bizum').val()) || 0;
        const transferencia = parseFloat($('#vendedor-settlement-pago-transferencia').val()) || 0;
        
        const totalPagarAhora = efectivo + bizum + transferencia;
        $('#vendedor-settlement-pagar-ahora').text(totalPagarAhora.toFixed(2) + '���');
        
        const pendiente = parseFloat($('#vendedor-settlement-pending-amount').text().replace('���', '').replace(',', '.')) || 0;
        const quedaraPendiente = pendiente - totalPagarAhora;
        
        $('#vendedor-settlement-quedara-pendiente').text(quedaraPendiente.toFixed(2) + '���');
        
        if (quedaraPendiente <= 0 && totalPagarAhora > 0) {
            $('#vendedor-settlement-quedara-pendiente').removeClass('text-warning').addClass('text-success');
        } else if (totalPagarAhora > 0) {
            $('#vendedor-settlement-quedara-pendiente').removeClass('text-success').addClass('text-warning');
        } else {
            $('#vendedor-settlement-quedara-pendiente').removeClass('text-success text-warning');
        }
    }

    // Event listeners para actualizar totales
    $('.vendedor-settlement-payment-input').on('input', actualizarTotalPagarAhoraSettlementVendedor);

    // Bot?n para registrar liquidaci?n (COPIA EXACTA DE SELLERS)
    $('#btn-registrar-liquidacion-vendedor').on('click', function() {
        if (!sorteoSeleccionadoLiquidacionVendedor) {
            mostrarMensaje('Debes seleccionar un sorteo primero', 'warning');
            return;
        }

        // Recopilar pagos
        const pagos = [];
        
        const efectivo = parseFloat($('#vendedor-settlement-pago-efectivo').val()) || 0;
        if (efectivo > 0) {
            pagos.push({ payment_method: 'efectivo', amount: efectivo });
        }
        
        const bizum = parseFloat($('#vendedor-settlement-pago-bizum').val()) || 0;
        if (bizum > 0) {
            pagos.push({ payment_method: 'bizum', amount: bizum });
        }
        
        const transferencia = parseFloat($('#vendedor-settlement-pago-transferencia').val()) || 0;
        if (transferencia > 0) {
            pagos.push({ payment_method: 'transferencia', amount: transferencia });
        }

        if (pagos.length === 0) {
            mostrarMensaje('Debes ingresar al menos un monto de pago', 'warning');
            return;
        }

        // Deshabilitar bot?n
        $(this).prop('disabled', true).text('Procesando...');

        $.ajax({
            url: '{{ route("sellers.settlement.store") }}',
            method: 'POST',
            data: {
                seller_id: vendedorSeleccionado.id,
                lottery_id: sorteoSeleccionadoLiquidacionVendedor,
                pagos: pagos,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    mostrarMensaje('Liquidaci?n registrada correctamente', 'success');
                    
                    // Limpiar campos de pago
                    $('#vendedor-settlement-pago-efectivo, #vendedor-settlement-pago-bizum, #vendedor-settlement-pago-transferencia').val('');
                    
                    // Recargar datos
                    setTimeout(() => {
                        cargarResumenLiquidacionVendedor();
                        cargarHistorialLiquidacionesVendedor();
                    }, 1000);
                } else {
                    mostrarMensaje(response.message || 'Error al registrar liquidaci?n', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                mostrarMensaje('Error al registrar la liquidaci?n', 'error');
            },
            complete: function() {
                $('#btn-registrar-liquidacion-vendedor').prop('disabled', false).html('<i class="ri-add-line"></i> Registrar Liquidaci?n');
            }
        });
    });

    // Funci?n para cargar historial de liquidaciones (COPIA EXACTA DE SELLERS)
    function cargarHistorialLiquidacionesVendedor() {
        if (!sorteoSeleccionadoLiquidacionVendedor) return;

        $.ajax({
            url: '{{ route("sellers.settlement-history") }}',
            method: 'GET',
            data: {
                seller_id: vendedorSeleccionado.id,
                lottery_id: sorteoSeleccionadoLiquidacionVendedor
            },
            success: function(response) {
                if (response.success && response.settlements.length > 0) {
                    let html = '<div class="table-responsive"><table class="table table-sm table-hover"><thead class="table-light"><tr><th>Fecha</th><th>Participaciones Liquidadas</th><th>Monto Pagado</th><th>M?todos de Pago</th></tr></thead><tbody>';
                    
                    response.settlements.forEach(settlement => {
                        const fecha = new Date(settlement.settlement_date).toLocaleDateString('es-ES');
                        
                        let metodos = [];
                        settlement.payments.forEach(payment => {
                            let icono = '';
                            if (payment.payment_method == 'efectivo') {
                                icono = '<i class="ri-wallet-line text-success"></i>';
                            } else if (payment.payment_method == 'bizum') {
                                icono = '<i class="ri-smartphone-line text-info"></i>';
                            } else if (payment.payment_method == 'transferencia') {
                                icono = '<i class="ri-bank-line text-primary"></i>';
                            }
                            const paymentAmount = parseFloat(payment.amount) || 0;
                            metodos.push(`${icono} ${paymentAmount.toFixed(2)}���`);
                        });
                        
                        const calculatedParts = parseFloat(settlement.calculated_participations) || 0;
                        const paidAmount = parseFloat(settlement.paid_amount) || 0;
                        
                        html += `
                            <tr>
                                <td>${fecha}</td>
                                <td>${calculatedParts.toFixed(2)}</td>
                                <td class="fw-bold text-success">${paidAmount.toFixed(2)}���</td>
                                <td>${metodos.join(', ')}</td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table></div>';
                    $('#vendedor-historial-liquidaciones-container').html(html);
                } else {
                    $('#vendedor-historial-liquidaciones-container').html('<p class="text-muted text-center">No hay liquidaciones registradas para este sorteo</p>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error al cargar historial:', error);
                $('#vendedor-historial-liquidaciones-container').html('<p class="text-danger text-center">Error al cargar el historial</p>');
            }
        });
    }

    // ===== PASO DE ANULACI��N =====
    
    // Event listeners para el paso de anulaci?n
    $('#btn-volver-anulacion').click(function() {
        mostrarPaso('paso-participaciones');
    });

    $('#btn-cancelar-anulacion').click(function() {
        if (confirm('?Est?s seguro de que quieres cancelar la anulaci?n?')) {
            mostrarPaso('paso-entidad');
            // Resetear variables
            participacionesAsignadas = [];
            entidadSeleccionada = null;
            sorteoSeleccionado = null;
            setSeleccionado = null;
            tipoDevolucion = null;
        }
    });

    $('#btn-confirmar-anulacion').click(function() {
        if (participacionesAsignadas.length === 0) {
            alert('No hay participaciones seleccionadas para anular');
            return;
        }

        const motivo = $('#anulacion-motivo').val().trim();
        if (!motivo) {
            alert('Debes especificar un motivo para la anulaci?n');
            return;
        }

        if (!confirm(`?Est?s seguro de que quieres anular ${participacionesAsignadas.length} participaciones? Esta acci?n no se puede deshacer.`)) {
            return;
        }

        // Mostrar loading
        $('#btn-confirmar-anulacion').prop('disabled', true).html('<i class="ri-loader-4-line ri-spin"></i> Procesando...');

        // Enviar datos de anulaci?n
        const datosAnulacion = {
            entity_id: entidadSeleccionada.id,
            lottery_id: sorteoSeleccionado.id,
            set_id: setSeleccionado ? setSeleccionado.id : null,
            participations: participacionesAsignadas.map(p => p.id),
            motivo: motivo,
            tipo_devolucion: 'anulacion',
            _token: '{{ csrf_token() }}'
        };

        $.ajax({
            url: '{{ route("devolutions.store") }}',
            method: 'POST',
            data: datosAnulacion,
            success: function(response) {
                if (response.success) {
                    alert('Anulaci?n procesada correctamente');
                    // Redirigir o resetear
                    window.location.href = '{{ route("devolutions.index") }}';
                } else {
                    alert('Error: ' + (response.message || 'Error desconocido'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Error en anulaci?n:', error);
                alert('Error al procesar la anulaci?n');
            },
            complete: function() {
                $('#btn-confirmar-anulacion').prop('disabled', false).html('<i class="ri-check-line"></i> Confirmar Anulaci?n');
            }
        });
    });

    // Funci?n para obtener el precio de un set desde el backend
    function obtenerPrecioSet(setId, callback) {
        $.ajax({
            url: '{{ route("sets.get-price") }}',
            method: 'GET',
            data: { set_id: setId },
            success: function(response) {
                if (response.success) {
                    // Precio para liquidaci?n = total_participation_amount (jugado + donaci?n)
                    callback(parseFloat(response.total_participation_amount) || parseFloat(response.played_amount) || 0);
                } else {
                    callback(0);
                }
            },
            error: function() {
                callback(0);
            }
        });
    }

    // Funci?n para configurar el paso de anulaci?n
    function configurarPasoAnulacion() {
        console.log('=== CONFIGURANDO PASO DE ANULACI��N ===');
        console.log('Entidad seleccionada:', entidadSeleccionada);
        console.log('Sorteo seleccionado:', sorteoSeleccionado);
        console.log('Set seleccionado:', setSeleccionado);
        console.log('Participaciones asignadas:', participacionesAsignadas);
        
        // Llenar informaci?n b?sica
        $('#anulacion-entidad-nombre').text(entidadSeleccionada ? entidadSeleccionada.name : '-');
        $('#anulacion-sorteo-nombre').text(sorteoSeleccionado ? (sorteoSeleccionado.lottery_name || sorteoSeleccionado.name || '-') : '-');
        $('#anulacion-set-nombre').text(setSeleccionado ? `Set #${setSeleccionado.id}` : '-');
        $('#anulacion-total-participaciones').text(participacionesAsignadas.length);

        // Precio para liquidaci?n = total_participation_amount (jugado + donaci?n)
        let precioPorParticipacion = 0;
        if (setSeleccionado) {
            precioPorParticipacion = parseFloat(setSeleccionado.total_participation_amount) || (parseFloat(setSeleccionado.played_amount) + parseFloat(setSeleccionado.donation_amount || 0)) || 0;
        }
        
        // Si no tenemos precio, intentar obtenerlo del backend
        if (precioPorParticipacion === 0 && participacionesAsignadas.length > 0 && setSeleccionado) {
            console.log('No se encontr? precio en setSeleccionado, obteniendo del backend...');
            obtenerPrecioSet(setSeleccionado.id, function(precio) {
                if (precio > 0) {
                    const montoLiberado = participacionesAsignadas.length * precio;
                    console.log('Precio obtenido del backend:', precio);
                    console.log('Monto liberado recalculado:', montoLiberado);
                    
                    $('#anulacion-monto-liberado').text(montoLiberado.toFixed(2) + '���');
                    $('#anulacion-credito-disponible').text(montoLiberado.toFixed(2) + '���');
                } else {
                    console.log('No se pudo obtener precio del backend, usando valor por defecto');
                    const montoLiberado = participacionesAsignadas.length * 5;
                    $('#anulacion-monto-liberado').text(montoLiberado.toFixed(2) + '���');
                    $('#anulacion-credito-disponible').text(montoLiberado.toFixed(2) + '���');
                }
            });
        }
        
        const montoLiberado = participacionesAsignadas.length * precioPorParticipacion;
        
        console.log('Precio por participaci?n final:', precioPorParticipacion);
        console.log('N?mero de participaciones:', participacionesAsignadas.length);
        console.log('Monto liberado calculado:', montoLiberado);
        
        $('#anulacion-monto-liberado').text(montoLiberado.toFixed(2) + '���');
        $('#anulacion-credito-disponible').text(montoLiberado.toFixed(2) + '���');

        // Generar resumen de participaciones
        let html = '<div class="table-responsive"><table class="table table-sm table-hover"><thead class="table-light"><tr><th>N?mero</th><th>C?digo</th><th>Estado</th></tr></thead><tbody>';
        
        participacionesAsignadas.forEach(participation => {
            html += `
                <tr>
                    <td>${participation.number || participation.participation_number}</td>
                    <td>${participation.participation_code}</td>
                    <td><span class="badge bg-warning">Se anular?</span></td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div>';
        $('#anulacion-resumen-participaciones').html(html);
    }

    // Modificar la funci?n mostrarPaso para incluir anulaci?n
    const mostrarPasoOriginal = mostrarPaso;
    mostrarPaso = function(pasoId) {
        if (pasoId === 'paso-anulacion') {
            configurarPasoAnulacion();
        }
        return mostrarPasoOriginal(pasoId);
    };
});


</script>

@endsection


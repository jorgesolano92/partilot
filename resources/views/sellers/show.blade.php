@extends('layouts.layout')

@section('title','Vendedores/Asignación')

@section('content')

<style>
    
    .form-wizard-element, .form-wizard-element label {
        cursor: pointer;
    }
    .form-check-input:checked {
        border-color: #333;
    }

    .seller-entity-banner .row {
        align-items: center;
    }
    .seller-entity-banner .seller-entity-banner__logo {
        width: 72px;
        height: 72px;
        min-width: 72px;
        min-height: 72px;
        background-size: cover;
        background-position: center;
    }
    .seller-entity-banner .seller-entity-banner__logo i {
        font-size: 2rem;
    }

    .part-information {
        transition: all 500ms;
    }

    /* Estilos para la funcionalidad de asignación */
    .asignacion-paso {
        transition: all 0.3s ease;
    }

    .asignacion-paso table {
        margin-top: 20px;
    }

    .asignacion-paso .btn-seleccionar {
        border-radius: 20px;
        font-size: 12px;
        padding: 5px 15px;
    }

    .asignacion-paso .btn-volver {
        border-radius: 20px;
        font-size: 12px;
        padding: 5px 15px;
    }

    /* Animación para transiciones entre pasos */
    .asignacion-paso.fade-in {
        animation: fadeIn 0.3s ease-in;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Estilos para las participaciones asignadas */
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

    /* Filas seleccionables por clic (reservas, sets) */
    #tabla-reservas tbody tr,
    #tabla-sets tbody tr,
    #tabla-reservas-participaciones tbody tr {
        cursor: pointer;
    }
    #tabla-reservas tbody tr:hover,
    #tabla-sets tbody tr:hover,
    #tabla-reservas-participaciones tbody tr:hover {
        background-color: rgba(231, 131, 7, 0.1) !important;
    }
</style>


<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('sellers.index') }}">Vendedores/Asignación</a></li>
                        <li class="breadcrumb-item active">Ver Vendedor</li>
                    </ol>
                </div>
                <h4 class="page-title">Vendedores/Asignación</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">
                        Detalles del Vendedor
                    </h4>

                    <br>

                    <div class="row">
                        <div class="col-md-3" style="position: relative;">
                            <ul class="form-card bs mb-3 nav">

                                @if(empty($hideDatosVendedorTab))
                                <li class="nav-item">
                                    <div class="form-wizard-element {{ ($defaultSellerTab ?? 'datos_vendedor') === 'datos_vendedor' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#datos_vendedor">
                                        <span>&nbsp;&nbsp;</span>
                                        <img src="{{url('icons_/vendedores.svg')}}" alt="">
                                        <label>Dat. Vendedor</label>
                                    </div>
                                </li>
                                @endif

                                <li class="nav-item">
                                    <div class="form-wizard-element {{ ($defaultSellerTab ?? 'datos_vendedor') === 'asignacion' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#asignacion">
                                        
                                        <span>
                                            &nbsp;&nbsp;
                                        </span>
                                        <img src="{{url('icons_/participaciones.svg')}}" alt="">
                                        <label>
                                            Asignación
                                        </label>

                                    </div>

                                </li>

                                <li class="nav-item">
                                    <div class="form-wizard-element" data-bs-toggle="tab" data-bs-target="#participaciones">
                                        
                                        <span>
                                            &nbsp;&nbsp;
                                        </span>
                                        <img src="{{url('icons_/participaciones.svg')}}" alt="">
                                        <label>
                                            Participaciones
                                        </label>

                                    </div>

                                </li>

                                <li class="nav-item">
                                    <div class="form-wizard-element" data-bs-toggle="tab" data-bs-target="#liquidacion">
                                        
                                        <span>
                                            &nbsp;&nbsp;
                                        </span>
                                        <img src="{{url('icons_/participaciones.svg')}}" alt="">
                                        <label>
                                            Liquidación
                                        </label>

                                    </div>

                                </li>
                            </ul>

                                                         @if(empty($hideSellerSidebarProfile))
                             <!-- Información del Vendedor -->
                             <div class="form-card bs mb-3">
                                 <div class="row">
                                     <div class="col-4">
                                         <div class="photo-preview-3 logo-round" @if($seller->display_image) style="background-image: url('{{ asset('storage/' . $seller->display_image) }}'); background-size: cover;" @endif>
                                             @if(!$seller->display_image)
                                             <i class="ri-account-circle-fill"></i>
                                             @endif
                                         </div>
                                         <div style="clear: both;"></div>
                                     </div>
                                     <div class="col-8 text-center mt-2">
                                         <h3 class="mt-2 mb-0">{{ $seller->full_name ?? 'N/A' }}</h3>
                                         <i style="position: relative; top: 3px; font-size: 16px; color: #333" class="ri-mail-line"></i> {{ $seller->display_email ?: 'N/A' }}
                                     </div>
                                 </div>
                             </div>
                             @endif

                             @if(empty($hideEntitySidebar))
                             <!-- Información de la Entidad - Sidebar Simple -->
                             <div class="form-card bs mb-3" id="entity-info-sidebar">
                                 <div class="row">
                                     <div class="col-4">
                                         <div class="photo-preview-3 logo-round" @if($currentEntity && ($currentEntity->image ?? null)) style="background-image: url('{{ asset('uploads/' . $currentEntity->image) }}'); background-size: cover;" @endif>
                                             @if(!$currentEntity || !($currentEntity->image ?? null))
                                                 <i class="ri-building-line"></i>
                                             @endif
                                         </div>
                                         <div style="clear: both;"></div>
                                     </div>
                                     <div class="col-8 text-center mt-2">
                                         <h3 class="mt-2 mb-0" id="sidebar-entity-name">{{ $currentEntity->name ?? 'Entidad' }}</h3>
                                         <i style="position: relative; top: 3px; font-size: 16px; color: #333" class="ri-computer-line"></i> 
                                         <span id="sidebar-entity-province">{{ $currentEntity->province ?? 'Provincia' }}</span>
                                     </div>
                                 </div>
                             </div>
                             @endif

                            <a href="{{ route('sellers.index') }}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                                <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span>
                            </a>
                        </div>

                        <div class="col-md-9">

                            @if($seller->entities->count() > 1 && !request()->query('entity_id'))
                                <!-- PASO 1: Selección de Entidad (Solo si tiene múltiples y no hay una seleccionada) -->
                                <div class="form-card bs" style="min-height: 658px;">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h4 class="mb-0 mt-1">Seleccionar Entidad</h4>
                                            <small><i>El vendedor trabaja con múltiples entidades. Selecciona una para continuar.</i></small>
                                        </div>
                                    </div>

                                    <br>

                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover nowrap w-100">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Entidad</th>
                                                    <th>Provincia</th>
                                                    <th>Ciudad</th>
                                                    <th>Administración</th>
                                                    <th>Teléfono</th>
                                                    <th>Seleccionar</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($seller->entities as $entity)
                                                    <tr>
                                                        <td>{{ $entity->id }}</td>
                                                        <td><strong>{{ $entity->name }}</strong></td>
                                                        <td>{{ $entity->province ?? 'N/A' }}</td>
                                                        <td>{{ $entity->city ?? 'N/A' }}</td>
                                                        <td>{{ $entity->administration->name ?? 'N/A' }}</td>
                                                        <td>{{ $entity->phone ?? 'N/A' }}</td>
                                                        <td>
                                                            <a href="{{ route('sellers.show', $seller->id) }}?entity_id={{ $entity->id }}" 
                                                               class="btn btn-sm btn-primary" style="border-radius: 20px;">
                                                                <i class="ri-checkbox-circle-line"></i> Seleccionar
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @else
                                <!-- PASO 2: Contenido del Vendedor (Cuando ya hay entidad seleccionada) -->
                                <div class="tabbable">
                                    
                                    <div class="tab-content p-0">
                                        
                                        @if(empty($hideDatosVendedorTab))
                                        <div class="tab-pane fade {{ ($defaultSellerTab ?? 'datos_vendedor') === 'datos_vendedor' ? 'active show' : '' }}" id="datos_vendedor">


                                            <div class="form-card bs" style="min-height: 658px;">
                                                <div class="d-flex justify-content-between align-items-center show-content">
                                                    <div>
                                                        <h4 class="mb-0 mt-1">
                                                            Información del Vendedor
                                                            @if($seller->entities->count() > 1)
                                                                <a href="{{ route('sellers.show', $seller->id) }}" class="btn btn-sm btn-outline-secondary ms-2" style="border-radius: 20px;" title="Cambiar entidad">
                                                                    <i class="ri-refresh-line"></i> Cambiar Entidad
                                                                </a>
                                                            @endif
                                                        </h4>
                                                        <small><i>Detalles completos del vendedor</i></small>
                                                    </div>
                                                    <div>
                                                        @if($canEditSeller ?? true)
                                                        <a href="{{ route('sellers.edit', $seller->id) }}" class="btn btn-light" style="border: 1px solid silver; border-radius: 30px;"> 
                                                            <img src="{{url('assets/form-groups/edit.svg')}}" alt="">
                                                            Editar
                                                        </a>
                                                        @endif
                                                    </div>
                                                </div>

                                            @if(empty($hideEntityBannerInTab))
                                            <div class="form-group mt-2 mb-3 admin-box seller-entity-banner">
                                                <div class="row">
                                                    <div class="col-auto pe-3">
                                                        <div class="photo-preview-3 logo-round seller-entity-banner__logo" @if($currentEntity && ($currentEntity->image ?? null)) style="background-image: url('{{ asset('uploads/' . $currentEntity->image) }}');" @endif>
                                                            @if(!$currentEntity || !($currentEntity->image ?? null))
                                                                <i class="ri-building-line"></i>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="col-4 text-center">
                                                        <h4 class="mt-0 mb-0">{{ $currentEntity->name ?? 'Entidad' }}</h4>
                                                        <small>{{ $currentEntity->province ?? 'Provincia' }}</small> <br>
                                                        <small>{{ $currentEntity->administration->name ?? 'Administración' }}</small>
                                                    </div>

                                                    <div class="col-3">
                                                        <div>
                                                            Provincia: {{ $currentEntity->province ?? 'N/A' }} <br>
                                                            Dirección: {{ $currentEntity->address ?? 'N/A' }}
                                                        </div>
                                                    </div>

                                                    <div class="col-3">
                                                        <div>
                                                            Ciudad: {{ $currentEntity->city ?? 'N/A' }} <br>
                                                            Tel: {{ $currentEntity->phone ?? 'N/A' }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif

                                            <br>

                                            @if(empty($hideSellerPersonalData))
                                            <div class="row show-content">
                                                <div class="col-md-4">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">Grupo</label>
                                                        <div class="input-group input-group-merge group-form">
                                                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                <img src="{{url('icons_/grupos.svg')}}" alt="" width="18">
                                                            </div>
                                                            <input class="form-control" type="text" value="{{ $sellerGroup->name ?? 'Sin grupo' }}" style="border-radius: 0 30px 30px 0;" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row show-content">
                                                <div class="col-md-4">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">Nombre</label>
                                                        <div class="input-group input-group-merge group-form">
                                                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                <img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
                                                            </div>
                                                            <input class="form-control" type="text" value="{{ $seller->name ?? 'N/A' }}" style="border-radius: 0 30px 30px 0;" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">Primer Apellido</label>
                                                        <div class="input-group input-group-merge group-form">
                                                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                <img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
                                                            </div>
                                                            <input class="form-control" type="text" value="{{ $seller->last_name ?? 'N/A' }}" style="border-radius: 0 30px 30px 0;" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">Segundo Apellido</label>
                                                        <div class="input-group input-group-merge group-form">
                                                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                <img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
                                                            </div>
                                                            <input class="form-control" type="text" value="{{ $seller->last_name2 ?? 'N/A' }}" style="border-radius: 0 30px 30px 0;" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row show-content">
                                                <div class="col-md-3">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">NIF/CIF</label>
                                                        <div class="input-group input-group-merge group-form">
                                                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                <img src="{{url('assets/form-groups/admin/4.svg')}}" alt="">
                                                            </div>
                                                            <input class="form-control" type="text" value="{{ $seller->nif_cif ?? 'N/A' }}" style="border-radius: 0 30px 30px 0;" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">F. Nacimiento</label>
                                                        <div class="input-group input-group-merge group-form">
                                                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                <img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
                                                            </div>
                                                            <input class="form-control" type="text" value="{{ $seller->birthday ? \Carbon\Carbon::parse($seller->birthday)->format('d/m/Y') : 'N/A' }}" style="border-radius: 0 30px 30px 0;" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">Email</label>
                                                        <div class="input-group input-group-merge group-form">
                                                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                <img src="{{url('assets/form-groups/admin/9.svg')}}" alt="">
                                                            </div>
                                                            <input class="form-control" type="email" value="{{ $seller->display_email ?: 'N/A' }}" style="border-radius: 0 30px 30px 0;" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">Teléfono</label>
                                                        <div class="input-group input-group-merge group-form">
                                                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                <img src="{{url('assets/form-groups/admin/10.svg')}}" alt="">
                                                            </div>
                                                            <input class="form-control" type="text" value="{{ $seller->phone ?? 'N/A' }}" style="border-radius: 0 30px 30px 0;" readonly>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">Estado</label>
                                                        <div class="input-group input-group-merge group-form">
                                                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                <img src="{{url('assets/form-groups/admin/13.svg')}}" alt="">
                                                            </div>
                                                            <input class="form-control" type="text" value="{{ $seller->status_text }}" id="seller-status-input" style="border-radius: 0 30px 30px 0;" readonly>
                                                            @if($canToggleSellerStatus ?? false)
                                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="seller-toggle-status" title="Cambiar estado" style="border-radius: 0 30px 30px 0; border-left: none;">Cambiar</button>
                                                            @endif
                                                        </div>
                                                        <span class="badge {{ $seller->status_class }} mt-2" id="seller-status-badge" style="display: none;">{{ $seller->status_text }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            @else
                                            <div class="row show-content">
                                                <div class="col-md-6">
                                                    <div class="form-group mt-2 mb-3">
                                                        <label class="label-control">Estado</label>
                                                        <div class="input-group input-group-merge group-form">
                                                            <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                                <img src="{{url('assets/form-groups/admin/13.svg')}}" alt="">
                                                            </div>
                                                            <input class="form-control" type="text" value="{{ $seller->status_text }}" readonly style="border-radius: 0 30px 30px 0;">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif

                                            @if($canEditSellerObservations ?? false)
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <form method="POST" action="{{ route('sellers.update-comment', $seller->id) }}?entity_id={{ $currentEntity->id ?? '' }}">
                                                        @csrf
                                                        <div class="form-group mt-2 mb-3">
                                                            <label class="label-control">Observaciones (entidad)</label>
                                                            <textarea name="comment" class="form-control" rows="4" placeholder="Notas internas sobre este vendedor">{{ old('comment', $seller->comment) }}</textarea>
                                                        </div>
                                                        <button type="submit" class="btn btn-sm btn-dark" style="border-radius: 20px;">Guardar observaciones</button>
                                                    </form>
                                                </div>
                                            </div>
                                            @endif

                                        </div>

                                    </div>
                                        @endif



                                    <div class="tab-pane fade {{ ($defaultSellerTab ?? 'datos_vendedor') === 'asignacion' ? 'active show' : '' }}" id="asignacion">
                                        <div class="form-card bs" style="min-height: 658px;">
                                                                                         <!-- Paso 1: Selección de Reservas -->
                                             <div id="paso-reservas" class="asignacion-paso" style="display: none;">
                                                 <div class="d-flex justify-content-between align-items-center">
                                                     <div>
                                                         <h4 class="mb-0 mt-1">
                                                          Reserva en la que Asignar participaciones
                                                         </h4>
                                                         <small><i>Selecciona una reserva para continuar</i></small>
                                                     </div>
                                                 </div>

                                                 <br>

                                                                                                   <div style="min-height: 656px;">
                                                      <table id="tabla-reservas" class="table table-striped nowrap w-100">
                                                          <thead class="">
                                                              <tr>
                                                                  <th>Orden ID</th>
                                                                  <th>N.Sorteo</th>
                                                                  <th>Fecha Sorteo</th>
                                                                  <th>Nombre Sorteo</th>
                                                                  <th>Numero/s</th>
                                                                  <th>Importe <br> (Número)</th>
                                                                  <th>Décimos <br> (Número)</th>
                                                                  <th>Importe <br> TOTAL</th>
                                                                  <th class="d-none">Seleccionar</th>
                                                              </tr>
                                                          </thead>
                                                          <tbody>
                                                              <!-- Los datos se cargarán dinámicamente -->
                                                          </tbody>
                                                      </table>
                                                  </div>

                                                  <div class="row">
                                                      <div class="col-12 text-end">
                                                          <button type="button" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2" id="btn-siguiente-reservas" disabled>
                                                              Siguiente
                                                              <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i>
                                                          </button>
                                                      </div>
                                                  </div>
                                             </div>

                                                                                         <!-- Paso 2: Selección de Sets -->
                                             <div id="paso-sets" class="asignacion-paso" style="display: none;">
                                                 <div class="d-flex justify-content-between align-items-center">
                                                     <div>
                                                         <h4 class="mb-0 mt-1">
                                                             Set en el que Asignar participaciones
                                                         </h4>
                                                         <small><i>Selecciona un set para continuar</i></small>
                                                     </div>
                                                     <button id="btn-volver-reservas" class="btn btn-secondary btn-sm">
                                                         <i class="ri-arrow-left-line"></i> Volver a Reservas
                                                     </button>
                                                 </div>

                                                 <br>

                                                <div id="reserva-paso-sets-text" class="mb-2 small text-muted" style="display: none;"></div>

                                                                                                   <div style="min-height: 656px;">
                                                      <table id="tabla-sets" class="table table-striped nowrap w-100">
                                                          <thead class="">
                                                              <tr>
                                                                  <th>Orden ID</th>
                                                                  <th>Nombre Set</th>
                                                                  <th>Importe Jugado <br> (por Número)</th>
                                                                  <th>Importe Donativo</th>
                                                                  <th>Importe TOTAL</th>
                                                                  <th>Participaciones <br> Físicas</th>
                                                                  <th>Participaciones <br> Disponibles</th>
                                                                  <th>Tipo</th>
                                                                  <th class="d-none">Seleccionar</th>
                                                              </tr>
                                                          </thead>
                                                          <tbody>
                                                              <!-- Los datos se cargarán dinámicamente -->
                                                          </tbody>
                                                      </table>
                                                  </div>

                                                  <div class="row">
                                                      <div class="col-12 text-end">
                                                          <button type="button" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2" id="btn-siguiente-sets" disabled>
                                                              Siguiente
                                                              <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i>
                                                          </button>
                                                      </div>
                                                  </div>
                                             </div>

                                            <!-- Paso 3: Asignación de Participaciones -->
                                            <div id="paso-asignacion" class="asignacion-paso" style="display: none;">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h4 class="mb-0 mt-1">
                                                            Asigna las participaciones
                                                        </h4>
                                                        <small><i>Individual o por rango</i></small>
                                                    </div>
                                                    <button id="btn-volver-sets" class="btn btn-secondary btn-sm">
                                                        <i class="ri-arrow-left-line"></i> Volver a Sets
                                                    </button>
                                                </div>

                                                <br>

                                                <div class="row">
                                                    <!-- Bloque asignación física: por rango y por unidad (solo sets físicos) -->
                                                    <div id="bloque-asignacion-fisica" class="col-md-12 mb-3">
                                                        <div class="form-card bs">
                                                            <div id="rango-disponibles-fisico" class="px-3 pt-3 small text-muted" style="display: none;"></div>
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
                                                                             <input type="number" class="form-control" id="rango-desde" placeholder="Número inicial" style="border-radius: 30px;">
                                                                    </div>
                                                                     </div>
                                                                     <div class="flex-fill">
                                                                        <label class="form-label small mb-1">Hasta</label>
                                                                         <div class="input-group input-group-merge group-form">
                                                                             
                                                                             <input type="number" class="form-control" id="rango-hasta" placeholder="Número final" style="border-radius: 30px;">
                                                                         </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex align-items-center p-3">
                                                                <div class="me-3">
                                                                    <img src="{{url('icons_/participaciones.svg')}}" alt="" width="40px">
                                                                </div>
                                                                <div class="flex-grow-1">
                                                                    <h4 class="m-0 fw-bold">Participación</h4>
                                                                    <small class="text-muted">Participación Unidad</small>
                                                                </div>
                                                                <div class="d-flex gap-2 align-items-end" style="width: 70%;">
                                                                     <div style="width: 50%;">
                                                                        <label class="form-label small mb-1">Participación</label>
                                                                         <div class="input-group input-group-merge group-form">
                                                                             <input type="number" class="form-control" id="participacion-unidad" placeholder="Número de participación" style="border-radius: 30px;">
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
                                                    <!-- Bloque asignación digital: solo cantidad (solo sets digitales) -->
                                                    <div id="bloque-cantidad-digital" class="col-md-12 mb-3" style="display: none;">
                                                        <div class="form-card bs">
                                                            <div class="d-flex align-items-center p-3">
                                                                <div class="me-3">
                                                                    <img src="{{url('icons_/participaciones.svg')}}" alt="" width="40px">
                                                                </div>
                                                                <div class="flex-grow-1">
                                                                    <h4 class="m-0 fw-bold">Participaciones digitales</h4>
                                                                    <small class="text-muted">Cantidad a asignar</small>
                                                                </div>
                                                                <div class="d-flex gap-2 align-items-end flex-wrap" style="width: 70%;">
                                                                    <div class="flex-fill">
                                                                        <label class="form-label small mb-1">Cantidad</label>
                                                                        <div class="input-group input-group-merge group-form">
                                                                            <input type="number" class="form-control" id="cantidad-digital" min="1" placeholder="Ej: 10, 20" style="border-radius: 30px;">
                                                                        </div>
                                                                    </div>
                                                                    <div class="d-flex align-items-center">
                                                                        <span class="text-muted me-2">Disponibles:</span>
                                                                        <span id="disponibles-digital" class="fw-bold">0</span>
                                                                    </div>
                                                                    <div>
                                                                        <button type="button" class="btn btn-warning" id="btn-asignar-cantidad-digital" style="border-radius: 30px; background-color: #e78307; color: #333; font-weight: bold;">
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
                                                            <h4 class="mb-0 mt-1">
                                                                Resumen Asignación
                                                            </h4>
                                                            <small><i>comprueba que la asignación sea la correcta </i></small>
                                                        </div>
                                                    </div>
                                                    
                                                    <br>
                                                    
                                                    <!-- Estado vacío -->
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
                                 <!-- Las participaciones se cargarán dinámicamente aquí -->
                             </div>
                         </div>
                                                        
                                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                                            <div class="d-flex align-items-center gap-3">
                                                                <span class="fw-bold">Total Asignadas:</span>
                                                                <div class="form-card bs px-3 py-2">
                                                                    <span id="total-asignadas" class="fw-bold fs-4">0</span>
                                                                </div>
                                                            </div>
                                                            <button type="button" class="btn btn-warning" id="btn-terminar-asignacion" style="border-radius: 30px; background-color: #e78307; color: #333; font-weight: bold; padding: 10px 30px;">
                                                                Terminar
                                                            </button>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>

                                            <!-- Estado inicial: Mostrar botón para comenzar -->
                                            <div id="estado-inicial" class="asignacion-paso">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h4 class="mb-0 mt-1">
                                                            Asignación de Participaciones
                                                        </h4>
                                                        <small><i>Comienza seleccionando una reserva</i></small>
                                                    </div>
                                                </div>

                                                <br>
                                                <br>

                                                <div class="d-flex align-items-center gap-1">
                                                    <div class="empty-tables">
                                                        <div>
                                                            <img src="{{url('icons_/participaciones.svg')}}" alt="" width="80px" style="margin-top: 10px;">
                                                        </div>
                                        
                                                        <h3 class="mb-0">Participaciones del vendedor</h3>
                                        
                                                        <small>Asigna Participaciones</small>
                                        
                                                        <br>
                                        
                                                        <button id="btn-iniciar-asignacion" style="border-radius: 30px; width: 150px;" class="btn btn-md btn-dark mt-2">
                                                            <i style="position: relative; top: 2px;" class="ri-add-line"></i> Asignar
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="participaciones">
                                        <div class="form-card bs" style="min-height: 658px;">
                                            <!-- Paso 1: Selección de Reservas -->
                                            <div id="paso-reservas-participaciones" class="participaciones-paso">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h4 class="mb-0 mt-1">
                                                            Reserva en la que Asignar participaciones
                                                        </h4>
                                                        <small><i>Selecciona una reserva para continuar</i></small>
                                                    </div>
                                                </div>

                                                <br>

                                                <div style="min-height: 656px;">
                                                    <table id="tabla-reservas-participaciones" class="table table-striped nowrap w-100">
                                                        <thead class="">
                                                            <tr>
                                                                <th>Orden ID</th>
                                                                <th>N.Sorteo</th>
                                                                <th>Fecha Sorteo</th>
                                                                <th>Nombre Sorteo</th>
                                                                <th>Numero/s</th>
                                                                <th>Importe <br> (Número)</th>
                                                                <th>Décimos <br> (Número)</th>
                                                                <th>Importe <br> TOTAL</th>
                                                                <th class="d-none">Seleccionar</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <!-- Los datos se cargarán dinámicamente -->
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <div class="row">
                                                    <div class="col-12 text-end">
                                                        <button type="button" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2" id="btn-siguiente-reservas-participaciones" disabled>
                                                            Siguiente
                                                            <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Paso 2: Mostrar Tacos del Vendedor -->
                                            <div id="paso-tacos-participaciones" class="participaciones-paso" style="display: none;">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h4 class="mb-0 mt-1">
                                                            Tacos del Vendedor
                                                        </h4>
                                                        <small><i>Participaciones asignadas por taco</i></small>
                                                    </div>
                                                    <button id="btn-volver-reservas-participaciones" class="btn btn-secondary btn-sm">
                                                        <i class="ri-arrow-left-line"></i> Volver a Reservas
                                                    </button>
                                                </div>

                                                <br>

                                                <div style="min-height: 656px;" id="contenedor-tacos">
                                                    <!-- Los tacos se cargarán dinámicamente aquí -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="liquidacion">
                                        <div class="form-card bs" style="min-height: 658px;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h4 class="mb-0 mt-1">Liquidación de Vendedor</h4>
                                                    <small><i>Registra pagos del vendedor</i></small>
                                                </div>
                                            </div>

                                            <hr>

                                            <!-- Selector de Sorteo -->
                                            <div class="row mb-4">
                                                <div class="col-md-6">
                                                    <label class="form-label fw-bold">Selecciona un Sorteo</label>
                                                    <select class="form-select" id="selector-sorteo-liquidacion">
                                                        <option value="">-- Selecciona un sorteo --</option>
                                                        @foreach($reserves->unique('lottery_id') as $reserve)
                                                            @if($reserve->lottery)
                                                                <option value="{{ $reserve->lottery->id }}">
                                                                    {{ $reserve->lottery->name }} - {{ $reserve->lottery->description }}
                                                                </option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Resumen de Liquidación -->
                                            <div id="resumen-liquidacion-container" style="display: none;">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="card">
                                                            <div class="card-header">Resumen de Participaciones</div>
                                                            <div class="card-body">
                                                                <p><strong>Total Participaciones a Liquidar:</strong> <span id="settlement-total-participations" class="fw-bold fs-4">0</span></p>
                                                                <p><strong>Precio por Participación:</strong> <span id="settlement-price-per-participation">0.00€</span></p>
                                                                <p><strong>Total a Liquidar:</strong> <span id="settlement-total-amount" class="text-danger fw-bold">0.00€</span></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="card">
                                                            <div class="card-header">Liquidación Actual</div>
                                                            <div class="card-body">
                                                                <p><strong>Total Pagado:</strong> <span id="settlement-total-paid" class="text-success fw-bold">0.00€</span></p>
                                                                <p><strong>Participaciones Liquidadas:</strong> <span id="settlement-liquidated-participations">0</span></p>
                                                                <p><strong>Pendiente por Liquidar:</strong> <span id="settlement-pending-amount" class="text-warning fw-bold">0.00€</span></p>
                                                                <p><strong>Participaciones Pendientes:</strong> <span id="settlement-pending-participations">0</span></p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Formas de Pago -->
                                                <div class="card mt-3">
                                                    <div class="card-body">
                                                        <h5 class="card-title">Registrar Pagos</h5>
                                                        <small class="text-muted">Puedes registrar múltiples formas de pago</small>
                                                        
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
                                                                        <input type="number" step="0.01" class="form-control settlement-payment-input" placeholder="0.00" id="settlement-pago-efectivo">
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
                                                                        <input type="number" step="0.01" class="form-control settlement-payment-input" placeholder="0.00" id="settlement-pago-bizum">
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
                                                                        <input type="number" step="0.01" class="form-control settlement-payment-input" placeholder="0.00" id="settlement-pago-transferencia">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="col-4">
                                                                <div class="text-center">
                                                                    <div class="border rounded p-3 mb-3 bg-light">
                                                                        <small class="text-muted">Pendiente a Pagar</small>
                                                                        <div class="text-danger h4" id="settlement-pendiente-display">0,00€</div>
                                                                    </div>
                                                                    <div class="border rounded p-3 mb-3 bg-success bg-opacity-10">
                                                                        <small class="text-muted">A Pagar Ahora</small>
                                                                        <div class="text-success h4" id="settlement-pagar-ahora">0,00€</div>
                                                                    </div>
                                                                    <div class="border rounded p-3 mb-3" id="settlement-quedara-pendiente-container">
                                                                        <small class="text-muted">Quedará Pendiente</small>
                                                                        <div class="h5" id="settlement-quedara-pendiente">0,00€</div>
                                                                    </div>
                                                                    <button type="button" class="btn btn-warning" id="btn-registrar-liquidacion" style="border-radius: 30px; width: 100%;">
                                                                        <i class="ri-add-line"></i> Registrar Liquidación
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
                                                        <div id="historial-liquidaciones-container">
                                                            <p class="text-muted text-center">Selecciona un sorteo para ver el historial</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                            </div>
                            @endif
                            <!-- Cierre del if de selección de entidad -->

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@section('scripts')

<script>

function initDatatable() 
  {
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
                            //         console.log(val)
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

  var init1 = false;

  setTimeout(()=>{
    $('.filters .inline-fields:first').trigger('keyup');
  },100);

  {{-- $('[data-bs-target="#participaciones"]').click(function (e) {
    if (!init1) {
      initDatatable();
      init1 =true;
    }
  }); --}}

  $('.show-details').click(function (e) {
      e.preventDefault();

      if ($(this).parents('.form-card').find('.part-information').css('height') == '0px') {
        $(this).parents('.form-card').find('.part-information').css('height', '250px');
      }else{
        $(this).parents('.form-card').find('.part-information').css('height', '0px');
        {{-- setTimeout(()=>{
            $(this).parents('.form-card').find('#details-participations').addClass('d-none');
            $(this).parents('.form-card').find('#list-participations').removeClass('d-none');
        },500); --}}
      }

  });

  {{-- $('.show-details').click(function(event) {
      $(this).parents('.form-card').find('#details-participations').removeClass('d-none');
      $(this).parents('.form-card').find('#list-participations').addClass('d-none');
  }); --}}



  // Funcionalidad para asignación de participaciones
  $(document).ready(function() {
    // Variables en scope superior para evitar TDZ y uso en todos los handlers
    let reservaSeleccionada = null;
    let setSeleccionado = null;
    let participacionesAsignadas = [];
    let participacionesDisponibles = [];
    let disponiblesDigitalSet = 0; // Para sets digitales: cantidad disponible en el set
    
    // Inicializar DataTables para las tablas de reservas y sets
    let tablaReservas = null;
    let tablaSets = null;
    
    // Función para inicializar DataTable de reservas
    function inicializarDataTableReservas() {
      if (tablaReservas) {
        return; // Ya está inicializada
      }
      
      tablaReservas = $('#tabla-reservas').DataTable({
        "select": { style: "single" },
        "ordering": false,
        "sorting": false,
        "scrollX": true,
        "scrollCollapse": true,
        "language": {
          url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
               '<"row"<"col-sm-12"tr>>' +
               '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "columnDefs": [
          {
            "targets": -1, // Última columna (Seleccionar)
            "orderable": false,
            "searchable": false,
            "className": "d-none"
          }
        ],
        "createdRow": function(row) {
          $(row).addClass('selectable-row');
        }
      });
    }
    
    // Función para inicializar DataTable de sets
    function inicializarDataTableSets() {
      if (tablaSets) {
        return; // Ya está inicializada
      }
      
      tablaSets = $('#tabla-sets').DataTable({
        "select": { style: "single" },
        "ordering": false,
        "sorting": false,
        "scrollX": true,
        "scrollCollapse": true,
        "language": {
          url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        "pageLength": 10,
        "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
               '<"row"<"col-sm-12"tr>>' +
               '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "columnDefs": [
          {
            "targets": -1, // Última columna (Seleccionar)
            "orderable": false,
            "searchable": false,
            "className": "d-none"
          }
        ],
        "createdRow": function(row) {
          $(row).addClass('selectable-row');
        }
      });
    }
    
    // Función para mostrar un paso específico
    function mostrarPaso(pasoId) {
      $('.asignacion-paso').hide();
      $('#' + pasoId).show();
      
      // Inicializar DataTables según el paso
      if (pasoId === 'paso-reservas' && !tablaReservas) {
        inicializarDataTableReservas();
      } else if (pasoId === 'paso-sets' && !tablaSets) {
        inicializarDataTableSets();
      }
      
      // Verificar que el botón existe
      if (pasoId === 'paso-reservas') {
        console.log('Botón siguiente existe:', $('#btn-siguiente-reservas').length > 0);
        console.log('Botón siguiente disabled:', $('#btn-siguiente-reservas').prop('disabled'));
      }
    }
    
    // Función para cargar datos de reservas desde la base de datos
    function cargarReservas() {
      // Inicializar DataTable si no existe
      if (!tablaReservas) {
        inicializarDataTableReservas();
      }
      
      // Limpiar datos existentes
      tablaReservas.clear();
      
      // Preparar los datos para agregar
      let datosReservas = [];
      
      // Los datos de reservas están disponibles en la variable PHP $reserves
      @if(isset($reserves) && $reserves->count() > 0)
        @foreach($reserves as $reserve)
          datosReservas.push([
            '#RS{{ str_pad($reserve->id, 4, '0', STR_PAD_LEFT) }}',
            '{{ $reserve->lottery ? $reserve->lottery->name : "Sin sorteo" }}',
            '{{ $reserve->lottery ? $reserve->lottery->draw_date->format('d-m-Y') : "Sin fecha" }}',
            '{{ $reserve->lottery ? $reserve->lottery->description : "N/A" }}',
            '{{ is_array($reserve->reservation_numbers) ? implode(' - ', $reserve->reservation_numbers) : ($reserve->reservation_numbers ?? 'Sin números') }}',
            '{{ number_format($reserve->reservation_amount ?? 0, 2) }}€',
            '{{ $reserve->reservation_tickets ?? 0 }}',
            '<b>{{ number_format($reserve->total_amount ?? 0, 2) }}€</b>',
            `<div class="form-check">
               <input class="form-check-input seleccionar-reserva" type="radio" name="reserve_id" value="{{ $reserve->id }}" id="reserve_{{ $reserve->id }}" data-reserva-id="{{ $reserve->id }}">
               <label class="form-check-label" for="reserve_{{ $reserve->id }}">
                 Seleccionar
               </label>
             </div>`
          ]);
        @endforeach
      @endif
      
      // Agregar los datos a la tabla
      if (datosReservas.length > 0) {
        tablaReservas.rows.add(datosReservas).draw();
      } else {
        tablaReservas.rows.add([['No hay reservas disponibles para esta entidad', '', '', '', '', '', '', '', '']]).draw();
      }
      
      console.log('Reservas cargadas correctamente con DataTable');
    }
    
    // Función para cargar sets de una reserva desde la base de datos
    function cargarSets(reservaId) {
      // Inicializar DataTable si no existe
      if (!tablaSets) {
        inicializarDataTableSets();
      }
      
      // Limpiar datos existentes
      tablaSets.clear();
      
      // Hacer llamada AJAX para obtener los sets de la reserva
      $.ajax({
        url: '{{ route("sellers.get-sets-by-reserve") }}',
        method: 'POST',
        data: {
          reserve_id: reservaId,
          _token: '{{ csrf_token() }}'
        },
        success: function(response) {
          // Limpiar datos existentes
          tablaSets.clear();
          const $reservaText = $('#reserva-paso-sets-text');
          if (response.reserve) {
            const ref = '#RS' + String(response.reserve.id).padStart(4, '0');
            const sorteo = response.reserve.lottery && response.reserve.lottery.name ? response.reserve.lottery.name : '';
            $reservaText.html('Reserva: <strong>' + ref + '</strong>' + (sorteo ? ' – Sorteo: ' + sorteo : '')).show();
          } else {
            $reservaText.hide().empty();
          }
          if (response.sets && response.sets.length > 0) {
            let datosSets = [];
            
            response.sets.forEach(set => {
              const isDigital = (set.digital_participations > 0) && (!set.physical_participations || set.physical_participations === 0);
              const tipoSet = isDigital ? 'Set digital' : 'Set físico';
              datosSets.push([
                `#SP${String(set.id).padStart(4, '0')}`,
                set.set_name,
                `${parseFloat(set.played_amount || 0).toFixed(2)}€`,
                `${parseFloat(set.donation_amount || 0).toFixed(2)}€`,
                `<b>${parseFloat(set.total_amount || 0).toFixed(2)}€</b>`,
                set.physical_participations || 0,
                set.total_participations || 0,
                `<span class="badge ${isDigital ? 'bg-info' : 'bg-secondary'}">${tipoSet}</span>`,
                `<div class="form-check">
                   <input class="form-check-input seleccionar-set" type="radio" name="set_id" value="${set.id}" id="set_${set.id}" data-set-id="${set.id}" data-digital="${isDigital ? '1' : '0'}">
                   <label class="form-check-label" for="set_${set.id}">
                     Seleccionar
                   </label>
                 </div>`
              ]);
            });
            
            tablaSets.rows.add(datosSets).draw();
          } else {
            tablaSets.rows.add([['No hay sets disponibles para esta reserva', '', '', '', '', '', '', '', '']]).draw();
          }
          
          console.log('Sets cargados correctamente con DataTable');
        },
        error: function(xhr, status, error) {
          console.error('Error al cargar sets:', error);
          
          // Limpiar datos y mostrar error
          tablaSets.clear();
          tablaSets.rows.add([['Error al cargar los sets', '', '', '', '', '', '', '', '']]).draw();
        }
      });
    }
    
    // Event listeners
      
             // Cargar participaciones existentes al cargar la página
       $(document).ready(function() {
         // No cargar participaciones al inicio, solo cuando se seleccione un set
         participacionesAsignadas = [];
         actualizarResumenAsignacion();
       });
    
    // Botón para iniciar asignación
    $(document).on('click', '#btn-iniciar-asignacion', function() {
      cargarReservas();
      mostrarPaso('paso-reservas');
    });
    
    // Seleccionar reserva (clic en radio o en la fila)
    $(document).on('change', '.seleccionar-reserva', function() {
      console.log('Radio button seleccionado (change)');
      const reservaId = $(this).data('reserva-id');
      reservaSeleccionada = { id: reservaId };
      console.log('Reserva seleccionada ID:', reservaId);
      $('#btn-siguiente-reservas').prop('disabled', false);
      console.log('Botón habilitado');
    });

    $(document).on('click', '#tabla-reservas tbody tr', function(e) {
      const $radio = $(this).find('.seleccionar-reserva');
      if ($radio.length && !$(e.target).is('a, button') && !$(e.target).closest('.form-check').length) {
        $radio.prop('checked', true).trigger('change');
      }
    });
    
    // Botón siguiente para ir a sets
    $(document).on('click', '#btn-siguiente-reservas', function() {
      console.log('Botón siguiente clickeado');
      if (reservaSeleccionada) {
        cargarSets(reservaSeleccionada.id);
        mostrarPaso('paso-sets');
      }
    });
    
    // Seleccionar set (clic en radio o en la fila)
    $(document).on('click', '#tabla-sets tbody tr', function(e) {
      const $radio = $(this).find('.seleccionar-set');
      if ($radio.length && !$(e.target).is('a, button') && !$(e.target).closest('.form-check').length) {
        $radio.prop('checked', true).trigger('change');
      }
    });

    $(document).on('change', '.seleccionar-set', function() {
      console.log('Set seleccionado (change)');
      const setId = $(this).data('set-id');
      const isDigital = $(this).data('digital') === 1;
      setSeleccionado = { id: setId, is_digital: isDigital };
      console.log('Set seleccionado ID:', setId, 'digital:', isDigital);
      $('#btn-siguiente-sets').prop('disabled', false);
    });
    
    // Botón siguiente para ir a asignación
    $(document).on('click', '#btn-siguiente-sets', function() {
      if (setSeleccionado) {
         participacionesAsignadas = [];
         cargarParticipacionesExistentes();
         mostrarPaso('paso-asignacion');
         // Mostrar bloque según tipo de set (físico: rango/unidad; digital: cantidad)
         if (setSeleccionado.is_digital) {
           $('#bloque-asignacion-fisica').hide();
           $('#bloque-cantidad-digital').show();
         } else {
           $('#bloque-asignacion-fisica').show();
           $('#bloque-cantidad-digital').hide();
         }
      }
    });
    
    // Botón volver a reservas
    $(document).on('click', '#btn-volver-reservas', function() {
      mostrarPaso('paso-reservas');
    });
    
    // Botón volver a sets
    $(document).on('click', '#btn-volver-sets', function() {
      mostrarPaso('paso-sets');
    });

      // Función para cargar participaciones asignadas existentes
      function cargarParticipacionesExistentes() {
        // Solo cargar si hay un set seleccionado
        if (!setSeleccionado) {
          participacionesAsignadas = [];
          actualizarResumenAsignacion();
          return;
        }

        $.ajax({
          url: '{{ route("sellers.get-assigned-participations") }}',
          method: 'POST',
          data: {
            seller_id: {{ $seller->id }},
            set_id: setSeleccionado.id,
            _token: '{{ csrf_token() }}'
          },
          success: function(response) {
            if (response.success && response.participations) {
              participacionesAsignadas = response.participations.map(participation => ({
                id: participation.id,
                number: participation.number,
                participation_code: participation.participation_code,
                set_id: participation.set_id,
                assigned_at: participation.sale_date ? (participation.sale_date + 'T' + (participation.sale_time || '00:00:00')) : null,
                updated_at: participation.updated_at,
                created_at: participation.created_at,
                is_persisted: true
              }));
              actualizarResumenAsignacion();
            }
            if (setSeleccionado && setSeleccionado.is_digital && response.set_disponibles !== undefined) {
              disponiblesDigitalSet = response.set_disponibles;
              $('#disponibles-digital').text(disponiblesDigitalSet);
            }
            // Sets físicos: mostrar rangos disponibles (de la X a la Y)
            if (setSeleccionado && !setSeleccionado.is_digital && response.available_ranges !== undefined) {
              const $rango = $('#rango-disponibles-fisico');
              if (response.available_ranges && response.available_ranges.length > 0) {
                const txt = response.available_ranges.map(r => r[0] === r[1] ? `de la ${r[0]}` : `de la ${r[0]} a la ${r[1]}`).join(', ');
                $rango.html('<strong>Disponibles:</strong> ' + txt + '.').show();
              } else {
                $rango.html('No hay participaciones disponibles en este set.').show();
              }
            } else {
              $('#rango-disponibles-fisico').hide().empty();
            }
          },
          error: function(xhr, status, error) {
            console.error('Error al cargar participaciones existentes:', error);
          }
        });
      }

      // Función para validar y obtener participaciones disponibles
     function validarParticipacionesDisponibles(desde, hasta, setId) {
       return new Promise((resolve, reject) => {
         $.ajax({
           url: '{{ route("sellers.validate-participations") }}',
           method: 'POST',
           data: {
             desde: desde,
             hasta: hasta,
             set_id: setId,
             seller_id: {{ $seller->id }},
             _token: '{{ csrf_token() }}'
           },
           success: function(response) {
             if (response.success) {
               participacionesDisponibles = response.participations || [];
               resolve(response);
             } else {
               reject(response.message || 'Error al validar participaciones');
             }
           },
           error: function(xhr, status, error) {
             reject('Error de conexión: ' + error);
           }
         });
       });
     }

     // Validar y obtener N participaciones disponibles (solo sets digitales)
     function validarParticipacionesDisponiblesCantidad(cantidad) {
       return new Promise((resolve, reject) => {
         $.ajax({
           url: '{{ route("sellers.validate-participations") }}',
           method: 'POST',
           data: {
             cantidad: cantidad,
             set_id: setSeleccionado.id,
             seller_id: {{ $seller->id }},
             _token: '{{ csrf_token() }}'
           },
           success: function(response) {
             if (response.success) {
               participacionesDisponibles = response.participations || [];
               resolve(response);
             } else {
               reject(response.message || 'Error al validar participaciones');
             }
           },
           error: function(xhr, status, error) {
             reject(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Error de conexión: ' + error);
           }
         });
       });
     }

     // Función para asignar participaciones por rango
     function asignarParticipacionesPorRango(desde, hasta) {
       if (!setSeleccionado) {
         alert('Debes seleccionar un set primero');
         return;
       }

       if (!desde || !hasta) {
         alert('Debes especificar el rango desde y hasta');
         return;
       }

       if (parseInt(desde) > parseInt(hasta)) {
         alert('El número "Desde" no puede ser mayor que "Hasta"');
         return;
       }

       // Mostrar loading
       $('#btn-asignar-participacion').prop('disabled', true).text('Validando...');

       validarParticipacionesDisponibles(desde, hasta, setSeleccionado.id)
         .then(response => {
           if (response.participations && response.participations.length > 0) {
             // Agregar las participaciones válidas al resumen
             response.participations.forEach(participation => {
               const participacionExistente = participacionesAsignadas.find(p => p.number === participation.number);
               if (!participacionExistente) {
                 participacionesAsignadas.push({
                   id: participation.id,
                   number: participation.number,
                   participation_code: participation.participation_code,
                   set_id: setSeleccionado.id,
                   assigned_at: new Date().toISOString(),
                  updated_at: new Date().toISOString(),  // Agregar updated_at
                  is_persisted: false
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
     }

     // Función para asignar participación individual
     function asignarParticipacionIndividual(numero) {
       if (!setSeleccionado) {
         alert('Debes seleccionar un set primero');
         return;
       }

       if (!numero) {
         alert('Debes especificar el número de participación');
         return;
       }

       // Mostrar loading
       $('#btn-asignar-participacion').prop('disabled', true).text('Validando...');

       validarParticipacionesDisponibles(numero, numero, setSeleccionado.id)
         .then(response => {
           if (response.participations && response.participations.length > 0) {
             const participation = response.participations[0];
             const participacionExistente = participacionesAsignadas.find(p => p.number === participation.number);
             
             if (!participacionExistente) {
               participacionesAsignadas.push({
                 id: participation.id,
                 number: participation.number,
                 participation_code: participation.participation_code,
                 set_id: setSeleccionado.id,
                 assigned_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),  // Agregar updated_at
                is_persisted: false
               });
               actualizarResumenAsignacion();
               mostrarMensaje('Participación asignada correctamente', 'success');
             } else {
               mostrarMensaje('Esta participación ya está asignada', 'warning');
             }
           } else {
             mostrarMensaje('La participación no está disponible', 'warning');
           }
         })
         .catch(error => {
           mostrarMensaje(error, 'error');
         })
         .finally(() => {
           $('#btn-asignar-participacion').prop('disabled', false).text('Asignar');
           $('#participacion-unidad').val('');
         });
     }

           // Función para actualizar el resumen de asignación
      function actualizarResumenAsignacion() {
        // Siempre mostrar el resumen, pero controlar qué contenido mostrar
        $('#resumen-asignacion').show();
        
        // Ocultar el estado vacío por defecto
        $('#estado-vacio-resumen').addClass('d-none');
        
        if (participacionesAsignadas.length === 0) {
          // Si no hay participaciones, mostrar estado vacío
          $('#estado-vacio-resumen').removeClass('d-none');
          $('#lista-participaciones-asignadas').hide();
        } else {
          // Si hay participaciones, mostrar lista
          $('#lista-participaciones-asignadas').show();
          
          // Actualizar contador
          $('#total-asignadas').text(participacionesAsignadas.length);

                    // Generar grid de participaciones
          const gridHtml = participacionesAsignadas.map(participation => {
            // DEBUG: Ver qué datos tiene la participación
            console.log('Participation data:', participation);
            
            // SIEMPRE usar updated_at que siempre existe en la BD
            let fechaStr = 'Fecha no disponible';
            let horaStr = '';
            
            if (participation.updated_at) {
              const fecha = new Date(participation.updated_at);
              if (!isNaN(fecha.getTime())) {
                fechaStr = fecha.toLocaleDateString('es-ES');
                horaStr = fecha.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
              } else {
                console.error('Fecha inválida:', participation.updated_at);
              }
            } else {
              console.error('No tiene updated_at:', participation);
            }
            
            return `
              <div class="participation-block" style="background: white; border-radius: 15px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border: 1px solid #e0e0e0;">
                <div class="d-flex align-items-center justify-content-between">
                  <div class="d-flex align-items-center">
                    <div style="background-color: #333; padding: 16px 10px; border-radius: 12px; margin-right: 15px;">
                      <img src="{{url('assets/ticket.svg')}}" alt="" width="30px">
                    </div>
                    <div>
                      <h6 class="mb-1" style="font-weight: bold; color: #333;">Participación: ${participation.participation_code}</h6>
                      <div class="d-flex align-items-center gap-2">
                        <i class="ri-calendar-line" style="font-size: 14px; color: #666;"></i>
                        <span style="font-size: 14px; color: #666;">${fechaStr}${horaStr ? ' - ' + horaStr + 'h' : ''}</span>
                      </div>
                      <span class="badge bg-success mt-2">Asignada</span>
                    </div>
                  </div>
                   <div class="d-flex gap-2">
                     <button class="btn btn-sm btn-light" onclick="verDetalleParticipacion('${participation.participation_code}', ${participation.id})" title="Ver detalle" style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                       <img src="{{url('assets/form-groups/eye.svg')}}" alt="" width="12">
                     </button>
                     <button class="btn btn-sm btn-danger" onclick="eliminarParticipacion('${participation.participation_code}')" title="Eliminar" style="border-radius: 8px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                       <i class="ri-delete-bin-line"></i>
                     </button>
                   </div>
                 </div>
               </div>
             `;
           }).join('');

          $('#grid-participaciones').html(gridHtml);
          
          // Asegurar que el estado vacío esté oculto cuando hay elementos en el grid
          $('#estado-vacio-resumen').addClass('d-none');
        }
      }

           // Función para eliminar participación del resumen
      window.eliminarParticipacion = function(codigo) {
        // Encontrar la participación a eliminar
        const participacionAEliminar = participacionesAsignadas.find(p => p.participation_code === codigo);
        
        if (participacionAEliminar) {
          // Si todavía no se ha guardado (Terminar), solo se elimina del borrador local.
          if (!participacionAEliminar.is_persisted) {
            participacionesAsignadas = participacionesAsignadas.filter(p => p.participation_code !== codigo);
            actualizarResumenAsignacion();
            mostrarMensaje('Participación retirada del borrador', 'success');
            return;
          }

          // Eliminar de la base de datos
          $.ajax({
            url: '{{ route("sellers.remove-assignment") }}',
            method: 'POST',
            data: {
              participation_id: participacionAEliminar.id,
              seller_id: {{ $seller->id }},
              _token: '{{ csrf_token() }}'
            },
            success: function(response) {
              if (response.success) {
                // Eliminar del array local
                participacionesAsignadas = participacionesAsignadas.filter(p => p.participation_code !== codigo);
                actualizarResumenAsignacion();
                mostrarMensaje('Participación eliminada correctamente', 'success');
              } else {
                mostrarMensaje(response.message || 'Error al eliminar la participación', 'error');
              }
            },
            error: function(xhr, status, error) {
              mostrarMensaje('Error de conexión al eliminar la participación', 'error');
            }
          });
        }
      };

           // Función para mostrar mensajes
      function mostrarMensaje(mensaje, tipo) {
        const pnotifyType = tipo === 'success'
          ? 'success'
          : (tipo === 'error' ? 'error' : (tipo === 'warning' ? 'notice' : 'info'));
        const pnotifyTitle = tipo === 'success'
          ? 'Proceso finalizado'
          : (tipo === 'error' ? 'Error' : (tipo === 'warning' ? 'Atención' : 'Información'));

        if (typeof PNotify !== 'undefined') {
          if (typeof PNotify.removeAll === 'function') {
            PNotify.removeAll();
          }
          document.querySelectorAll('.ui-pnotify').forEach(function (el) { el.remove(); });
          const notice = new PNotify({
            title: pnotifyTitle,
            text: mensaje,
            type: pnotifyType,
            addclass: 'partilot-notify',
            width: '460px',
            icon: false,
            hide: true,
            delay: 6000,
            stack: PNotify.prototype.options.stack,
            buttons: {
              closer: true,
              sticker: false,
              closer_hover: false
            }
          });
          return notice;
        }

        // Fallback mínimo si PNotify no está disponible
        alert(mensaje);
        return null;
      }

     // Event listeners para asignación
     $(document).on('click', '#btn-asignar-participacion', function() {
       const desde = $('#rango-desde').val();
       const hasta = $('#rango-hasta').val();
       const unidad = $('#participacion-unidad').val();

       if (desde && hasta) {
         // Asignación por rango
         asignarParticipacionesPorRango(desde, hasta);
       } else if (unidad) {
         // Asignación individual
         asignarParticipacionIndividual(unidad);
       } else {
         alert('Debes especificar un rango o una participación individual');
       }
     });

     // Asignar por cantidad (solo sets digitales)
     $(document).on('click', '#btn-asignar-cantidad-digital', function() {
       if (!setSeleccionado || !setSeleccionado.is_digital) return;
       const cantidad = parseInt($('#cantidad-digital').val(), 10);
       if (!cantidad || cantidad < 1) {
         alert('Indica la cantidad de participaciones a asignar (mínimo 1).');
         return;
       }
       if (cantidad > disponiblesDigitalSet) {
         alert('La cantidad no puede ser mayor que las disponibles (' + disponiblesDigitalSet + ').');
         return;
       }
       $('#btn-asignar-cantidad-digital').prop('disabled', true).text('Validando...');
       validarParticipacionesDisponiblesCantidad(cantidad)
         .then(function(response) {
           if (response.participations && response.participations.length > 0) {
             response.participations.forEach(function(participation) {
               var existente = participacionesAsignadas.find(function(p) { return p.id === participation.id; });
               if (!existente) {
                 participacionesAsignadas.push({
                   id: participation.id,
                   number: participation.number,
                   participation_code: participation.participation_code,
                   set_id: setSeleccionado.id,
                   assigned_at: new Date().toISOString(),
                  updated_at: new Date().toISOString(),
                  is_persisted: false
                 });
               }
             });
             if (response.disponibles_restantes !== undefined) {
               disponiblesDigitalSet = response.disponibles_restantes;
               $('#disponibles-digital').text(disponiblesDigitalSet);
             }
             actualizarResumenAsignacion();
             mostrarMensaje('Participaciones asignadas correctamente', 'success');
             $('#cantidad-digital').val('');
           } else {
             mostrarMensaje('No se obtuvieron participaciones', 'warning');
           }
         })
         .catch(function(error) {
           mostrarMensaje(error, 'error');
         })
         .always(function() {
           $('#btn-asignar-cantidad-digital').prop('disabled', false).text('Asignar');
         });
     });

           // Event listener para terminar asignación
      $(document).on('click', '#btn-terminar-asignacion', function() {
        if (participacionesAsignadas.length === 0) {
          alert('No hay participaciones para guardar');
          return;
        }

        // Mostrar loading
        $('#btn-terminar-asignacion').prop('disabled', true).text('Guardando...');

        // Guardar asignaciones en la base de datos
        $.ajax({
          url: '{{ route("sellers.save-assignments") }}',
          method: 'POST',
          data: {
            participations_json: JSON.stringify(participacionesAsignadas),
            seller_id: {{ $seller->id }},
            _token: '{{ csrf_token() }}'
          },
          success: function(response) {
            if (response.success) {
              if (response.queued) {
                try {
                  sessionStorage.setItem('partilot_bg_job_started', JSON.stringify({
                    title: 'Asignación en segundo plano',
                    text: 'Las asignaciones están en cola. Recargamos la página; al terminar verás un aviso.',
                    type: 'notice'
                  }));
                } catch (e) {}
                window.location.reload();
              } else {
                mostrarMensaje(response.message || 'Asignaciones guardadas correctamente', 'success');
                
                // Limpiar el array temporal y recargar las participaciones existentes
                participacionesAsignadas = [];
                
                // Recargar las participaciones existentes desde la base de datos
                cargarParticipacionesExistentes();
                
                // Limpiar los campos de entrada
                $('#rango-desde').val('');
                $('#rango-hasta').val('');
                $('#participacion-unidad').val('');
                
                // Habilitar todos los campos
                $('#rango-desde, #rango-hasta, #participacion-unidad').prop('disabled', false);
              }
            } else {
              mostrarMensaje(response.message || 'Error al guardar las asignaciones', 'error');
            }
          },
                     error: function(xhr, status, error) {
             mostrarMensaje('Error de conexión al guardar las asignaciones', 'error');
             $('#btn-terminar-asignacion').prop('disabled', false).text('Terminar');
           },
           complete: function() {
             $('#btn-terminar-asignacion').prop('disabled', false).text('Terminar');
           }
        });
      });

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

     // Variables para el tab de participaciones
     let tablaReservasParticipaciones = null;
     let reservaSeleccionadaParticipaciones = null;

     // Función para inicializar DataTable de reservas para participaciones
     function inicializarDataTableReservasParticipaciones() {
       if (tablaReservasParticipaciones) {
         return; // Ya está inicializada
       }
       
       tablaReservasParticipaciones = $('#tabla-reservas-participaciones').DataTable({
         "select": { style: "single" },
         "ordering": false,
         "sorting": false,
         "scrollX": true,
         "scrollCollapse": true,
         "language": {
           url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
         },
         "pageLength": 10,
         "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "Todos"]],
         "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
         "columnDefs": [
           {
             "targets": -1, // Última columna (Seleccionar)
             "orderable": false,
             "searchable": false,
             "className": "d-none"
           }
         ],
         "createdRow": function(row) {
           $(row).addClass('selectable-row');
         }
       });
     }

     // Función para cargar reservas en el tab de participaciones
     function cargarReservasParticipaciones() {
       if (!tablaReservasParticipaciones) {
         inicializarDataTableReservasParticipaciones();
       }
       
       tablaReservasParticipaciones.clear();
       
       let datosReservas = [];
       
       @if(isset($reserves) && $reserves->count() > 0)
         @foreach($reserves as $reserve)
           datosReservas.push([
             '#RS{{ str_pad($reserve->id, 4, '0', STR_PAD_LEFT) }}',
             '{{ $reserve->lottery ? $reserve->lottery->name : "Sin sorteo" }}',
             '{{ $reserve->lottery ? $reserve->lottery->draw_date->format('d-m-Y') : "Sin fecha" }}',
             '{{ $reserve->lottery ? $reserve->lottery->description : "N/A" }}',
             '{{ is_array($reserve->reservation_numbers) ? implode(' - ', $reserve->reservation_numbers) : ($reserve->reservation_numbers ?? 'Sin números') }}',
             '{{ number_format($reserve->reservation_amount ?? 0, 2) }}€',
             '{{ $reserve->reservation_tickets ?? 0 }}',
             '<b>{{ number_format($reserve->total_amount ?? 0, 2) }}€</b>',
             `<div class="form-check">
                <input class="form-check-input seleccionar-reserva-participaciones" type="radio" name="reserve_id_participaciones" value="{{ $reserve->id }}" id="reserve_participaciones_{{ $reserve->id }}" data-reserva-id="{{ $reserve->id }}">
                <label class="form-check-label" for="reserve_participaciones_{{ $reserve->id }}">
                  Seleccionar
                </label>
              </div>`
           ]);
         @endforeach
       @endif
       
       if (datosReservas.length > 0) {
         tablaReservasParticipaciones.rows.add(datosReservas).draw();
       } else {
         tablaReservasParticipaciones.rows.add([['No hay reservas disponibles para esta entidad', '', '', '', '', '', '', '', '']]).draw();
       }
     }

           // Función para calcular y mostrar tacos del vendedor
      window.cargarTacosVendedor = function(reservaId) {
        // Mostrar loading
        $('#contenedor-tacos').html('<div class="text-center"><i class="ri-loader-4-line fa-spin"></i> Cargando tacos...</div>');

        // Obtener los sets de la reserva
        $.ajax({
          url: '{{ route("sellers.get-sets-by-reserve") }}',
          method: 'POST',
          data: {
            reserve_id: reservaId,
            include_digital: 1,
            seller_id: {{ $seller->id }},
            _token: '{{ csrf_token() }}'
          },
          success: function(response) {
            if (response.sets && response.sets.length > 0) {
              let html = '';
              let setsProcessed = 0;
              
              response.sets.forEach(set => {
                const isDigital = (set.digital_participations > 0) && (!set.physical_participations || set.physical_participations === 0);
                $.ajax({
                  url: '{{ route("sellers.get-assigned-participations") }}',
                  method: 'POST',
                  data: {
                    seller_id: {{ $seller->id }},
                    set_id: set.id,
                    _token: '{{ csrf_token() }}'
                  },
                  success: function(participationsResponse) {
                    setsProcessed++;
                    if (participationsResponse.success && participationsResponse.participations.length > 0) {
                      if (isDigital) {
                        html += generarHTMLSetDigital(set, participationsResponse.participations);
                      } else {
                        const tacos = calcularTacos(participationsResponse.participations, set);
                        if (tacos.length > 0) {
                          html += generarHTMLTacos(tacos, set);
                        }
                      }
                    }
                    if (setsProcessed === response.sets.length) {
                      if (html) {
                        $('#contenedor-tacos').html(html);
                      } else {
                        $('#contenedor-tacos').html('<div class="alert alert-info">No hay participaciones asignadas ni digitales vendidas en ningún set de esta reserva</div>');
                      }
                    }
                  },
                  error: function() {
                    setsProcessed++;
                    if (setsProcessed === response.sets.length) {
                      $('#contenedor-tacos').html('<div class="alert alert-danger">Error al cargar las participaciones</div>');
                    }
                  }
                });
              });
            } else {
              $('#contenedor-tacos').html('<div class="alert alert-info">No hay sets disponibles para esta reserva</div>');
            }
          },
          error: function() {
            $('#contenedor-tacos').html('<div class="alert alert-danger">Error al cargar los sets</div>');
          }
        });
      };

     // Función para calcular los tacos basándose en las participaciones
     function calcularTacos(participations, set) {
       const tacos = new Map();
       
       // Obtener el número de participaciones por taco desde el set
       // Por defecto 50 si no está definido
       const participationsPerBook = set.participations_per_book || 50;
       
       participations.forEach(participation => {
         const participationNumber = parseInt(participation.number);
         const bookNumber = Math.ceil(participationNumber / participationsPerBook);
         
         if (!tacos.has(bookNumber)) {
           tacos.set(bookNumber, {
             bookNumber: bookNumber,
             participations: [],
             totalParticipations: 0,
             salesRegistered: 0,
             returnedParticipations: 0,
             availableParticipations: 0
           });
         }
         
         const taco = tacos.get(bookNumber);
         taco.participations.push(participation);
         taco.totalParticipations++;
         
         if (participation.status === 'vendida' || participation.status === 'pagada') {
           taco.salesRegistered++;
         } else if (participation.status === 'devuelta') {
           taco.returnedParticipations++;
         } else {
           taco.availableParticipations++;
         }
       });
       
       return Array.from(tacos.values());
     }

     // Función para generar HTML de un set digital (serie completa, con desplegable como los tacos físicos)
     function generarHTMLSetDigital(set, participations) {
       const setNombre = set.set_name || ('Set #' + set.id);
       let salesRegistered = 0, returnedParticipations = 0, availableParticipations = 0;
       participations.forEach(p => {
         if (p.status === 'vendida' || p.status === 'pagada' || p.status === 'reserva_venta_digital') salesRegistered++;
         else if (p.status === 'devuelta') returnedParticipations++;
         else availableParticipations++;
       });
       const participationsJson = JSON.stringify(participations).replace(/'/g, '&#39;');
       return `
         <div class="form-card bs mb-2" style="margin: 5px;">
           <table class="table table-striped table-condensed table nowrap w-100 mb-0">
             <thead>
               <tr style="font-size: 10px;">
                 <th>Set</th>
                 <th>Participaciones</th>
                 <th>Nº Participaciones</th>
                 <th>Ventas Registradas</th>
                 <th>Participaciones Devueltas</th>
                 <th>Participaciones Disponibles</th>
                 <th></th>
               </tr>
               <tr style="font-size: 12px; font-weight: bolder; border-color: transparent;">
                 <td><strong>Set digital</strong> – ${setNombre}</td>
                 <td>${participations.length}</td>
                 <td>Serie completa</td>
                 <td>${salesRegistered}</td>
                 <td>${returnedParticipations}</td>
                 <td>${availableParticipations}</td>
                 <td>
                   <a class="btn btn-sm btn-light show-details-taco" href="#" data-set-id="${set.id}" data-book-number="digital" data-participations='${participationsJson}'><img src="{{ url('assets/form-groups/eye.svg') }}" alt="" width="12"></a>
                 </td>
               </tr>
             </thead>
           </table>
           <div class="part-information" style="height: 0px; overflow: hidden; transition: height 0.5s ease;">
             <div style="height: 250px; overflow: auto;" id="list-participations-taco-${set.id}-digital" class="">
               <table class="table table-striped table-condensed table nowrap w-100 mb-0">
                 <thead>
                   <tr style="font-size: 10px;">
                     <th rowspan="2" style="border-color: transparent; width: 80px;"></th>
                     <th>Nº Participación</th>
                     <th>Estado</th>
                     <th>Vendedor</th>
                     <th>Fecha Venta</th>
                     <th>Hora Venta</th>
                     <th></th>
                   </tr>
                   <tr style="font-size: 12px; font-weight: bolder; border-color: transparent;">
                     <td></td><td></td><td></td><td></td><td></td><td></td>
                   </tr>
                 </thead>
                 <tbody id="participations-body-${set.id}-digital"></tbody>
               </table>
             </div>
           </div>
         </div>`;
     }

           // Función para generar HTML de los tacos
      function generarHTMLTacos(tacos, set) {
        let html = '';
        
        // Obtener el número de participaciones por taco desde el set
        const participationsPerBook = set.participations_per_book || 50;
        
        tacos.forEach(taco => {
          const startParticipation = (taco.bookNumber - 1) * participationsPerBook + 1;
          const endParticipation = Math.min(taco.bookNumber * participationsPerBook, set.total_participations || 1000);
          
          // Generar el formato de participation_code para el rango
          const startCode = `${set.set_number || 1}/${String(startParticipation).padStart(5, '0')}`;
          const endCode = `${set.set_number || 1}/${String(endParticipation).padStart(5, '0')}`;
          const participationsRange = `${startCode} - ${endCode}`;
          
          html += `
            <div class="form-card bs mb-2" style="margin: 5px;">
              <table class="table table-striped table-condensed table nowrap w-100 mb-0">
                <thead>
                  <tr style="font-size: 10px;">
                    
                    <th>Nº Taco</th>
                    <th>Participaciones</th>
                    <th>Nº Participaciones</th>
                    <th>Ventas Registradas</th>
                    <th>Participaciones Devueltas</th>
                    <th>Participaciones Disponibles</th>
                    <th></th>
                  </tr>
                  <tr style="font-size: 12px; font-weight: bolder; border-color: transparent;">
                    <td>${set.set_number || set.id}/${String(taco.bookNumber).padStart(4, '0')}</td>
                    <td>${taco.totalParticipations}</td>
                    <td>${participationsRange}</td>
                    <td>${taco.salesRegistered}</td>
                    <td>${taco.returnedParticipations}</td>
                    <td>${taco.availableParticipations}</td>
                    <td>
                      <a class="btn btn-sm btn-light show-details-taco" data-set-id="${set.id}" data-book-number="${taco.bookNumber}" data-participations='${JSON.stringify(taco.participations)}'><img src="{{url('assets/form-groups/eye.svg')}}" alt="" width="12"></a>
                    </td>
                  </tr>
                </thead>
              </table>
              
                             <!-- Sección desplegable para mostrar participaciones -->
               <div class="part-information" style="height: 0px; overflow: hidden; transition: height 0.5s ease;">
                 <div style="height: 250px; overflow: auto;" id="list-participations-taco-${set.id}-${taco.bookNumber}" class="">
                   <table class="table table-striped table-condensed table nowrap w-100 mb-0">
                     <thead>
                       <tr style="font-size: 10px;">
                         <th rowspan="2" style="border-color: transparent; width: 80px;">
                           <div style="background-color: #333; padding: 10px 5px; border-radius: 12px; text-align: center; display:none">
                             <img src="{{url('assets/ticket.svg')}}" alt="" width="30px">
                           </div>
                         </th>
                         <th>Nº Participación</th>
                         <th>Estado</th>
                         <th>Vendedor</th>
                         <th>Fecha Venta</th>
                         <th>Hora Venta</th>
                         <th></th>
                       </tr>
                       <tr style="font-size: 12px; font-weight: bolder; border-color: transparent;">
                         <td></td>
                         <td></td>
                         <td></td>
                         <td></td>
                         <td></td>
                         <td></td>
                       </tr>
                     </thead>
                     <tbody id="participations-body-${set.id}-${taco.bookNumber}">
                       <!-- Las participaciones se cargarán dinámicamente aquí -->
                     </tbody>
                   </table>
                 </div>
               </div>
            </div>
          `;
        });
        
        return html;
      }

     // Event listeners para el tab de participaciones
     $(document).on('change', '.seleccionar-reserva-participaciones', function() {
       const reservaId = $(this).data('reserva-id');
       reservaSeleccionadaParticipaciones = { id: reservaId };
       $('#btn-siguiente-reservas-participaciones').prop('disabled', false);
     });

     $(document).on('click', '#tabla-reservas-participaciones tbody tr', function(e) {
       const $radio = $(this).find('.seleccionar-reserva-participaciones');
       if ($radio.length && !$(e.target).is('a, button') && !$(e.target).closest('.form-check').length) {
         $radio.prop('checked', true).trigger('change');
       }
     });

     $(document).on('click', '#btn-siguiente-reservas-participaciones', function() {
       if (reservaSeleccionadaParticipaciones) {
         cargarTacosVendedor(reservaSeleccionadaParticipaciones.id);
         $('#paso-reservas-participaciones').hide();
         $('#paso-tacos-participaciones').show();
       }
     });

     $(document).on('click', '#btn-volver-reservas-participaciones', function() {
       $('#paso-tacos-participaciones').hide();
       $('#paso-reservas-participaciones').show();
     });

           // Inicializar el tab de participaciones cuando se active
      $('[data-bs-target="#participaciones"]').click(function() {
        if (!tablaReservasParticipaciones) {
          cargarReservasParticipaciones();
        }
      });

      // Event listener para mostrar/ocultar participaciones de tacos
      $(document).on('click', '.show-details-taco', function(e) {
        e.preventDefault();
        
        const setId = $(this).data('set-id');
        const bookNumber = $(this).data('book-number');
        const participations = $(this).data('participations');
        const partInformation = $(this).closest('.form-card').find('.part-information');
        
        if (partInformation.css('height') == '0px') {
          // Abrir el desplegable
          partInformation.css('height', '250px');
          
          // Cargar las participaciones en la tabla
          const tbody = $(`#participations-body-${setId}-${bookNumber}`);
          tbody.empty();
          
          if (participations && participations.length > 0) {
            participations.forEach(participation => {
              // Validar y formatear fecha de venta
              let saleDate = 'N/A';
              if (participation.sale_date) {
                const fecha = new Date(participation.sale_date);
                if (!isNaN(fecha.getTime())) {
                  saleDate = fecha.toLocaleDateString('es-ES');
                }
              }
              const saleTime = participation.sale_time ? participation.sale_time : 'N/A';
              const status = participation.status || 'asignada';
              const statusLabel = participation.status_text || (
                status === 'pagada' ? 'Pagada'
                : status === 'vendida' ? 'Vendida'
                : status === 'devuelta' ? 'Devuelta'
                : status === 'reserva_venta_digital' ? 'Venta digital pendiente'
                : 'Asignada'
              );
              const statusClass = status === 'pagada' ? 'bg-info'
                : status === 'vendida' ? 'bg-primary'
                : status === 'devuelta' ? 'bg-warning text-dark'
                : status === 'reserva_venta_digital' ? 'bg-secondary'
                : (participation.status_text === 'DISPONIBLE DV' ? 'bg-info' : 'bg-success');
              
              tbody.append(`
                <tr style="font-size: 12px; font-weight: bolder; border-color: transparent;">
                  <td style="width: 80px;">
                    <div style="background-color: #333; padding: 10px 5px; border-radius: 12px; text-align: center;">
                      <img src="{{url('assets/ticket.svg')}}" alt="" width="30px">
                    </div>
                  </td>
                  <td>${participation.participation_code}</td>
                  <td><label class="badge ${statusClass}">${statusLabel}</label></td>
                  <td>{{ $seller->name ?? 'N/A' }}</td>
                  <td>${saleDate}</td>
                  <td>${saleTime}</td>
                  <td>
                    <div class="d-flex gap-2">
                      <button class="btn btn-sm btn-light" onclick="verDetalleParticipacionTaco('${participation.participation_code}', ${participation.id})" title="Ver detalle">
                        <img src="{{url('assets/form-groups/eye.svg')}}" alt="" width="12">
                      </button>
                      <button class="btn btn-sm btn-danger" onclick="eliminarParticipacionTaco('${participation.participation_code}', ${participation.id})">
                        <i class="ri-delete-bin-line"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              `);
            });
                     } else {
             tbody.append(`
               <tr style="font-size: 12px; font-weight: bolder; border-color: transparent;">
                 <td colspan="7" class="text-center">No hay participaciones en este taco</td>
               </tr>
             `);
           }
        } else {
          // Cerrar el desplegable
          partInformation.css('height', '0px');
        }
      });

     

           // Función para ver detalle de participación desde el grid
      window.verDetalleParticipacion = function(codigo, participationId) {
        const url = `{{ url('/') }}/participations/view/${participationId}?from_seller={{ $seller->id }}`;
        window.open(url, '_blank');
      };

      // Función para ver detalle de participación desde el taco
      window.verDetalleParticipacionTaco = function(codigo, participationId) {
        const url = `{{ url('/') }}/participations/view/${participationId}?from_seller={{ $seller->id }}`;
        window.open(url, '_blank');
      };

      // Función para eliminar participación desde el taco
      window.eliminarParticipacionTaco = function(codigo, participationId) {
        if (confirm('¿Estás seguro de que quieres eliminar esta participación?')) {
          // Eliminar de la base de datos
          $.ajax({
            url: '{{ route("sellers.remove-assignment") }}',
            method: 'POST',
            data: {
              participation_id: participationId,
              seller_id: {{ $seller->id }},
              _token: '{{ csrf_token() }}'
            },
            success: function(response) {
              if (response.success) {
                // Mostrar mensaje de éxito
                mostrarMensaje('Participación eliminada correctamente', 'success');
                
                // Recargar los tacos para actualizar la información
                if (reservaSeleccionadaParticipaciones) {
                  cargarTacosVendedor(reservaSeleccionadaParticipaciones.id);
                }
              } else {
                mostrarMensaje(response.message || 'Error al eliminar la participación', 'error');
              }
            },
            error: function(xhr, status, error) {
              mostrarMensaje('Error de conexión al eliminar la participación', 'error');
            }
          });
        }
      };

      // ========================================
      // LIQUIDACIÓN DE VENDEDOR
      // ========================================
      
      let sorteoSeleccionadoLiquidacion = null;

      // Event listener para cambio de sorteo
      $('#selector-sorteo-liquidacion').on('change', function() {
          const lotteryId = $(this).val();
          
          if (lotteryId) {
              sorteoSeleccionadoLiquidacion = lotteryId;
              cargarResumenLiquidacion();
              cargarHistorialLiquidaciones();
              $('#resumen-liquidacion-container').show();
          } else {
              sorteoSeleccionadoLiquidacion = null;
              $('#resumen-liquidacion-container').hide();
          }
      });

      // Función para cargar resumen de liquidación
      function cargarResumenLiquidacion() {
          if (!sorteoSeleccionadoLiquidacion) return;

          $.ajax({
              url: '{{ route("sellers.settlement-summary") }}',
              method: 'GET',
              data: {
                  seller_id: {{ $seller->id }},
                  lottery_id: sorteoSeleccionadoLiquidacion
              },
              success: function(response) {
                  console.log('=== RESPUESTA SETTLEMENT SUMMARY ===');
                  console.log(response);
                  
                  if (response.success) {
                      const summary = response.summary;
                      console.log('Summary:', summary);
                      
                      // Parsear valores a números
                      const pricePerParticipation = parseFloat(summary.price_per_participation) || 0;
                      const totalAmount = parseFloat(summary.total_amount) || 0;
                      const totalPaid = parseFloat(summary.total_paid) || 0;
                      const pendingAmount = parseFloat(summary.pending_amount) || 0;
                      const liquidatedParticipations = parseFloat(summary.liquidated_participations) || 0;
                      const pendingParticipations = parseFloat(summary.pending_participations) || 0;
                      
                      $('#settlement-total-participations').text(summary.total_participations);
                      $('#settlement-price-per-participation').text(pricePerParticipation.toFixed(2) + '€');
                      $('#settlement-total-amount').text(totalAmount.toFixed(2) + '€');
                      $('#settlement-total-paid').text(totalPaid.toFixed(2) + '€');
                      $('#settlement-liquidated-participations').text(liquidatedParticipations.toFixed(2));
                      $('#settlement-pending-amount').text(pendingAmount.toFixed(2) + '€');
                      $('#settlement-pending-participations').text(pendingParticipations.toFixed(2));
                      $('#settlement-pendiente-display').text(pendingAmount.toFixed(2) + '€');
                      
                      console.log('Datos actualizados en la vista');
                      
                      // Resetear campos de pago
                      actualizarTotalPagarAhoraSettlement();
                  } else {
                      console.error('Response no exitoso:', response);
                  }
              },
              error: function(xhr, status, error) {
                  console.error('Error al cargar resumen:', error);
                  mostrarMensaje('Error al cargar el resumen de liquidación', 'error');
              }
          });
      }

      // Función para actualizar total a pagar ahora
      function actualizarTotalPagarAhoraSettlement() {
          const efectivo = parseFloat($('#settlement-pago-efectivo').val()) || 0;
          const bizum = parseFloat($('#settlement-pago-bizum').val()) || 0;
          const transferencia = parseFloat($('#settlement-pago-transferencia').val()) || 0;
          
          const totalPagarAhora = efectivo + bizum + transferencia;
          $('#settlement-pagar-ahora').text(totalPagarAhora.toFixed(2) + '€');
          
          const pendiente = parseFloat($('#settlement-pending-amount').text().replace('€', '').replace(',', '.')) || 0;
          const quedaraPendiente = pendiente - totalPagarAhora;
          
          $('#settlement-quedara-pendiente').text(quedaraPendiente.toFixed(2) + '€');
          
          if (quedaraPendiente <= 0 && totalPagarAhora > 0) {
              $('#settlement-quedara-pendiente').removeClass('text-warning').addClass('text-success');
          } else if (totalPagarAhora > 0) {
              $('#settlement-quedara-pendiente').removeClass('text-success').addClass('text-warning');
          } else {
              $('#settlement-quedara-pendiente').removeClass('text-success text-warning');
          }
      }

      // Event listeners para actualizar totales
      $('.settlement-payment-input').on('input', actualizarTotalPagarAhoraSettlement);

      // Botón para registrar liquidación
      $('#btn-registrar-liquidacion').on('click', function() {
          if (!sorteoSeleccionadoLiquidacion) {
              mostrarMensaje('Debes seleccionar un sorteo primero', 'warning');
              return;
          }

          // Recopilar pagos
          const pagos = [];
          
          const efectivo = parseFloat($('#settlement-pago-efectivo').val()) || 0;
          if (efectivo > 0) {
              pagos.push({ payment_method: 'efectivo', amount: efectivo });
          }
          
          const bizum = parseFloat($('#settlement-pago-bizum').val()) || 0;
          if (bizum > 0) {
              pagos.push({ payment_method: 'bizum', amount: bizum });
          }
          
          const transferencia = parseFloat($('#settlement-pago-transferencia').val()) || 0;
          if (transferencia > 0) {
              pagos.push({ payment_method: 'transferencia', amount: transferencia });
          }

          if (pagos.length === 0) {
              mostrarMensaje('Debes ingresar al menos un monto de pago', 'warning');
              return;
          }

          // Deshabilitar botón
          $(this).prop('disabled', true).text('Procesando...');

          $.ajax({
              url: '{{ route("sellers.settlement.store") }}',
              method: 'POST',
              data: {
                  seller_id: {{ $seller->id }},
                  lottery_id: sorteoSeleccionadoLiquidacion,
                  pagos: pagos,
                  _token: '{{ csrf_token() }}'
              },
              success: function(response) {
                  if (response.success) {
                      mostrarMensaje('Liquidación registrada correctamente', 'success');
                      
                      // Limpiar campos de pago
                      $('#settlement-pago-efectivo, #settlement-pago-bizum, #settlement-pago-transferencia').val('');
                      
                      // Recargar datos
                      setTimeout(() => {
                          cargarResumenLiquidacion();
                          cargarHistorialLiquidaciones();
                      }, 1000);
                  } else {
                      mostrarMensaje(response.message || 'Error al registrar liquidación', 'error');
                  }
              },
              error: function(xhr, status, error) {
                  console.error('Error:', error);
                  mostrarMensaje('Error al registrar la liquidación', 'error');
              },
              complete: function() {
                  $('#btn-registrar-liquidacion').prop('disabled', false).html('<i class="ri-add-line"></i> Registrar Liquidación');
              }
          });
      });

      // Función para cargar historial de liquidaciones
      function cargarHistorialLiquidaciones() {
          if (!sorteoSeleccionadoLiquidacion) return;

          $.ajax({
              url: '{{ route("sellers.settlement-history") }}',
              method: 'GET',
              data: {
                  seller_id: {{ $seller->id }},
                  lottery_id: sorteoSeleccionadoLiquidacion
              },
              success: function(response) {
                  if (response.success && response.settlements.length > 0) {
                      let html = '<div class="table-responsive"><table class="table table-sm table-hover"><thead class="table-light"><tr><th>Fecha</th><th>Participaciones Liquidadas</th><th>Monto Pagado</th><th>Métodos de Pago</th></tr></thead><tbody>';
                      
                      response.settlements.forEach(settlement => {
                          // Validar y formatear fecha de liquidación
                          let fecha = 'N/A';
                          if (settlement.settlement_date) {
                              const fechaObj = new Date(settlement.settlement_date);
                              if (!isNaN(fechaObj.getTime())) {
                                  fecha = fechaObj.toLocaleDateString('es-ES');
                              }
                          }
                          
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
                              metodos.push(`${icono} ${paymentAmount.toFixed(2)}€`);
                          });
                          
                          const calculatedParts = parseFloat(settlement.calculated_participations) || 0;
                          const paidAmount = parseFloat(settlement.paid_amount) || 0;
                          
                          html += `
                              <tr>
                                  <td>${fecha}</td>
                                  <td>${calculatedParts.toFixed(2)}</td>
                                  <td class="fw-bold text-success">${paidAmount.toFixed(2)}€</td>
                                  <td>${metodos.join(', ')}</td>
                              </tr>
                          `;
                      });
                      
                      html += '</tbody></table></div>';
                      $('#historial-liquidaciones-container').html(html);
                  } else {
                      $('#historial-liquidaciones-container').html('<p class="text-muted text-center">No hay liquidaciones registradas para este sorteo</p>');
                  }
              },
              error: function(xhr, status, error) {
                  console.error('Error al cargar historial:', error);
                  $('#historial-liquidaciones-container').html('<p class="text-danger text-center">Error al cargar el historial</p>');
              }
          });
      }
  });

  // Toggle estado vendedor (AJAX)
  document.getElementById('seller-toggle-status') && document.getElementById('seller-toggle-status').addEventListener('click', function() {
      var btn = this;
      var sellerId = {{ $seller->id }};
      btn.disabled = true;
      fetch('{{ route("sellers.toggle-status", $seller->id) }}', {
          method: 'POST',
          headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': '{{ csrf_token() }}',
              'Accept': 'application/json'
          },
          body: JSON.stringify({})
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
          if (data.success) {
              var input = document.getElementById('seller-status-input');
              var badge = document.getElementById('seller-status-badge');
              input.value = data.status_text;
              badge.textContent = data.status_text;
              badge.className = 'badge ' + data.status_class + ' mt-2';
          } else {
              alert(data.message || 'Error al cambiar el estado');
          }
      })
      .catch(function() { alert('Error al cambiar el estado'); })
      .finally(function() { btn.disabled = false; });
  });

</script>

@endsection
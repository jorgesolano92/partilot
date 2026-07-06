@extends('layouts.layout')

@section('title','Administraciones')

@section('content')

<style>
	
	.form-wizard-element, .form-wizard-element label {
		cursor: pointer;
	}
	.form-check-input:checked {
		border-color: #333;
	}
</style>

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
            	<div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Administraciones</a></li>
                        <li class="breadcrumb-item active">Administración</li>
                    </ol>
                </div>
                <h4 class="page-title">Administraciones</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

            		<h4 class="header-title">

                    	Datos Administración

                    </h4>

                    <br>

                    <div class="row">
                    	
                    	<div class="col-md-3" style="position: relative;">

                    		<ul class="form-card bs mb-3 nav">

                    			<li class="nav-item">
	                    			<div class="form-wizard-element active" data-bs-toggle="tab" data-bs-target="#datos_legales">
	                    				
	                    				<span>
	                    					&nbsp;&nbsp;
	                    				</span>

	                    				<img src="{{url('assets/admin.svg')}}" alt="">

	                    				<label>
	                    					Datos administración
	                    				</label>

	                    			</div>
                    			</li>

	                    		<li class="nav-item">
	                    			<div class="form-wizard-element" data-bs-toggle="tab" data-bs-target="#datos_contacto">
	                    				
	                    				<span>
	                    					&nbsp;&nbsp;
	                    				</span>

	                    				<img src="{{url('assets/gestor.svg')}}" alt="">

	                    				<label>
	                    					Datos Gestor
	                    				</label>

	                    			</div>

	                    		</li>

	                    		<li class="nav-item">

	                    			<div class="form-wizard-element" data-bs-toggle="tab" data-bs-target="#configuracion_api">
	                    				
	                    				<span>
	                    					&nbsp;&nbsp;
	                    				</span>

	                    				<img src="{{url('assets/api.svg')}}" alt="">

	                    				<label>
	                    					Configuración API
	                    				</label>

	                    			</div>
	                    		</li>
                    			
                    		</ul>

                    		<div class="form-card show-content bs mb-3">
                    			<h4 class="mb-0 mt-1">
                    				Página web
                    			</h4>
                    			<small><i>Este campo no es obligatorio</i></small>

                    			<div class="form-group mt-2">
	                    			<label class="label-control">Web</label>

	                    			<div class="input-group input-group-merge group-form" style="border: none">

	                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
	                                        <img src="{{url('assets/form-groups/admin/0.svg')}}" alt="">
	                                    </div>

	                                    <input readonly="" value="{{$administration->web ?? ''}}" class="form-control" type="text" placeholder="www.administracion.es" style="border-radius: 0 30px 30px 0;">
	                                </div>
                    			</div>
                    		</div>

                    		<div class="form-card bs">
                    			<h4 class="mb-0 mt-1">
                    				Estado Administración
                    			</h4>
                    			<small><i>Bloquea o desbloquea la administración</i></small>

                    			<div class="form-group mt-2">
	                    			<label class="">Estado Actual</label> 
	                    			@php
	                    				$statusValue = $administration->status;
	                    				if ($statusValue === null || $statusValue === -1) {
	                    					$statusText = 'Pendiente';
	                    					$statusClass = 'bg-secondary';
	                    				} elseif ($statusValue == 1) {
	                    					$statusText = 'Activo';
	                    					$statusClass = 'bg-success';
	                    				} else {
	                    					$statusText = 'Inactivo';
	                    					$statusClass = 'bg-danger';
	                    				}
	                    			@endphp
	                    			<div class="input-group input-group-merge group-form">
	                    				<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
	                    					<img src="{{url('assets/form-groups/admin/13.svg')}}" alt="">
	                    				</div>
	                    				<input class="form-control" type="text" value="{{ $statusText }}" id="admin-status-input" style="border-radius: 0 !important; border-bottom: 1px solid #dee2e6;" readonly>
	                    				<button type="button" class="btn btn-sm btn-outline-secondary" id="admin-toggle-status" title="Cambiar estado" style="border-radius: 0 30px 30px 0; border-left: none;">Cambiar</button>
	                    			</div>
	                    			<span class="badge badge-lg {{ $statusClass }} mt-2" id="admin-status-badge" style="display: none;">{{ $statusText }}</span>
	                    			<div style="clear: both;"></div>
                    			</div>
                    		</div>

                    		<a href="{{url('administrations')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9">

                    		<div class="tabbable show-content">
                    			
                    			<div class="tab-content p-0">
                    				
                    				<div class="tab-pane fade active show" id="datos_legales">
                    					<div class="form-card bs" style="min-height: 658px;">
			                    			<h4 class="mb-0 mt-1">
			                    				Datos legales de la administración

			                    				<a href="{{url('administrations/edit/'.$administration->id)}}" class="btn btn-light float-end" style="border: 1px solid silver; border-radius: 30px;"> 
			                    				<img src="{{url('assets/form-groups/edit.svg')}}" alt="">
			                    				Editar</a>
			                    			</h4>
			                    			<small><i>Todos los campos son obligatorios</i></small>
			                    			<div style="clear: both;"></div>

			                    			<div class="form-group mt-2 mb-3">

			                    				<div class="row">
			                    					
				                    				<div class="col-1">
				                    						
					                    				<div class="photo-preview-3" style="background-image: url({{url('images/'.$administration->image)}});">
					                    					
					                    					@if($administration->image)

					                    					@else
					                    						<i class="ri-account-circle-fill"></i>
					                    					@endif

					                    				</div>
					                    				
					                    				<div style="clear: both;"></div>
			                    					</div>

			                    					<div class="col-4 text-center">

			                    						<h4 class="mt-3 mb-0">{{$administration->name ?? 'Sin nombre'}}</h4>

			                    						<small>@if($administration->manager?->user?->isPanelAccount())Acceso al panel @elseif($administration->manager?->user){{ $administration->manager?->user?->name }} {{ $administration->manager?->user?->last_name }}@else Sin gestor principal @endif</small> <br>
			                    						
			                    					</div>
			                    				</div>

			                    				<div style="clear: both;"></div>
			                    			</div>

			                    			
			                    			<br>

			                    			<div>
			                    				<div class="row">
			                    					
			                    					<div class="col-4">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Nombre comercial</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/1.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{$administration->name ?? ''}}" class="form-control" type="text" placeholder="Nombre Administración" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>
			                    					<div class="col-3">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Nº Receptor</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/2.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{$administration->receiving ?? ''}}" class="form-control" type="number" placeholder="000000" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
						                    		</div>
			                    					
			                    					<div class="col-5">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Nombre Autónomo / Sociedad</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/3.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{$administration->society ?? ''}}" class="form-control" type="text" placeholder="José Andrés / Administración S.L.U." style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-3">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">NIF/CIF</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/4.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{$administration->nif_cif ?? ''}}" class="form-control" type="text" placeholder="B26262626" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-3">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Provincia</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/5.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{$administration->province ?? ''}}" class="form-control" type="text" placeholder="Provincia" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-4">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Localidad</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/6.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{$administration->city ?? ''}}" class="form-control" type="text" placeholder="Localidad" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-2">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Código Postal</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/7.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{$administration->postal_code ?? ''}}" class="form-control" type="number" placeholder="C.P." style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-4">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Dirección</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/8.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{$administration->address ?? ''}}" class="form-control" type="text" placeholder="Dirección" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-4">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Email</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/9.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{$administration->email ?? ''}}" class="form-control" type="email" placeholder="ejemplo@cuentaemail.com" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-4">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Teléfono</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/10.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{$administration->phone ?? ''}}" class="form-control" type="phone" placeholder="940 200 200" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    				</div>

			                    				@if(isset($panelUser) && $panelUser)
			                    				<div class="row mt-3">
			                    					<div class="col-12">
			                    						<h4 class="mb-0 mt-1">Acceso al panel web</h4>
			                    						<small class="text-muted">Usuario fijo (no se puede cambiar). El correo con el enlace se envía al email de contacto de la administración.</small>
			                    					</div>
			                    					<div class="col-md-4 mt-2">
			                    						<label class="label-control">Usuario de acceso</label>
			                    						<div class="input-group input-group-merge group-form">
			                    							<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
			                    								<i class="ri-user-line"></i>
			                    							</div>
			                    							<input readonly class="form-control" value="{{ $panelUser->panel_login_username ?? '—' }}" style="border-radius: 0 30px 30px 0;">
			                    						</div>
			                    					</div>
			                    					<div class="col-md-8 mt-2 d-flex align-items-end">
			                    						<form method="post" action="{{ route('administrations.send-panel-access', $administration) }}" class="d-inline" onsubmit="return confirm('¿Enviar correo con usuario y enlace para establecer contraseña?');">
			                    							@csrf
			                    							<button type="submit" class="btn btn-dark" style="border-radius: 30px;">
			                    								<i class="ri-mail-send-line"></i> Enviar correo de acceso al panel
			                    							</button>
			                    						</form>
			                    						<a href="{{ route('administrations.edit', $administration->id) }}" class="btn btn-light ms-2" style="border: 1px solid silver; border-radius: 30px;">Cambiar contraseña del panel</a>
			                    					</div>
			                    				</div>
			                    				@endif

			                    			</div>

			                    			<h4 class="mb-0 mt-1">
			                    				Datos Pago
			                    			</h4>
			                    			<small><i>Introduzca los detalles de su cuenta bancaria para procesar los pagos. Asegúrese de que la <br> información es correcta y está actualizada</i></small>

			                    			<div class="row">
			                    				
			                    				<div class="col-8">

			                    					@php
			                    						$accountValue = $administration->account ?? '';
			                    						// Si empieza con ES, quitarlo para mostrar solo los dígitos
			                    						if (str_starts_with($accountValue, 'ES')) {
			                    							$accountValue = substr($accountValue, 2);
			                    						} else {
			                    							// Si es formato antiguo (con espacios), intentar convertir
			                    							$accountParts = explode(' ', $accountValue);
			                    							if (count($accountParts) >= 5) {
			                    								// Formato antiguo: 4-4-4-2-10
			                    								$accountValue = str_pad($accountParts[0] ?? '', 4, '0', STR_PAD_LEFT) .
			                    															str_pad($accountParts[1] ?? '', 4, '0', STR_PAD_LEFT) .
			                    															str_pad($accountParts[2] ?? '', 4, '0', STR_PAD_LEFT) .
			                    															str_pad($accountParts[3] ?? '', 2, '0', STR_PAD_LEFT) .
			                    															str_pad($accountParts[4] ?? '', 10, '0', STR_PAD_LEFT);
			                    							} else {
			                    								$accountValue = str_replace(' ', '', $accountValue);
			                    							}
			                    						}
			                    						// Formatear con máscara: 12 1234 1234 12 123456789
			                    						if ($accountValue) {
			                    							$numbers = str_replace(' ', '', $accountValue);
			                    							if (strlen($numbers) >= 2) {
			                    								$formatted = substr($numbers, 0, 2);
			                    								if (strlen($numbers) > 2) {
			                    									$formatted .= ' ' . substr($numbers, 2, 4);
			                    									if (strlen($numbers) > 6) {
			                    										$formatted .= ' ' . substr($numbers, 6, 4);
			                    										if (strlen($numbers) > 10) {
			                    											$formatted .= ' ' . substr($numbers, 10, 2);
			                    											if (strlen($numbers) > 12) {
			                    												$formatted .= ' ' . substr($numbers, 12, 10);
			                    											}
			                    										}
			                    									}
			                    								}
			                    								$accountValue = $formatted;
			                    							}
			                    						}
			                    					@endphp
			                    					
			                    					<div class="form-group mt-2">
						                    			<div class="input-group input-group-merge group-form">
						                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
						                                        <span style="font-weight: bold;">ES</span>
						                                    </div>
						                                    <input readonly="" class="form-control" type="text" value="{{ $accountValue }}" placeholder="12 1234 1234 12 123456789" style="border-radius: 0 30px 30px 0;">
						                                </div>
					                    			</div>
					                    		</div>

			                    				<div class="col-4 text-end">
			                    					
			                    				</div>

			                    			</div>

			                    			@include('admins.partials.billing_payment_card')

			                    			@include('admins.partials.contract_card')

			                    		</div>
                    				</div>

                    				<div class="tab-pane fade" id="datos_contacto">
                    					<div class="form-card bs" style="min-height: 658px;">
			                    			@php $adminPrimaryManagerUser = $administration->manager?->user; @endphp

			                    			@if(!$adminPrimaryManagerUser)
			                    				<div class="alert alert-warning mb-3">No hay gestor principal asignado o el usuario fue eliminado. Use <strong>Editar</strong> arriba para registrar un gestor.</div>
			                    			@endif

			                    			<h4 class="mb-0 mt-1">
			                    				Datos de contacto

			                    				<a href="{{ route('administrations.edit-manager', $administration->id) }}" class="btn btn-light float-end" style="border: 1px solid silver; border-radius: 30px;"> 
			                    				<img src="{{url('assets/form-groups/edit.svg')}}" alt="">
			                    				Editar</a>
			                    			</h4>
			                    			<small><i>Todos los campos son obligatorios</i></small>
			                    			<div style="clear: both;"></div>

			                    			<div class="form-group mt-2 mb-3 admin-box">

			                    				<div class="row">
			                    					<div class="col-1">
			                    						
					                    				<div class="photo-preview-2">
					                    					
					                    					@if($adminPrimaryManagerUser && $adminPrimaryManagerUser->image)
					                    						<img src="{{ url('manager/'.$adminPrimaryManagerUser->image) }}" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">
					                    					@else
					                    						<i class="ri-account-circle-fill"></i>
					                    					@endif

					                    				</div>
					                    				
					                    				<div style="clear: both;"></div>
			                    					</div>

			                    					<div class="col-4 text-center">

			                    						<h4 class="mt-0 mb-0">{{$administration->name ?? 'Sin nombre'}}</h4>

			                    						<small>{{ $adminPrimaryManagerUser?->name ?? '' }} {{ $adminPrimaryManagerUser?->last_name ?? '' }}</small> <br>

			                    						<i style="position: relative; top: 3px; font-size: 16px; color: #333" class="ri-computer-line"></i> {{$administration->receiving ?? ''}}
			                    						
			                    					</div>

			                    					<div class="col-4">

			                    						<div class="mt-2">
			                    							Provincia: {{$administration->province ?? ''}} <br>
			                    							Dirección: {{$administration->address ?? ''}}
			                    						</div>
			                    						
			                    					</div>

			                    					<div class="col-3">

			                    						<div class="mt-2">
			                    							Ciudad: {{$administration->city ?? ''}} <br>
			                    							Tel: {{$administration->phone ?? ''}}
			                    						</div>
			                    						
			                    					</div>
			                    				</div>
			                    			</div>

			                    			
			                    			<br>

			                    			<div>

			                    				<div class="row">
			                    					
			                    					<div class="col-4">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Nombre</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                      	<img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $adminPrimaryManagerUser?->name ?? '' }}" class="form-control" type="text" placeholder="Nombre" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>
			                    					<div class="col-4">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Primer Apellido</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $adminPrimaryManagerUser?->last_name ?? '' }}" class="form-control" type="text" placeholder="Primer Apellido" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-4">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Segundo Apellido</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $adminPrimaryManagerUser?->last_name2 ?? '' }}" class="form-control" type="text" placeholder="Segundo Apellido" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>
			                    					
			                    					<div class="col-2">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">NIF/CIF</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/4.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $adminPrimaryManagerUser?->nif_cif ?? '' }}" class="form-control" type="text" placeholder="12345678A" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-3">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Fecha de Nacimiento</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $adminPrimaryManagerUser?->birthday?->format('Y-m-d') ?? '' }}" class="form-control" type="text" placeholder="1985-03-15" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-4">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Email</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/9.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $adminPrimaryManagerUser?->email ?? '' }}" class="form-control" type="email" placeholder="ejemplo@cuentaemail.com" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-3">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">Teléfono</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/10.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $adminPrimaryManagerUser?->phone ?? '' }}" class="form-control" type="phone" placeholder="940 200 200" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    				</div>
			                    				
			                    			</div>

			                    			<h4 class="mb-0 mt-1">
			                    				Comentarios
			                    			</h4>
			                    			<small><i>Puedes añadir un comentario si necesitas añadir información adicional <br> sobre la entidad. Puedes añadir comentarios mas tarde.</i></small>

			                    			<div class="row">
			                    				
			                    				<div class="col-8">
			                    					
			                    					<div class="form-group mt-2">
						                    			<label class="label-control">Comentario</label>

						                    			<div class="input-group input-group-merge group-form" style="border: none">

						                                    <textarea readonly="" class="form-control" placeholder="Añade tu comentario" name="" id="" rows="6">{{ $adminPrimaryManagerUser?->comment ?? '' }}</textarea>
						                                </div>
					                    			</div>

			                    				</div>

			                    				<div class="col-4 text-end">
			                    					
			                    				</div>

			                    			</div>
			                    			

			                    		</div>
                    				</div>

                    				<div class="tab-pane fade" id="configuracion_api">
                    					@php
                    						$prepagoService = app(\App\Services\PrepagoCodigosService::class);
                    						$canGenerateCodes = $prepagoService->canGenerateCodes($administration);
                    						$usesPartilot = $administration->prepago_use_partilot_default;
                    						$hasOwnConfig = $prepagoService->hasOwnConfig($administration);
                    						$resolvedSource = $canGenerateCodes
                    						    ? ($hasOwnConfig ? 'administration' : 'partilot')
                    						    : null;
                    					@endphp
                    					<div class="form-card bs" style="min-height: 658px;">
			                    			<h4 class="mb-0 mt-1">
			                    				Datos generales API
			                    				<a href="{{ route('administrations.edit-api', $administration->id) }}" class="btn btn-light float-end" style="border: 1px solid silver; border-radius: 30px;">
			                    				<img src="{{ url('assets/form-groups/edit.svg') }}" alt="">
			                    				Editar</a>
			                    			</h4>
			                    			<small><i>Todos los campos son obligatorios</i></small>
			                    			<div style="clear: both;"></div>

			                    			<div class="row">
		                    					<div class="col-7">
		                    						<div class="form-group mt-2 mb-3">
		                    							<label class="label-control">Nombre de la integración</label>
		                    							<div class="input-group input-group-merge group-form">
		                    								<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
		                    									<img src="{{ url('assets/form-groups/admin/1.svg') }}" alt="">
		                    								</div>
		                    								<input class="form-control" type="text" readonly value="{{ $administration->prepago_integration_name ?: '—' }}" placeholder="Nombre Integración" style="border-radius: 0 30px 30px 0;">
		                    							</div>
		                    							<small><i>Ayuda: Un nombre fácil de recordar para identificar esta configuración</i></small>
		                    						</div>
		                    					</div>
		                    					<div class="col-5">
		                    						<div class="form-group mb-3">
		                    							<div class="form-check form-switch mt-4" style="margin-top: 3rem !important;">
		                    								<input disabled style="float: right;" class="form-check-input bg-dark" type="checkbox" role="switch" id="api_status_view" {{ $administration->prepago_integration_enabled ? 'checked' : '' }}>
		                    								<label style="float: right; margin-right: 50px;" class="form-check-label" for="api_status_view"><b>Estado de la integración</b></label>
		                    							</div>
		                    						</div>
		                    					</div>
		                    				</div>

		                    				<h4 class="mb-0 mt-1">Datos generales API</h4>
		                    				<small><i>Todos los campos son obligatorios</i></small>

		                    				<div class="row">
		                    					<div class="col-7">
		                    						<div class="form-group mt-2 mb-3">
		                    							<label class="label-control">URL Base de la API (Endpoint)</label>
		                    							<div class="input-group input-group-merge group-form">
		                    								<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
		                    									<img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
		                    								</div>
		                    								<input class="form-control" type="text" readonly value="{{ $administration->prepago_api_url ?: '—' }}" placeholder="URL Base de la API" style="border-radius: 0 30px 30px 0;">
		                    							</div>
		                    						</div>
		                    					</div>
		                    					<div class="col-5">
		                    						<div class="form-group mt-2 mb-3">
		                    							<label class="label-control">Método de Autenticación</label>
		                    							<div class="input-group input-group-merge group-form">
		                    								<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
		                    									<img src="{{ url('assets/form-groups/admin/13.svg') }}" alt="">
		                    								</div>
		                    								<select class="form-control" disabled style="border-radius: 0 30px 30px 0;">
		                    									<option value="apikey" selected>Clave API (API Key)</option>
		                    								</select>
		                    							</div>
		                    						</div>
		                    					</div>
		                    				</div>

		                    				<div class="mt-3">
		                    					<h4 class="mb-0 mt-1">Clave API (API Key)</h4>
		                    					<div class="row">
		                    						<div class="col-4">
		                    							<div class="form-group mt-2 mb-3">
		                    								<label class="label-control">Prefijo del código</label>
		                    								<div class="input-group input-group-merge group-form">
		                    									<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
		                    										<img src="{{ url('assets/form-groups/admin/4.svg') }}" alt="">
		                    									</div>
		                    									<input class="form-control" type="text" readonly value="{{ $administration->prepago_api_prefix ?: '—' }}" style="border-radius: 0 30px 30px 0;">
		                    								</div>
		                    							</div>
		                    						</div>
		                    						<div class="col-8">
		                    							<div class="form-group mt-2 mb-3">
		                    								<label class="label-control">API Key</label>
		                    								<input class="form-control" type="text" readonly value="{{ $hasOwnConfig ? 'Configurada' : '—' }}" placeholder="API Key" style="border-radius: 30px;">
		                    							</div>
		                    						</div>
		                    					</div>
		                    				</div>

		                    				<hr class="my-3">

		                    				<div class="form-check mb-2">
		                    					<input class="form-check-input" type="checkbox" disabled {{ $usesPartilot ? 'checked' : '' }}>
		                    					<label class="form-check-label">Usar integración por defecto de PARTILOT (.env) si no hay configuración propia completa</label>
		                    				</div>
		                    				@if($canGenerateCodes)
		                    					<small class="text-muted d-block">Generación de códigos activa — origen actual: <strong>{{ $resolvedSource === 'administration' ? 'integración propia' : 'PARTILOT (.env)' }}</strong>.</small>
		                    				@else
		                    					<small class="text-muted d-block">Sin integración operativa: las donaciones desde la app serán solo a entidad (100% del premio, sin código de recarga).</small>
		                    				@endif
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

@endsection

@section('scripts')

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.location.hash === '#configuracion_api') {
        var tabEl = document.querySelector('[data-bs-target="#configuracion_api"]');
        if (tabEl) {
            bootstrap.Tab.getOrCreateInstance(tabEl).show();
        }
    }
});
</script>

<script>

	$('.form-wizard-element').click(function(event) {
		event.preventDefault();

		let target = $(this).data('target');

	    let tab = new bootstrap.Tab(document.querySelector(target));
	    console.log(tab);
	    tab.show();

	});

	$('#method').change(function (e) {
		e.preventDefault();

		let value = $(this).val();

		console.log(value);

		$('.method').addClass('d-none');

		$('#'+value).removeClass('d-none');
	});

// Toggle estado administración (AJAX)
document.getElementById('admin-toggle-status') && document.getElementById('admin-toggle-status').addEventListener('click', function() {
	var btn = this;
	var adminId = {{ $administration->id }};
	btn.disabled = true;
	fetch('{{ route("administrations.toggle-status", $administration->id) }}', {
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
			var input = document.getElementById('admin-status-input');
			var badge = document.getElementById('admin-status-badge');
			input.value = data.status_text;
			badge.textContent = data.status_text;
			badge.className = 'badge badge-lg bg-' + data.status_class + ' mt-2';
		} else {
			alert('Error al cambiar el estado');
		}
	})
	.catch(function() { alert('Error al cambiar el estado'); })
	.finally(function() { btn.disabled = false; });
});

</script>

@endsection
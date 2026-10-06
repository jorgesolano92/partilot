@extends('layouts.layout')

@section('title','Participaciones')

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
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Participaciones</a></li>
                        <li class="breadcrumb-item active">Participación</li>
                    </ol>
                </div>
                <h4 class="page-title">Participaciones</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

            		<h4 class="header-title">

                    	Datos Participación

                    </h4>

                    <br>

                    <div class="row">
                    	
                    	<div class="col-md-3" style="position: relative;">

                    		@php
                    		    $participationSeller = $participation->seller
                    		        ?? optional($pendingDigitalSaleForLinkCode)->seller;
                    		@endphp

                    		<ul class="form-card bs mb-3 nav">

                    			<li class="nav-item">
	                    			<div class="form-wizard-element active" data-bs-toggle="tab" data-bs-target="#datos_legales">
	                    				
	                    				<span>
	                    					&nbsp;&nbsp;
	                    				</span>

	                    				<img src="{{url('icons_/entidades.svg')}}" alt="">

	                    				<label>
	                    					Datos participación
	                    				</label>

	                    			</div>
                    			</li>

                        @if($participationSeller)
	                    		<li class="nav-item">
	                    			<div class="form-wizard-element" data-bs-toggle="tab" data-bs-target="#datos_contacto">
	                    				
	                    				<span>
	                    					&nbsp;&nbsp;
	                    				</span>

	                    				<img src="{{url('icons_/sorteos.svg')}}" alt="">

	                    				<label>
	                    					Vendedor
	                    				</label>

	                    			</div>

	                    		</li>
                        @endif
                    			
                    		</ul>

                    		<div class="form-card show-content bs mb-3">
                    			<h4 class="mb-0 mt-1">
                    				Estado Participación
                    			</h4>
                    			<small><i>Cambia estado Participación</i></small>

                    			<div class="form-group mt-2">
	                    			<label class="">Estado Actual</label> 
	                    			@if($participation->status == 'vendida')
	                    				<label class="badge badge-lg bg-success float-end">Vendida</label>
	                    			@elseif($participation->status == 'pagada')
	                    				<label class="badge badge-lg bg-info float-end">Pagada</label>
	                    			@elseif($participation->status == 'disponible' && $participation->seller_id)
	                    				<label class="badge badge-lg bg-primary float-end">Asignada</label>
	                    			@elseif($participation->status == 'disponible' || $participation->status == 'devuelta')
	                    				<label class="badge badge-lg {{ $participation->status == 'devuelta' ? 'bg-danger' : 'bg-info' }} float-end">{{ $participation->status_text }}</label>
	                    			@else
	                    				<label class="badge badge-lg bg-warning float-end">{{ $participation->status_text }}</label>
	                    			@endif
	                    			<div style="clear: both;"></div>
                    			</div>

                    			@if(!empty($pendingDigitalSaleForLinkCode) && $pendingDigitalSaleForLinkCode->link_code)
                    			<div class="form-group mt-3 mb-0 p-3 rounded" style="background:#fff8f0;border:1px solid #ffeeba;">
                    				<label class="mb-1 d-block fw-semibold">Código de vinculación</label>
                    				<p class="mb-1" style="letter-spacing:3px;font-size:1.15rem;font-weight:700;color:#212529;">
                    					{{ $pendingDigitalSaleForLinkCode->link_code }}
                    				</p>
                    				<small class="text-muted d-block">
                    					Venta digital pendiente de registro en la app
                    					@if($pendingDigitalSaleForLinkCode->valid_until)
                    						· válido hasta {{ $pendingDigitalSaleForLinkCode->valid_until->format('d/m/Y') }}
                    					@endif
                    				</small>
                    			</div>
                    			@endif
                    		</div>

                    		@if($participation->walletOwner || ($participation->buyer_name && !$participation->buyerNameIsWalletUserId()))
                    		<div class="form-card show-content bs mb-3">
                    			<h4 class="mb-0 mt-1">Titular / cartera</h4>
                    			<!-- @if($participation->buyerNameIsWalletUserId())
                    			<small><i>Usuario app (buyer_name = {{ $participation->buyer_name }})</i></small>
                    			@endif -->
                    			<div class="form-group mt-2 mb-0">
                    				@if($participation->walletOwner)
                    				<p class="mb-1"><strong>{{ trim($participation->walletOwner->name.' '.($participation->walletOwner->last_name ?? '')) }}</strong></p>
                    				<p class="mb-0 text-muted small">{{ $participation->walletOwner->email }}</p>
                    				@else
                    				<p class="mb-1"><strong>{{ $participation->buyer_name }}</strong></p>
                    				@if($participation->buyer_email)
                    				<p class="mb-0 text-muted small">{{ $participation->buyer_email }}</p>
                    				@endif
                    				@endif
                    			</div>
                    		</div>
                    		@endif

                    		<div class="form-card">
                    			
                    			@php $partEntity = $participation->set && $participation->set->reserve ? $participation->set->reserve->entity : null; @endphp
                    			<div class="row">
                					<div class="col-4">
                						
	                    				<div class="photo-preview-3 logo-round" @if($partEntity && $partEntity->image) style="background-image: url('{{ asset('uploads/' . $partEntity->image) }}');" @endif>
	                    					@if(!$partEntity || !$partEntity->image)
	                    						<i class="ri-account-circle-fill"></i>
	                    					@endif
	                    				</div>
	                    				
	                    				<div style="clear: both;"></div>
                					</div>

                					<div class="col-8 text-center mt-2">

                						<h3 class="mt-2 mb-0">{{ $partEntity ? ($partEntity->name ?? 'Sin entidad') : 'Sin entidad' }}</h3>

                						<i style="position: relative; top: 3px; font-size: 16px; color: #333" class="ri-computer-line"></i> {{ $partEntity ? ($partEntity->province ?? 'Sin provincia') : 'Sin provincia' }}
                						
                					</div>
                				</div>

                    		</div>

                    		<a href="{{url('participations/add')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9">

                    		<div class="tabbable show-content">
                    			
                    			<div class="tab-content p-0">
                    				
                    				<div class="tab-pane fade active show" id="datos_legales">
                    					<div class="form-card bs" style="min-height: 658px;">
			                    			<h4 class="mb-0 mt-1">
			                    				Participación

			                    				{{-- <a href="{{url('administrations/edit/1')}}" class="btn btn-light float-end" style="border: 1px solid silver; border-radius: 30px;"> 
			                    				<img src="{{url('assets/form-groups/edit.svg')}}" alt="">
			                    				Editar</a> --}}
			                    			</h4>
			                    			<small><i>Comprueba que el sorteo es el correcto</i></small>
			                    			
			                    			<br>
											<br>

			                    			<div>
			                    				<div class="row">

			                    					<div class="col-2">

			                    						<img class="mt-2" src="{{asset('storage/design_snapshots/design_set_'.$participation->set->id.'.png')}}" alt="" width="100%" style="max-width: 150px; border-radius: 12px;">
			                    						
			                    					</div>
			                    					
			                    					<div class="col-2">
			                    						<div class="form-group mt-3 mb-3">
			                    							<label class="label-control">Número de Sorteo</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/16.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $participation->set && $participation->set->reserve && $participation->set->reserve->lottery ? $participation->set->reserve->lottery->name : 'N/A' }}" class="form-control" type="text" placeholder="46/25" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-2">
			                    						<div class="form-group mt-3 mb-3">
			                    							<label class="label-control">Fecha Sorteo</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $participation->set && $participation->set->reserve && $participation->set->reserve->lottery && $participation->set->reserve->lottery->draw_date ? \Carbon\Carbon::parse($participation->set->reserve->lottery->draw_date)->format('Y-m-d') : 'N/A' }}" class="form-control" type="date" placeholder="01/01/2025" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-6">
			                    						<div class="form-group mt-3 mb-3">
			                    							<label class="label-control">Número/s Jugado/s</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/14.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $participation->set && $participation->set->reserve && $participation->set->reserve->reservation_numbers ? implode(' - ', $participation->set->reserve->reservation_numbers) : 'N/A' }}" class="form-control" type="text" placeholder="0000" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-2">
			                    						<div class="form-group mt-3 mb-3">
			                    							<label class="label-control">Importe Jugado (por Número)</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/15.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $participation->set ? number_format($participation->set->played_amount, 2, ',', '.') . '€' : 'N/A' }}" class="form-control" type="text" placeholder="6,00€" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-2">
			                    						<div class="form-group mt-3 mb-3">
			                    							<label class="label-control">Importe <br> Donativo</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/15.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $participation->set ? number_format($participation->set->donation_amount, 2) . '€' : 'N/A' }}" class="form-control" type="text" placeholder="6,00€" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-2">
			                    						<div class="form-group mt-3 mb-3">
			                    							<label class="label-control">Importe Total Participación</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/15.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $participation->set ? number_format($participation->set->pricePerParticipation(), 2, ',', '.') . '€' : 'N/A' }}" class="form-control" type="text" placeholder="6,00€" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-2">
			                    						<div class="form-group mt-3 mb-3">
			                    							<label class="label-control">Número <br> Participación</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/22.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $participation->display_participation_code ?? $participation->participation_code ?? 'N/A' }}" class="form-control" type="text" placeholder="1/0001" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-4">
			                    						<div class="form-group mt-3 mb-3">
			                    							<label class="label-control">Número Seguridad <br> Participación</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/21.svg')}}" alt="">
							                                    </div>

							                                    <input readonly="" value="{{ $ticketReference ?? 'N/A' }}" class="form-control" type="text" placeholder="12345678901234567890" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>
			                    				</div>
			                    			</div>

		                    			<div class="d-flex justify-content-between align-items-center mb-2">
		                    				<div>
		                    					<h4 class="mb-0 mt-1">
		                    						<i class="mdi mdi-timeline-clock"></i> Historial Participación
		                    					</h4>
		                    					<small><i>Registro completo de actividades</i></small>
		                    				</div>
		                    				<button class="btn btn-sm btn-light" onclick="refreshActivityHistory()" title="Actualizar historial">
		                    					<i class="mdi mdi-refresh"></i> Actualizar
		                    				</button>
		                    			</div>

		                    			<!-- Loading state -->
		                    			<div id="activity-loading" class="text-center py-4">
		                    				<div class="spinner-border text-primary" role="status">
		                    					<span class="visually-hidden">Cargando...</span>
		                    				</div>
		                    				<p class="text-muted mt-2 mb-0"><small>Cargando historial de actividades...</small></p>
		                    			</div>

		                    			<!-- No activities state -->
		                    			<div id="no-activities" class="text-center py-4" style="display: none;">
		                    				<i class="mdi mdi-information-outline" style="font-size: 48px; color: #ccc;"></i>
		                    				<p class="text-muted mt-2"><small>No hay actividades registradas</small></p>
		                    			</div>

		                    			<!-- Error state -->
		                    			<div id="activity-error" class="text-center py-4" style="display: none;">
		                    				<i class="mdi mdi-alert-circle-outline" style="font-size: 48px; color: #dc3545;"></i>
		                    				<p class="text-danger mt-2"><small>Error al cargar el historial</small></p>
		                    			</div>

		                    			<!-- Activities table -->
		                    			<div id="activity-timeline" style="display: none;">
		                    				<table class="table table-striped table-condensed table-hover nowrap w-100 mb-0">
		                    					<thead>
		                    						<tr>
		                    							<th style="width: 100px;">Fecha</th>
		                    							<th style="width: 80px;">Hora</th>
		                    							<th style="width: 130px;">Tipo</th>
		                    							<th>Concepto</th>
		                    							<th style="width: 120px;">Usuario</th>
		                    							<th style="width: 120px;">Vendedor</th>
		                    						</tr>
		                    					</thead>
		                    					<tbody id="activity-tbody">
		                    						<!-- Las actividades se cargarán aquí -->
		                    					</tbody>
		                    				</table>
		                    			</div>

			                    		</div>
                    				</div>

                        @if($participationSeller)
                    				<div class="tab-pane fade" id="datos_contacto">
                    					<div class="form-card bs" style="min-height: 658px;">
			                    			<h4 class="mb-0 mt-1">
			                    				Datos de contacto
			                    				{{-- Sin botón Editar vendedor cuando la participación está asignada: se edita desde el perfil del vendedor si hace falta --}}
			                    			</h4>
			                    			<small><i>Datos del vendedor asignado (solo lectura)</i></small>
			                    			<div style="clear: both;"></div>

			                    			
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

                                            <input readonly="" value="{{ $participationSeller->name }}" class="form-control" type="text" placeholder="Nombre completo" style="border-radius: 0 30px 30px 0;">
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

                                            <input readonly="" value="{{ optional($participationSeller)->user ? optional($participationSeller->user)->last_name : (optional($participationSeller)->last_name ?? '-') }}" class="form-control" type="text" placeholder="Primer Apellido" style="border-radius: 0 30px 30px 0;">
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

                                            <input readonly="" value="{{ optional($participationSeller)->user ? optional($participation->seller->user)->last_name2 : (optional($participationSeller)->last_name2 ?? '-') }}" class="form-control" type="text" placeholder="Segundo Apellido" style="border-radius: 0 30px 30px 0;">
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

                                        <input readonly="" value="{{ optional($participationSeller)->user ? optional($participation->seller->user)->nif_cif : (optional($participationSeller)->nif_cif ?? 'N/A') }}" class="form-control" type="text" placeholder="B26262626" style="border-radius: 0 30px 30px 0;">
							                                </div>
						                    			</div>
			                    					</div>

			                    					<div class="col-3">
			                    						<div class="form-group mt-2 mb-3">
			                    							<label class="label-control">F. Nacimiento</label>

							                    			<div class="input-group input-group-merge group-form">

							                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
							                                        <img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
							                                    </div>

                                        <input readonly="" value="{{ optional(optional($participationSeller)->user)->birthday ? \Carbon\Carbon::parse(optional(optional($participationSeller)->user)->birthday)->format('Y-m-d') : '' }}" class="form-control" type="date" placeholder="01/01/1990" style="border-radius: 0 30px 30px 0;">
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

                                        <input readonly="" value="{{ optional($participationSeller)->email ?? optional(optional($participationSeller)->user)->email ?? 'N/A' }}" class="form-control" type="email" placeholder="ejemplo@cuentaemail.com" style="border-radius: 0 30px 30px 0;">
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

                                        <input readonly="" value="{{ optional($participationSeller)->phone ?? optional(optional($participationSeller)->user)->phone ?? 'N/A' }}" class="form-control" type="phone" placeholder="940 200 200" style="border-radius: 0 30px 30px 0;">
							                                </div>
                        @endif
						                    			</div>
			                    					</div>

			                    				</div>
			                    				
			                    			</div>

			                    			<h4 class="mb-0 mt-1">
			                    				Comentarios
			                    			</h4>
			                    			<small><i>Puedes editar el comentario de la participación directamente aquí.</i></small>

			                    			<div class="row">
			                    				
			                    				<div class="col-8">
			                    					
			                    					<div class="form-group mt-2">
						                    			<label class="label-control">Comentario</label>

						                    			<div class="input-group input-group-merge group-form" style="border: none">

						                                    <textarea class="form-control" placeholder="Añade tu comentario" name="notes" id="participation-notes" rows="6">{{ old('notes', $participation->notes ?? '') }}</textarea>
						                                </div>
						                                <button type="button" class="btn btn-primary mt-2" id="btn-guardar-comentario" style="border-radius: 30px;">
						                                    <i class="ri-save-line"></i> Guardar comentario
						                                </button>
					                    			</div>

			                    				</div>

			                    				<div class="col-4 text-end">
			                    					
			                    				</div>

			                    			</div>

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

	// ==================== HISTORIAL DE ACTIVIDADES ====================
	const participationId = {{ $participation->id }};

	// Guardar comentario de la participación
	$('#btn-guardar-comentario').on('click', function() {
		const notes = $('#participation-notes').val();
		const $btn = $(this);
		$btn.prop('disabled', true).html('<i class="ri-loader-4-line spin"></i> Guardando...');
		$.ajax({
			url: `{{ url('participations/view') }}/${participationId}/notes`,
			type: 'POST',
			data: {
				notes: notes,
				_token: '{{ csrf_token() }}',
				_method: 'PATCH'
			},
			success: function() {
				$btn.prop('disabled', false).html('<i class="ri-save-line"></i> Guardar comentario');
				alert('Comentario guardado correctamente');
			},
			error: function() {
				$btn.prop('disabled', false).html('<i class="ri-save-line"></i> Guardar comentario');
				alert('Error al guardar el comentario');
			}
		});
	});

	// Cargar el historial al cargar la página
	$(document).ready(function() {
		loadActivityHistory();
	});

	function loadActivityHistory() {
		// Mostrar loading
		$('#activity-loading').show();
		$('#activity-timeline').hide();
		$('#no-activities').hide();
		$('#activity-error').hide();

		$.ajax({
			url: `{{url('/')}}/participations/${participationId}/history`,
			type: 'GET',
			success: function(data) {
				$('#activity-loading').hide();

				if (data.success && data.activities && data.activities.length > 0) {
					renderActivities(data.activities);
					$('#activity-timeline').show();
				} else {
					$('#no-activities').show();
				}
			},
			error: function(xhr, status, error) {
				console.error('Error al cargar historial:', error);
				$('#activity-loading').hide();
				$('#activity-error').show();
			}
		});
	}

	function createActivityRow(activity) {
		// Separar fecha y hora
		const dateTimeParts = activity.created_at.split(' ');
		const datePart = dateTimeParts[0] || '';
		const timePart = dateTimeParts[1] || '';

		// Crear la fila
		let row = `
			<tr style="cursor: pointer;" onclick="showActivityDetails(${activity.id})" title="Click para ver detalles">
				<td><small>${datePart}</small></td>
				<td><small>${timePart}</small></td>
				<td><span class="badge ${activity.activity_badge}">${activity.activity_type_text}</span></td>
				<td>
					<div>${activity.description}</div>
		`;

		// Agregar información de cambios de estado si existe
		if (activity.old_status && activity.new_status) {
			row += `
				<small class="text-muted">
					<span class="badge bg-secondary">${activity.old_status}</span> 
					<i class="mdi mdi-arrow-right"></i> 
					<span class="badge bg-primary">${activity.new_status}</span>
				</small>
			`;
		}

		// Agregar información de cambio de vendedor si existe
		if (activity.old_seller && activity.new_seller) {
			row += `
				<small class="text-muted d-block mt-1">
					<i class="mdi mdi-account-switch"></i> ${activity.old_seller} → ${activity.new_seller}
				</small>
			`;
		}

		row += `
				</td>
				<td><small>${activity.user || 'Sistema'}</small></td>
				<td>
		`;

		// Columna de vendedor
		if (activity.seller || activity.new_seller || activity.old_seller) {
			const sellerName = activity.seller || activity.new_seller || activity.old_seller;
			row += `<small>${sellerName}</small>`;
		} else {
			row += `<small class="text-muted">-</small>`;
		}

		row += `
				</td>
			</tr>
		`;

		return row;
	}

	// Guardar actividades en una variable global para acceso en los detalles
	window.activitiesData = {};

	function renderActivities(activities) {
		const tbody = $('#activity-tbody');
		tbody.empty();

		// Guardar actividades para acceso posterior
		activities.forEach(function(activity) {
			window.activitiesData[activity.id] = activity;
			const row = createActivityRow(activity);
			tbody.append(row);
		});
	}

	function showActivityDetails(activityId) {
		const activity = window.activitiesData[activityId];
		if (!activity) return;

		let detailsHtml = `
			<div class="mb-3">
				<h6><span class="badge ${activity.activity_badge}">${activity.activity_type_text}</span></h6>
				<p class="mb-2">${activity.description}</p>
			</div>
		`;

		// Agregar metadata si existe
		if (activity.metadata && Object.keys(activity.metadata).length > 0) {
			detailsHtml += '<div class="mb-2"><strong>Información adicional:</strong></div>';
			detailsHtml += '<div class="table-responsive"><table class="table table-sm table-bordered">';
			
			for (const [key, value] of Object.entries(activity.metadata)) {
				if (value !== null && value !== undefined && value !== '') {
					const formattedKey = formatMetadataKey(key);
					const formattedValue = formatMetadataValue(value);
					detailsHtml += `
						<tr>
							<td style="width: 40%;"><strong>${formattedKey}</strong></td>
							<td>${formattedValue}</td>
						</tr>
					`;
				}
			}
			
			detailsHtml += '</table></div>';
		}

		// Agregar IP si existe
		if (activity.ip_address) {
			detailsHtml += `<div class="mt-2"><small class="text-muted"><i class="mdi mdi-ip"></i> IP: <code>${activity.ip_address}</code></small></div>`;
		}

		// Mostrar en modal con SweetAlert2 o alert nativo
		if (typeof Swal !== 'undefined') {
			Swal.fire({
				title: 'Detalles de la Actividad',
				html: detailsHtml,
				width: 600,
				confirmButtonText: 'Cerrar',
				confirmButtonColor: '#333'
			});
		} else {
			alert('Detalles:\n\n' + activity.description + '\n\nUsuario: ' + (activity.user || 'Sistema'));
		}
	}

	function formatMetadataKey(key) {
		const translations = {
			'participation_code': 'Código Participación',
			'book_number': 'Número de Taco',
			'set_id': 'ID Set',
			'design_format_id': 'ID Formato',
			'cancellation_reason': 'Razón de Anulación',
			'return_reason': 'Razón de Devolución',
			'sale_amount': 'Importe de Venta',
			'buyer_name': 'Nombre Comprador',
			'buyer_phone': 'Teléfono Comprador',
			'buyer_email': 'Email Comprador',
			'buyer_nif': 'NIF Comprador',
			'owner_user_id': 'ID Usuario titular',
			'owner_user_name': 'Titular (usuario)',
			'owner_user_email': 'Email titular',
			'status': 'Estado',
			'seller_id': 'ID Vendedor'
		};
		return translations[key] || key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
	}

	function formatMetadataValue(value) {
		if (typeof value === 'object' && value !== null) {
			return '<pre style="font-size: 0.85em; margin: 0;">' + JSON.stringify(value, null, 2) + '</pre>';
		}
		return value;
	}

	function refreshActivityHistory() {
		loadActivityHistory();
	}

</script>

@endsection
@extends('layouts.layout')

@section('title','Entidades')

@section('content')

<style>
	
	.form-wizard-element, .form-wizard-element label {
		cursor: pointer;
	}
	.form-check-input:checked {
		border-color: #333;
	}
	/* Solo en ficha entidad/gestor: card-body en columna para alinear Atrás/Guardar */
	.card > .card-body:has(> .entity-detail-form),
	.card > .card-body:has(> .entity-detail-row) {
		display: flex !important;
		flex-direction: column !important;
	}
	.entity-detail-form {
		display: flex;
		flex-direction: column;
		flex: 1 1 auto;
		min-height: 0;
		width: 100%;
	}
	.entity-detail-row {
		align-items: stretch;
		flex: 1 1 auto;
		min-height: 658px;
		width: 100%;
	}
	.entity-detail-sidebar {
		display: flex !important;
		flex-direction: column;
		min-height: 100%;
	}
	.entity-detail-sidebar .entity-detail-back {
		margin-top: auto !important;
		margin-bottom: 0;
		align-self: flex-start;
		position: relative !important;
	}
	.entity-detail-main {
		display: flex;
		flex-direction: column;
		min-height: 100%;
	}
	.entity-detail-main > .form-card {
		flex: 1 1 auto;
		display: flex;
		flex-direction: column;
		min-height: 100%;
		height: 100%;
	}
	.entity-detail-main > .form-card > .row:last-of-type {
		margin-top: auto;
		margin-bottom: 0;
	}
	.entity-detail-actions {
		display: flex;
		justify-content: flex-end;
		align-items: flex-end;
		padding-top: 1rem;
	}
	.entity-detail-actions .btn {
		margin-bottom: 0;
	}
</style>

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
            	<div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('entities.index') }}">Entidades</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('entities.show', $entity->id) }}">Entidad</a></li>
                        <li class="breadcrumb-item active">Editar Gestor</li>
                    </ol>
                </div>
                <h4 class="page-title">Entidades</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

            		<h4 class="header-title">

                    	Datos Gestor

                    </h4>

                    <br>

                    <form action="{{ route('managers.update', $entity->manager->id) }}" method="POST" enctype="multipart/form-data" class="entity-detail-form">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="origin" value="entity">
                        
                        <div class="row entity-detail-row">
                    	
                    	<div class="col-md-3 entity-detail-sidebar">

                    		<div class="form-card bs mb-3">
                    			<div class="form-wizard-element">
                    				
                    				<span>
                    					&nbsp;&nbsp;
                    				</span>

                    				<img src="{{url('assets/entidad.svg')}}" alt="">

                    				<label>
                    					Datos Entidad
                    				</label>

                    			</div>

                    			<div class="form-wizard-element active">
                    				
                    				<span>
                    					&nbsp;&nbsp;
                    				</span>

                    				<img src="{{url('assets/gestor.svg')}}" alt="">

                    				<label>
                    					Datos Gestor
                    				</label>

                    			</div>
                    		</div>

                    		<div class="form-card bs mb-3">
                    			<h4 class="mb-0 mt-1">
                    				Página web
                    			</h4>
                    			<small><i>Este campo no es obligatorio</i></small>

                    			<div class="form-group mt-2">
	                    			<label class="label-control">Web</label>

	                    			<div class="input-group input-group-merge group-form">

	                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
	                                        <img src="{{url('assets/form-groups/admin/0.svg')}}" alt="">
	                                    </div>

	                                    <input class="form-control" value="{{$entity->administration->web}}" readonly="" type="text" placeholder="www.administracion.es" style="border-radius: 0 30px 30px 0;">
	                                </div>
                    			</div>
                    		</div>

                    		<div class="form-card show-content bs">
                    			<h4 class="mb-0 mt-1">
                    				Estado
                    			</h4>
                    			<small><i>Entidad y gestor</i></small>

                    			@php
                    				$entityStatusValue = $entity->status;
                    				if ($entityStatusValue === null || $entityStatusValue === -1) {
                    					$entityStatusText = 'Pendiente';
                    					$entityStatusClass = 'bg-secondary';
                    				} elseif ((int) $entityStatusValue === 1) {
                    					$entityStatusText = 'Activa';
                    					$entityStatusClass = 'bg-success';
                    				} else {
                    					$entityStatusText = 'Inactiva';
                    					$entityStatusClass = 'bg-danger';
                    				}
                    				$entityContractSigned = $entity->hasSignedFrameworkContract();
                    				$editManager = $entity->manager;
                    				$managerPending = $editManager ? $editManager->isPendingActivation() : false;
                    				$managerRoleLegalOk = $editManager ? $editManager->hasAcceptedRoleLegal() : false;
                    			@endphp

                    			<div class="form-group mt-2 mb-2">
	                    			<label class="">Entidad</label>
	                    			<label class="badge badge-lg {{ $entityStatusClass }} float-end">{{ $entityStatusText }}</label>
	                    			<div style="clear: both;"></div>
	                    			@if(! $entityContractSigned)
	                    				<p class="small text-warning mb-0 mt-1">
	                    					<i class="ri-error-warning-line"></i> Contrato marco pendiente de firma.
	                    				</p>
	                    			@endif
                    			</div>

                    			@if($editManager)
                    			<div class="form-group mb-0">
	                    			<label class="">Gestor</label>
	                    			<label class="badge badge-lg {{ $editManager->statusBadgeClass() }} float-end">{{ $editManager->statusLabel() }}</label>
	                    			<div style="clear: both;"></div>
	                    			@if($managerPending)
	                    				<p class="small text-warning mb-0 mt-1">
	                    					<i class="ri-time-line"></i> Pendiente de aceptar la invitación o el cargo.
	                    				</p>
	                    			@endif
	                    			@if(! $managerRoleLegalOk)
	                    				<p class="small text-warning mb-0 mt-1">
	                    					<i class="ri-file-warning-line"></i> Marco legal del rol no firmado.
	                    				</p>
	                    			@elseif(! $managerPending)
	                    				<p class="small text-success mb-0 mt-1">
	                    					<i class="ri-checkbox-circle-line"></i> Marco legal del rol aceptado.
	                    				</p>
	                    			@endif
                    			</div>
                    			@endif
                    		</div>

                    		<a href="{{ route('entities.show', $entity->id) }}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder;" class="btn btn-md btn-light entity-detail-back">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9 entity-detail-main">

        					<div class="form-card bs" style="min-height: 658px;">
                    			<h4 class="mb-0 mt-1">
                    				Datos de gestor
                    			</h4>
                    			<small><i>Todos los campos son obligatorios</i></small>
                    			<div style="clear: both;"></div>

                    			<div class="form-group mt-2 mb-3 admin-box">

                    				<div class="row">
                    					<div class="col-1">
                    						
		                    				<div class="photo-preview-2 logo-round" @if($entity->image) style="background-image: url('{{ asset('uploads/' . $entity->image) }}');" @endif>
		                    					@if(!$entity->image)
		                    						<i class="ri-account-circle-fill"></i>
		                    					@endif
		                    				</div>
		                    				
		                    				<div style="clear: both;"></div>
                    					</div>

                    					<div class="col-4 text-center">

                    						<h4 class="mt-0 mb-0">{{ $entity->name ?? 'Sin nombre' }}</h4>
                    						<small>{{ $entity->manager->user->name ?? '' }} {{ $entity->manager->user->last_name ?? '' }}</small> <br>
                    						<i style="position: relative; top: 3px; font-size: 16px; color: #333" class="ri-computer-line"></i> {{ $entity->administration->receiving ?? '' }}
                    						
                    					</div>

                    					<div class="col-4">

                    						<div class="mt-2">
                    							Provincia: {{ $entity->province ?? '' }} <br>
                    							Dirección: {{ $entity->address ?? '' }}
                    						</div>
                    						
                    					</div>

                    					<div class="col-3">

                    						<div class="mt-2">
                    							Ciudad: {{ $entity->city ?? '' }} <br>
                    							Tel: {{ $entity->phone ?? '' }}
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

				                                    <input name="name" value="{{ old('name', $entity->manager->user->name ?? '') }}" class="form-control" type="text" placeholder="Nombre" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input name="last_name" value="{{ old('last_name', $entity->manager->user->last_name ?? '') }}" class="form-control" type="text" placeholder="Primer Apellido" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input name="last_name2" value="{{ old('last_name2', $entity->manager->user->last_name2 ?? '') }}" class="form-control" type="text" placeholder="Segundo Apellido" style="border-radius: 0 30px 30px 0;">
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

				                                    <input name="nif_cif" id="entity-edit-manager-nif-cif" value="{{ old('nif_cif', $entity->manager->user->nif_cif ?? '') }}" class="form-control" type="text" placeholder="B26262626" style="border-radius: 0 30px 30px 0;">
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

				                                    <input name="birthday" value="{{ old('birthday', $entity->manager->user->birthday?->format('Y-m-d') ?? '') }}" class="form-control" type="date" min="1900-01-01" max="{{ now()->toDateString() }}" placeholder="01/01/1990" style="border-radius: 0 30px 30px 0;">
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

				                                    <input name="email" value="{{ old('email', $entity->manager->user->email ?? '') }}" class="form-control" type="email" placeholder="ejemplo@cuentaemail.com" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input name="phone" value="{{ old('phone', $entity->manager->user->phone ?? '') }}" class="form-control" type="phone" placeholder="940 200 200" style="border-radius: 0 30px 30px 0;">
				                                </div>
			                    			</div>
                    					</div>



                    				</div>
                    				
                    			</div>

                    			<h4 class="mb-0 mt-1">
                    				Comentarios
                    			</h4>
                    			<small><i>Puedes añadir un comentario si necesitas añadir información adicional <br> sobre el gestor. Puedes añadir comentarios mas tarde.</i></small>

                    			<div class="row">
                    				
                    				<div class="col-8">
                    					
                    					<div class="form-group mt-2">
			                    			<label class="label-control">Comentario</label>

			                    			<div class="input-group input-group-merge group-form" style="border: none">

			                                    <textarea name="comment" class="form-control" placeholder="Añade tu comentario" rows="6">{{ old('comment', $entity->manager->user->comment ?? '') }}</textarea>
			                                </div>
		                    			</div>

                    				</div>

                    				<div class="col-4 text-end entity-detail-actions">
                    					<button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light">Guardar
                    						<i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i></button>
                    				</div>

                    			</div>

                    		</div>

                    	</div>
                    	
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
// Inicializar validación de documento español
document.addEventListener('DOMContentLoaded', function() {
    initSpanishDocumentValidation('entity-edit-manager-nif-cif', {
        showMessage: true
    });
});
</script>

<script>
	$('footer.footer').css('margin-left', '12px');
	$('footer.footer').css('margin-right', '12px');
</script>
@endsection
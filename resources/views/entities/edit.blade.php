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
</style>

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
            	<div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Entidades</a></li>
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Entidad</a></li>
                        <li class="breadcrumb-item active">Editar</li>
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

                    	Datos Entidad

                    </h4>

                    <br>

                    <form action="{{ route('entities.update', $entity->id) }}" method="POST" enctype="multipart/form-data" id="entity-edit-form">
                        @csrf
                        @method('PUT')
                        <div class="row">
                        <div class="col-md-3" style="position: relative;">

                    		<div class="form-card bs mb-3">
                    			<div class="form-wizard-element active">
                    				
                    				<span>
                    					&nbsp;&nbsp;
                    				</span>

                    				<img src="{{url('assets/entidad.svg')}}" alt="">

                    				<label>
                    					Datos Entidad
                    				</label>

                    			</div>

                    			<div class="form-wizard-element" data-bs-toggle="tab" data-bs-target="#datos_contacto">
                    				
                    				<span>
                    					&nbsp;&nbsp;
                    				</span>

                    				<img src="{{url('assets/gestor.svg')}}" alt="">

                    				<label>
                    					Datos Gestor
                    				</label>

                    			</div>
                    			
                    		</div>

                    		<div class="form-card show-content mb-3 bs">
                    			<h4 class="mb-0 mt-1">
                    				Estado Entidad
                    			</h4>
                    			<small><i>Bloquea o desbloquea la entidad</i></small>

                    			<div class="form-group mt-2">
	                    			<label class="">Estado Actual</label> 
	                    			@php
	                    				$statusValue = $entity->status;
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
	                    			<label class="badge badge-lg {{ $statusClass }} float-end">
	                    				{{ $statusText }}
	                    			</label>
	                    			<div style="clear: both;"></div>
	                    			
	                    			<div class="form-group mt-3">
	                    				<label class="form-label">Cambiar Estado</label>
	                    				<select name="status" id="entity_status" class="form-select">
	                    					<option value="-1" {{ ($statusValue === null || $statusValue === -1) ? 'selected' : '' }}>Pendiente</option>
	                    					<option value="1" {{ $statusValue == 1 ? 'selected' : '' }}>Activo</option>
	                    					<option value="0" {{ $statusValue == 0 ? 'selected' : '' }}>Inactivo</option>
	                    				</select>
	                    			</div>
                    			</div>
                    		</div>

                    		@include('entities.partials.billing_switches_card', ['entity' => $entity, 'readonly' => false])

                    		<a href="{{ route('entities.show', $entity->id) }}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9">
                    				
            				<div class="tab-pane fade active show" id="datos_legales">

	                    		<div class="form-card bs" style="min-height: 658px;">
	                    			<h4 class="mb-0 mt-1">
	                    				Datos legales de la entidad
	                    			</h4>
	                    			<small><i>Todos los campos son obligatorios</i></small>

	                    			<div class="form-group mt-2 mb-3">

	                    				<div class="row align-items-center">
	                    					<div class="col-auto">
			                    				<div class="photo-preview-3 logo-round entity-image-preview" @if($entity->image) style="background-image: url('{{ asset('uploads/' . $entity->image) }}');" @endif>
			                    					@if(!$entity->image)
			                    						<i class="ri-image-add-line"></i>
			                    					@endif
			                    				</div>
	                    					</div>
	                    					<div class="col-auto">
	                    						<small><i>Imagen entidad</i></small><br>
	                    						<b>Logotipo</b><br>
	                    						<label style="border-radius: 30px; width: 150px; background-color: #333;" class="btn btn-md btn-dark mt-2">
	                    							<small>Subir imagen</small>
	                    							<input type="file" id="entity-imagen-input" name="image" style="display: none;" accept="image/*">
	                    						</label>
	                    						<button type="button" id="entity-btn-eliminar-imagen" style="border-radius: 30px; width: 150px; background-color: transparent; color: #333;" class="btn btn-md btn-dark mt-2">
	                    							<small>Eliminar imagen</small>
	                    						</button>
	                    						<input type="hidden" name="remove_image" id="entity-remove-image" value="0">
	                    					</div>
	                    					<div class="col-auto mt-3 mt-md-0 text-center">
	                    						<h4 class="mt-0 mb-0">{{ $entity->name ?? '' }}</h4>
	                    						<small>{{ $entity->province ?? '' }}</small>
	                    					</div>
	                    				</div>
	                    			</div>

	                    			<br>

	                    			<div>
	                    				<div class="row">
	                    					
	                    					<div class="col-6">
	                    						<div class="form-group mt-2 mb-3">
	                    							<label class="label-control">Nombre comercial</label>

					                    			<div class="input-group input-group-merge group-form">

					                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
					                                        <img src="{{url('assets/form-groups/admin/1.svg')}}" alt="">
					                                    </div>

					                                    <input class="form-control" name="name" value="{{ old('name', $entity->name) }}" type="text" placeholder="Nombre Entidad" style="border-radius: 0 30px 30px 0;">
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

					                                    <select class="form-control" name="province" id="entity-edit-province-select" style="border-radius: 0 30px 30px 0;">
                                                            <option value="">Seleccionar provincia</option>
                                                            @foreach(($provinces ?? []) as $province)
                                                                <option value="{{ $province }}" {{ old('province', $entity->province) === $province ? 'selected' : '' }}>{{ $province }}</option>
                                                            @endforeach
                                                        </select>
					                                </div>
				                    			</div>
	                    					</div>

	                    					<div class="col-3">
	                    						<div class="form-group mt-2 mb-3">
	                    							<label class="label-control">Localidad</label>

					                    			<div class="input-group input-group-merge group-form">

					                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
					                                        <img src="{{url('assets/form-groups/admin/6.svg')}}" alt="">
					                                    </div>

					                                    <select class="form-control" name="city" id="entity-edit-city-select" style="border-radius: 0 30px 30px 0;">
                                                            <option value="">Seleccionar localidad</option>
                                                        </select>
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

					                                    <input class="form-control" name="postal_code" value="{{ old('postal_code', $entity->postal_code) }}" type="text" placeholder="Código Postal" style="border-radius: 0 30px 30px 0;">
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

					                                    <input class="form-control" name="address" value="{{ old('address', $entity->address) }}" type="text" placeholder="Dirección" style="border-radius: 0 30px 30px 0;">
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

					                                    <input class="form-control" name="nif_cif" id="entity-edit-nif-cif" value="{{ old('nif_cif', $entity->nif_cif) }}" type="text" placeholder="NIF/CIF" style="border-radius: 0 30px 30px 0;">
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

					                                    <input class="form-control" name="phone" value="{{ old('phone', $entity->phone) }}" type="text" placeholder="Teléfono" style="border-radius: 0 30px 30px 0;">
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

					                                    <input class="form-control" name="email" id="entity-edit-email" value="{{ old('email', $entity->email) }}" type="email" placeholder="Email" style="border-radius: 0 30px 30px 0;">
					                                </div>
				                    			</div>
	                    					</div>

								@php
									$entityHasPanel = \App\Models\User::where('panel_account_type', 'entity')->where('panel_account_id', $entity->id)->exists();
								@endphp
								@if($entityHasPanel)
								<div class="col-12 mt-2">
									<h4 class="mb-0 mt-1">Acceso al panel web</h4>
									<small class="text-muted">El inicio de sesión usa el email de la entidad. Deje la contraseña en blanco para no cambiarla.</small>
								</div>
								<div class="col-md-6 mt-2">
									<div class="form-group mb-3">
										<label class="label-control">Nueva contraseña del panel</label>
										<input class="form-control" type="password" name="panel_password" autocomplete="new-password" style="border-radius: 30px;">
									</div>
								</div>
								<div class="col-md-6 mt-2">
									<div class="form-group mb-3">
										<label class="label-control">Confirmar contraseña</label>
										<input class="form-control" type="password" name="panel_password_confirmation" autocomplete="new-password" style="border-radius: 30px;">
									</div>
								</div>
								@endif

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

				                    			<div class="input-group input-group-merge group-form">

				                                    <textarea class="form-control" placeholder="Añade tu comentario" name="comments" id="" rows="6">{{ old('email', $entity->comments) }}</textarea>
				                                </div>
			                    			</div>

	                    				</div>

	                    				<div class="col-4 text-end">
	                    					<button type="submit" id="entity-edit-submit-btn" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative; top: calc(100% - 51px);" class="btn btn-md btn-light mt-2">
	                    						Guardar
	                    						<i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i>
	                    					</button>
	                    				</div>

	                    			</div>

	                    		</div>

	                    	</div>
                    	</div>

                        </div>

                    </form>

                    
                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div>
    <!-- end row-->

</div> <!-- container -->

@include('entities.partials.billing_switches_confirm_modal')

@endsection

@section('scripts')

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    .group-form .ts-wrapper {
        flex: 1 1 auto;
    }
    .group-form .ts-wrapper.single .ts-control {
        height: calc(1.5em + 0.9rem + 2px) !important;
        min-height: calc(1.5em + 0.9rem + 2px) !important;
        border-radius: 0 30px 30px 0 !important;
        border-left: 0 !important;
    }
    .group-form .ts-wrapper.single .ts-control input::placeholder {
        color: #6c757d !important;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
// Preview y eliminación de imagen de entidad
document.getElementById('entity-imagen-input').addEventListener('change', function(e) {
    const archivo = e.target.files[0];
    const preview = document.querySelector('.entity-image-preview');
    const removeFlag = document.getElementById('entity-remove-image');
    if (removeFlag && archivo) removeFlag.value = '0';
    if (archivo && preview) {
        const lector = new FileReader();
        lector.onload = function(ev) {
            preview.style.backgroundImage = 'url(' + ev.target.result + ')';
            preview.style.backgroundSize = 'cover';
            preview.style.backgroundPosition = 'center';
            const icon = preview.querySelector('i');
            if (icon) icon.style.display = 'none';
        };
        lector.readAsDataURL(archivo);
    }
});
document.getElementById('entity-btn-eliminar-imagen').addEventListener('click', function() {
    const input = document.getElementById('entity-imagen-input');
    const preview = document.querySelector('.entity-image-preview');
    const removeFlag = document.getElementById('entity-remove-image');
    if (input) input.value = '';
    if (removeFlag) removeFlag.value = '1';
    if (preview) {
        preview.style.backgroundImage = 'none';
        const icon = preview.querySelector('i');
        if (icon) {
            icon.style.display = '';
            icon.className = 'ri-image-add-line';
        }
    }
});

// Actualizar el badge de estado cuando se cambie el select
document.addEventListener('DOMContentLoaded', function() {
    const provinceCityMap = @json($provinceCityMap ?? []);
    const provinceSelect = document.getElementById('entity-edit-province-select');
    const citySelect = document.getElementById('entity-edit-city-select');
    const selectedCity = @json(old('city', $entity->city));
    let provinceTs = null;
    let cityTs = null;

    const fillCities = function(province) {
        if (!citySelect) return;
        const cities = (province && provinceCityMap[province]) ? provinceCityMap[province] : [];
        if (cityTs) {
            cityTs.clear(true);
            cityTs.clearOptions();
            cities.forEach(function(city) {
                cityTs.addOption({ value: city, text: city });
            });
            cityTs.refreshOptions(false);
            if (selectedCity && cities.includes(selectedCity)) {
                cityTs.setValue(selectedCity, true);
            } else {
                cityTs.clear(true);
            }
            return;
        }
        citySelect.innerHTML = '<option value="">Seleccionar localidad</option>';
        cities.forEach(function(city) {
            const opt = document.createElement('option');
            opt.value = city;
            opt.textContent = city;
            citySelect.appendChild(opt);
        });
        if (selectedCity && cities.includes(selectedCity)) {
            citySelect.value = selectedCity;
        }
    };

    if (provinceSelect) {
        fillCities(provinceSelect.value);
        if (window.TomSelect) {
            provinceTs = new TomSelect(provinceSelect, {
                create: false,
                allowEmptyOption: true,
                placeholder: 'Seleccionar provincia',
            });
            cityTs = new TomSelect(citySelect, {
                create: false,
                allowEmptyOption: true,
                placeholder: 'Seleccionar localidad',
            });
            provinceTs.on('change', function(value) {
                fillCities(value || '');
            });
            if (!provinceSelect.value) {
                provinceTs.clear(true);
            }
            if (!citySelect.value) {
                cityTs.clear(true);
            }
        } else {
            provinceSelect.addEventListener('change', function() {
                fillCities(this.value);
            });
        }
    }

    // Validación documento entidad: NIF, NIE, TIE o CIF
    initSpanishDocumentValidation('entity-edit-nif-cif', { forEntity: true, showMessage: true });
    // Inicializar validación de email
    initEmailValidation('entity-edit-email', {
        context: 'entity',
        excludeId: {{ $entity->id }},
        showMessage: true
    });
    
    const select = document.getElementById('entity_status');
    
    if (select) {
        // Buscar el badge específico en el contexto del formulario
        const formCard = select.closest('.form-card');
        const badge = formCard ? formCard.querySelector('.badge') : null;
        
        if (badge) {
            select.addEventListener('change', function() {
                const value = this.value;
                if (value === '-1' || value === '') {
                    badge.textContent = 'Pendiente';
                    badge.className = 'badge badge-lg bg-secondary float-end';
                } else if (value === '1') {
                    badge.textContent = 'Activo';
                    badge.className = 'badge badge-lg bg-success float-end';
                } else {
                    badge.textContent = 'Inactivo';
                    badge.className = 'badge badge-lg bg-danger float-end';
                }
            });
        }
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const entityEditForm = document.getElementById('entity-edit-form');
    const hideBillingModal = @json($hideBillingSwitchesModal ?? false);
    const dismissModalUrl = @json(route('entities.dismiss-billing-switches-modal'));

    if (!entityEditForm) {
        return;
    }

    entityEditForm.addEventListener('submit', function(e) {
        if (entityEditForm.dataset.billingConfirmed === '1' || hideBillingModal) {
            return;
        }
        e.preventDefault();

        const paysManagement = document.getElementById('entity_pays_management_fee')?.checked ?? false;
        const paysPrint = document.getElementById('entity_pays_print_fee')?.checked ?? false;

        document.getElementById('billing-modal-management-payer').textContent =
            paysManagement ? 'Entidad' : 'Administración';
        document.getElementById('billing-modal-print-payer').textContent =
            paysPrint ? 'Entidad' : 'Administración';

        const modalEl = document.getElementById('entityBillingSwitchesModal');
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    document.getElementById('billing-modal-confirm-btn')?.addEventListener('click', async function() {
        const hideAgain = document.getElementById('billing-modal-hide-again')?.checked ?? false;
        if (hideAgain) {
            try {
                await fetch(dismissModalUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ hide: true }),
                });
            } catch (err) {
                console.warn('No se pudo guardar la preferencia del modal', err);
            }
        }

        entityEditForm.dataset.billingConfirmed = '1';
        bootstrap.Modal.getInstance(document.getElementById('entityBillingSwitchesModal'))?.hide();
        entityEditForm.submit();
    });

    function updateBillingSwitchHints() {
        document.querySelectorAll('.entity-commercial-switch').forEach(function (input) {
            const hint = document.getElementById(input.dataset.hintTarget);
            if (!hint) return;
            hint.innerHTML = input.checked ? input.dataset.hintOn : input.dataset.hintOff;
        });
    }

    document.querySelectorAll('.entity-commercial-switch').forEach(function (input) {
        input.addEventListener('change', updateBillingSwitchHints);
    });
});
</script>

@endsection
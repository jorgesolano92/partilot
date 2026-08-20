@extends('layouts.layout')

@section('title','Entidades')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
            	<div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Entidades</a></li>
                        <li class="breadcrumb-item active">Añadir</li>
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

                    <div class="row">
                    	
                    	<div class="col-md-3" style="position: relative;">
                    		<div class="form-card bs mb-3">

                    			<div class="form-wizard-element">
                    				
                    				<span>
                    					1
                    				</span>

                    				<img src="{{url('assets/admin.svg')}}" alt="">

                    				<label>
                    					Selec. Administración
                    				</label>

                    			</div>

                    			<div class="form-wizard-element active">
                    				
                    				<span>
                    					2
                    				</span>

                    				<img src="{{url('assets/entidad.svg')}}" alt="">

                    				<label>
                    					Datos Entidad
                    				</label>

                    			</div>

                    			<div class="form-wizard-element">
                    				
                    				<span>
                    					3
                    				</span>

                    				<img src="{{url('assets/gestor.svg')}}" alt="">

                    				<label>
                    					Datos Gestor
                    				</label>

                    			</div>
                    			
                    		</div>

                    		@include('entities.partials.entity_commercial_settings_card', [
                    		    'entity' => (object) [],
                    		    'readonly' => false,
                    		    'formId' => 'entity-information-form',
                    		    'defaults' => [
                    		        'is_non_profit' => (bool) old('is_non_profit', session('entity_information.is_non_profit', true)),
                    		        'entity_pays_management_fee' => (bool) old('entity_pays_management_fee', session('entity_information.entity_pays_management_fee', false)),
                    		        'entity_pays_print_fee' => (bool) old('entity_pays_print_fee', session('entity_information.entity_pays_print_fee', false)),
                    		    ],
                    		])

                    		<div class="form-card bs">
                    			
                    			<div class="row">
                					<div class="col-4">
                						
	                    				@php $adminImg = data_get(session('selected_administration'), 'image'); @endphp
	                    				<div class="photo-preview-3 logo-round" @if($adminImg) style="background-image: url('{{ asset('images/' . $adminImg) }}'); background-size: cover;" @endif>
	                    					@if(!$adminImg)
	                    						<i class="ri-account-circle-fill"></i>
	                    					@endif
	                    				</div>
	                    				
	                    				<div style="clear: both;"></div>
                					</div>

                					<div class="col-8 text-center mt-2">

                						<h4 class="mt-0 mb-0">{{session('selected_administration')->name ?? 'Administración'}}</h4>

                						<small>
                							@php
                								$admin = session('selected_administration');
                								$managerName = 'Gestor';
                								if ($admin && $admin->manager && $admin->manager->user) {
                									$managerName = trim(($admin->manager->user->name ?? '') . ' ' . ($admin->manager->user->last_name ?? ''));
                									if (empty($managerName)) {
                										$managerName = 'Gestor';
                									}
                								}
                							@endphp
                							{{ $managerName }}
                						</small> <br>

                						<i style="position: relative; top: 3px; font-size: 16px; color: #333" class="ri-computer-line"></i> {{session('selected_administration')->province ?? 'Provincia'}}
                						
                					</div>
                				</div>

                    		</div>

                    		<a href="{{url('entities/add')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9">
                    		<div class="form-card bs" style="min-height: 658px;">
                    			<form action="{{url('entities/store-information')}}" method="POST" enctype="multipart/form-data" id="entity-information-form">
                    				@csrf()
	                    			<h4 class="mb-0 mt-1">
	                    				Datos legales de la entidad
	                    			</h4>
	                    			<small><i>Todos los campos son obligatorios</i></small>

	                    			@if (session('error'))
	                    				<div class="alert alert-warning mt-3">{{ session('error') }}</div>
	                    			@endif

	                    			@if ($errors->any())
	                    				<div class="alert alert-danger mt-3">
	                    					<ul class="mb-0">
	                    						@foreach ($errors->all() as $error)
	                    							<li>{{ $error }}</li>
	                    						@endforeach
	                    					</ul>
	                    				</div>
	                    			@endif

	                    			<div class="form-group mt-2 mb-3">
	                    				<input type="hidden" name="remove_image" id="remove_image_input" value="0">
	                    				<div class="photo-preview logo-round" id="entity-image-preview" @if(session('entity_information.image')) style="background-image: url('{{ asset('uploads/' . session('entity_information.image')) }}'); background-size: cover;" @endif>
	                    					@if(!session('entity_information.image'))
	                    						<i class="ri-image-add-line"></i>
	                    					@endif
	                    				</div>

	                    				<div>
	                    					
	                    					<small><i>Imágen entidad</i></small>
	                    					 <br>
	                    					<b>Logotipo</b>
	                    					<br>

	                    					<label style="border-radius: 30px; width: 150px; background-color: #333;" class="btn btn-md btn-dark mt-2"><small>Subir Imágen</small>
	                    						<input type="file" id="imagenInput" name="image" style="display: none;" accept="image/*">
	                    					</label>
	                    					<button type="button" id="btn-remove-entity-image" style="border-radius: 30px; width: 150px; background-color: transparent; color: #333;" class="btn btn-md btn-dark mt-2"><small>Eliminar Imágen</small></button>

	                    				</div>
	                    				
	                    				<div style="clear: both;"></div>
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

					                                    <input class="form-control" type="text" name="name" placeholder="Nombre Entidad" value="{{ old('name', session('entity_information.name')) }}" required style="border-radius: 0 30px 30px 0;">
					                                    @error('name')
					                                        <div class="text-danger small mt-1">{{ $message }}</div>
					                                    @enderror
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

					                                    <select class="form-control" name="province" id="entity-province-select" required style="border-radius: 0 30px 30px 0;">
                                                            <option value="">Seleccionar provincia</option>
                                                            @foreach(($provinces ?? []) as $province)
                                                                <option value="{{ $province }}" {{ old('province', session('entity_information.province')) === $province ? 'selected' : '' }}>{{ $province }}</option>
                                                            @endforeach
                                                        </select>
					                                    @error('province')
					                                        <div class="text-danger small mt-1">{{ $message }}</div>
					                                    @enderror
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

					                                    <select class="form-control" name="city" id="entity-city-select" required style="border-radius: 0 30px 30px 0;">
                                                            <option value="">Seleccionar localidad</option>
                                                        </select>
					                                    @error('city')
					                                        <div class="text-danger small mt-1">{{ $message }}</div>
					                                    @enderror
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

					                                    <input class="form-control" type="text" name="postal_code" placeholder="C.P." value="{{ old('postal_code', session('entity_information.postal_code')) }}" required style="border-radius: 0 30px 30px 0;">
					                                    @error('postal_code')
					                                        <div class="text-danger small mt-1">{{ $message }}</div>
					                                    @enderror
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

					                                    <input class="form-control" type="text" name="address" placeholder="Dirección" value="{{ old('address', session('entity_information.address')) }}" required style="border-radius: 0 30px 30px 0;">
					                                    @error('address')
					                                        <div class="text-danger small mt-1">{{ $message }}</div>
					                                    @enderror
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

					                                    <input class="form-control" type="text" name="nif_cif" id="entity-nif-cif" placeholder="B26262626" value="{{ old('nif_cif', session('entity_information.nif_cif')) }}" required style="border-radius: 0 30px 30px 0;">
					                                    @error('nif_cif')
					                                        <div class="text-danger small mt-1">{{ $message }}</div>
					                                    @enderror
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

					                                    <input class="form-control" type="text" name="phone" placeholder="940 200 200" value="{{ old('phone', session('entity_information.phone')) }}" required style="border-radius: 0 30px 30px 0;">
					                                    @error('phone')
					                                        <div class="text-danger small mt-1">{{ $message }}</div>
					                                    @enderror
					                                </div>
				                    			</div>
	                    					</div>

	                    					<div class="col-4">
	                    						<div class="form-group mt-2 mb-3">
	                    							<label class="label-control">Email acceso panel</label>

					                    			<div class="input-group input-group-merge group-form">

					                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
					                                        <img src="{{url('assets/form-groups/admin/9.svg')}}" alt="">
					                                    </div>

					                                    <input class="form-control" type="email" id="entity-email" name="email" placeholder="ejemplo@cuentaemail.com" value="{{ old('email', session('entity_information.email')) }}" required style="border-radius: 0 30px 30px 0;">
					                                    @error('email')
					                                        <div class="text-danger small mt-1 field-error">{{ $message }}</div>
					                                    @enderror
					                                </div>
					                                <small class="text-muted">Se enviará un correo a esta dirección con una contraseña provisional para acceder al panel. Al iniciar sesión podrá cambiarla o posponer el cambio.</small>
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

				                    			<div class="input-group input-group-merge group-form">

				                                    <textarea class="form-control" placeholder="Añade tu comentario" name="comments" id="" rows="6">{{ old('comments', session('entity_information.comments')) }}</textarea>
				                                    @error('comments')
				                                        <div class="text-danger small mt-1">{{ $message }}</div>
				                                    @enderror
				                                </div>
			                    			</div>

	                    				</div>

	                    				<div class="col-12 text-end">
	                    					<button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">Siguiente
	                    						<i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-arrow-right-circle-line"></i></button>
	                    				</div>

	                    			</div>

	                    			</form>

	                    		</div>
	                    	</div>

                    </div>

                    
                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div>
    <!-- end row-->

</div> <!-- container -->

@include('entities.partials.billing_switches_confirm_modal', ['onboardingMode' => true])

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
    .group-form .form-control.is-invalid,
    .group-form .ts-wrapper.is-invalid .ts-control {
        border-color: #dc3545 !important;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>

	document.getElementById('imagenInput').addEventListener('change', function(event) {
	    const archivo = event.target.files[0];
	    document.getElementById('remove_image_input').value = '0';
	    const preview = document.getElementById('entity-image-preview');
	    if (archivo) {
	        const lector = new FileReader();
	        lector.onload = function(e) {
	            preview.style.backgroundImage = 'url(' + e.target.result + ')';
	            preview.style.backgroundSize = 'cover';
	            preview.querySelector('i') && preview.querySelector('i').remove();
	        };
	        lector.readAsDataURL(archivo);
	    } else {
	        preview.style.backgroundImage = 'none';
	        preview.style.backgroundSize = '';
	        if (!preview.querySelector('i')) {
	            const i = document.createElement('i');
	            i.className = 'ri-image-add-line';
	            preview.appendChild(i);
	        }
	    }
	});

	document.getElementById('btn-remove-entity-image').addEventListener('click', function() {
	    document.getElementById('remove_image_input').value = '1';
	    const preview = document.getElementById('entity-image-preview');
	    preview.style.backgroundImage = 'none';
	    preview.style.backgroundSize = '';
	    if (!preview.querySelector('i')) {
	        const i = document.createElement('i');
	        i.className = 'ri-image-add-line';
	        preview.appendChild(i);
	    }
	    document.getElementById('imagenInput').value = '';
	});

	document.addEventListener('DOMContentLoaded', function() {
        const provinceCityMap = @json($provinceCityMap ?? []);
        const provinceSelect = document.getElementById('entity-province-select');
        const citySelect = document.getElementById('entity-city-select');
        const selectedCity = @json(old('city', session('entity_information.city')));
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
                // Restaurar valores (old/session) tras inicializar TomSelect
                if (provinceSelect.getAttribute('data-selected') || provinceSelect.value) {
                    var provVal = provinceSelect.value || provinceSelect.getAttribute('data-selected');
                    if (provVal) {
                        provinceTs.setValue(provVal, true);
                        fillCities(provVal);
                    }
                }
                if (selectedCity) {
                    cityTs.setValue(selectedCity, true);
                }
            } else {
                provinceSelect.addEventListener('change', function() {
                    fillCities(this.value);
                });
            }
        }

	    // Al cargar sin imagen en sesión, no mostrar imagen previa de otros flujos
	    localStorage.removeItem('image_entity_create');

	    initSpanishDocumentValidation('entity-nif-cif', {
	        forEntity: true,
	        showMessage: true
	    });
	    initEmailValidation('entity-email', {
	        context: 'entity',
	        showMessage: true
	    });

	    document.querySelectorAll('.entity-commercial-switch').forEach(function (input) {
	        input.addEventListener('change', function () {
	            const hint = document.getElementById(input.dataset.hintTarget);
	            if (!hint) return;
	            hint.innerHTML = input.checked ? input.dataset.hintOn : input.dataset.hintOff;
	        });
	    });

	    const entityForm = document.getElementById('entity-information-form');
	    if (entityForm) {
	        const fieldRules = {
	            name: { label: 'Nombre comercial', test: (v) => v.trim() !== '' },
	            province: { label: 'Provincia', test: (v) => v.trim() !== '' },
	            city: { label: 'Localidad', test: (v) => v.trim() !== '' },
	            postal_code: { label: 'Código postal', test: (v) => v.trim() !== '' },
	            address: { label: 'Dirección', test: (v) => v.trim() !== '' },
	            nif_cif: { label: 'NIF/CIF', test: (v) => v.trim() !== '' },
	            phone: { label: 'Teléfono', test: (v) => v.trim() !== '' },
	            email: { label: 'Email acceso panel', test: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()) },
	        };

	        const clearFieldError = function (field) {
	            field.classList.remove('is-invalid');
	            const group = field.closest('.input-group') || field.closest('.group-form');
	            if (group) {
	                group.classList.remove('is-invalid');
	            }
	            const wrapper = field.closest('.form-group');
	            if (wrapper) {
	                const inline = wrapper.querySelector('.client-field-error');
	                if (inline) inline.remove();
	            }
	        };

	        const showFieldError = function (field, message) {
	            field.classList.add('is-invalid');
	            const group = field.closest('.input-group') || field.closest('.group-form');
	            if (group) {
	                group.classList.add('is-invalid');
	            }
	            const wrapper = field.closest('.form-group');
	            if (!wrapper) return;
	            let inline = wrapper.querySelector('.client-field-error');
	            if (!inline) {
	                inline = document.createElement('div');
	                inline.className = 'text-danger small mt-1 client-field-error';
	                wrapper.appendChild(inline);
	            }
	            inline.textContent = message;
	        };

	        entityForm.querySelectorAll('input, select, textarea').forEach(function (field) {
	            if (!field.name || !fieldRules[field.name]) return;
	            field.addEventListener('input', function () { clearFieldError(field); });
	            field.addEventListener('change', function () { clearFieldError(field); });
	        });

        entityForm.addEventListener('submit', function (event) {
            if (entityForm.dataset.billingConfirmed === '1') {
                // continuar con validación normal
            } else {
                event.preventDefault();
                const canDonate = document.getElementById('is_non_profit')?.checked ?? false;
                const paysManagement = document.getElementById('entity_pays_management_fee')?.checked ?? false;
                const paysPrint = document.getElementById('entity_pays_print_fee')?.checked ?? false;
                document.getElementById('billing-modal-donation-cert').textContent = canDonate ? 'Sí' : 'No';
                document.getElementById('billing-modal-management-payer').textContent = paysManagement ? 'Entidad' : 'Administración';
                document.getElementById('billing-modal-print-payer').textContent = paysPrint ? 'Entidad' : 'Administración';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('entityBillingSwitchesModal')).show();
                return;
            }

            // TomSelect a veces no sincroniza el <select> nativo hasta el submit.
            try {
                if (provinceTs) {
                    var pv = provinceTs.getValue();
                    provinceSelect.value = Array.isArray(pv) ? (pv[0] || '') : (pv || '');
                }
                if (cityTs) {
                    var cv = cityTs.getValue();
                    citySelect.value = Array.isArray(cv) ? (cv[0] || '') : (cv || '');
                }
            } catch (e) {}

            let firstInvalid = null;
            Object.keys(fieldRules).forEach(function (name) {
                const field = entityForm.querySelector('[name="' + name + '"]');
                if (!field) return;
                clearFieldError(field);
                const rule = fieldRules[name];
                if (!rule.test(field.value || '')) {
                    if (!firstInvalid) firstInvalid = field;
                    showFieldError(field, 'Revise el campo ' + rule.label + '.');
                }
            });
            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
	    }
	});


    document.getElementById('billing-modal-confirm-btn')?.addEventListener('click', function () {
        const entityForm = document.getElementById('entity-information-form');
        if (!entityForm) return;
        entityForm.dataset.billingConfirmed = '1';
        bootstrap.Modal.getInstance(document.getElementById('entityBillingSwitchesModal'))?.hide();
        entityForm.requestSubmit();
    });
</script>

@endsection
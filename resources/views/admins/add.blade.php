@extends('layouts.layout')

@section('title','Administraciones')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
            	<div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Administraciones</a></li>
                        <li class="breadcrumb-item active">Añadir</li>
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

            		<h4 class="header-title">

                    	Datos Administración

                    </h4>

                    <br>

                    <div class="row">
                    	
                    	<div class="col-md-3" style="position: relative;">
                    		<div class="form-card bs mb-3">

                    			<div class="form-wizard-element active">
                    				
                    				<span>
                    					1
                    				</span>

                    				<img src="{{url('assets/admin.svg')}}" alt="">

                    				<label>
                    					Datos administración
                    				</label>

                    			</div>

                    			<div class="form-wizard-element">
                    				
                    				<span>
                    					2
                    				</span>

                    				<img src="{{url('assets/gestor.svg')}}" alt="">

                    				<label>
                    					Datos Gestor
                    				</label>

                    			</div>
                    			
                    		</div>

                    		<div class="form-card">
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

	                                    <input class="form-control" type="text" name="web" placeholder="www.administracion.es" value="{{ old('web') }}" style="border-radius: 0 30px 30px 0;">
	                                </div>
                    			</div>
                    		</div>

                    		<a href="{{url('administrations')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9">
                    		<div class="form-card bs" style="min-height: 658px;">
                    			<form action="{{url('administrations/add/manager')}}" method="POST" enctype="multipart/form-data" id="add-form">

                    				@csrf()
                    				
                                <!-- Web (hidden, sincronizado con el campo visible de la izquierda) -->
                                <input type="hidden" name="web" id="webHidden" value="{{ old('web') }}">
                    				
	                    			<h4 class="mb-0 mt-1">
	                    				Datos legales de la administración
	                    			</h4>
	                    			<small><i>Todos los campos son obligatorios</i></small>

	                    			<div class="form-group mt-2 mb-3">

	                    				<div class="photo-preview">
	                    					
	                    					<i class="ri-image-add-line"></i>

	                    				</div>

	                    				<div>
	                    					
	                    					<small><i>Imágen empresa</i></small>
	                    					 <br>
	                    					<b>Logotipo</b>
	                    					<br>

	                    					<label style="border-radius: 30px; width: 150px; background-color: #333;" class="btn btn-md btn-dark mt-2"><small>Subir Imágen</small>
	                    						<input type="file" id="imagenInput" name="image" style="display: none;" accept="image/*">
	                    					</label>
	                    					<button type="button" id="btnEliminarImagen" style="border-radius: 30px; width: 150px; background-color: transparent; color: #333;" class="btn btn-md btn-dark mt-2"><small>Eliminar Imágen</small></button>

	                    				</div>
	                    				
	                    				<div style="clear: both;"></div>
	                    			</div>

	                    			
	                    			<br>

	                    			<div>
	                    				<div class="row">
	                    					
                    					<div class="col-3">
	                    						<div class="form-group mt-2 mb-3">
	                    							<label class="label-control">Nombre comercial</label>

					                    			<div class="input-group input-group-merge group-form">

					                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
					                                        <img src="{{url('assets/form-groups/admin/1.svg')}}" alt="">
					                                    </div>

					                                    <input class="form-control" type="text" required name="name" placeholder="Nombre Administración" value="{{ old('name') }}" style="border-radius: 0 30px 30px 0;">
					                                </div>
				                    			</div>
	                    					</div>
                    					<div class="col-2">
	                    						<div class="form-group mt-2 mb-3">
	                    							<label class="label-control">Nº Receptor</label>

					                    			<div class="input-group input-group-merge group-form">

					                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
					                                        <img src="{{url('assets/form-groups/admin/2.svg')}}" alt="">
					                                    </div>

					                                    <input class="form-control" type="text" required name="receiving" placeholder="00000" maxlength="5" pattern="[0-9]{5}" value="{{ old('receiving') }}" style="border-radius: 0 30px 30px 0;">
					                                </div>
				                    			</div>
	                    					</div>
                    					<div class="col-2">
	                    						<div class="form-group mt-2 mb-3">
	                    							<label class="label-control">Nº Administración</label>

					                    			<div class="input-group input-group-merge group-form">

					                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
					                                        <img src="{{url('assets/form-groups/admin/2.svg')}}" alt="">
					                                    </div>

					                                    <input class="form-control" type="text" name="admin_number" value="{{ old('admin_number') }}" placeholder="260000001" maxlength="9" pattern="[0-9]{9}" inputmode="numeric" title="Exactamente 9 dígitos numéricos" style="border-radius: 0 30px 30px 0;">
					                                </div>
					                                <small class="form-text text-muted d-block mt-1">Introduce el número identificativo oficial de nueve dígitos (ejemplo: 260000001). Solo números; se conservan los ceros a la izquierda.</small>
					                                @error('admin_number')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
				                    			</div>
	                    					</div>
                    					<div class="col-5">
	                    						<div class="form-group mt-2 mb-3">
	                    							<label class="label-control">Nombre Autónomo / Sociedad</label>

					                    			<div class="input-group input-group-merge group-form">

					                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
					                                        <img src="{{url('assets/form-groups/admin/3.svg')}}" alt="">
					                                    </div>

					                                    <input class="form-control" type="text" required name="society" placeholder="José Andrés / Administración S.L.U." value="{{ old('society') }}" style="border-radius: 0 30px 30px 0;">
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

					                                    <input class="form-control" type="text" required name="nif_cif" id="admin-nif-cif" placeholder="B26262626" value="{{ old('nif_cif') }}" style="border-radius: 0 30px 30px 0;">
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

					                                    <select class="form-control" required name="province" id="province-select" style="border-radius: 0 30px 30px 0;">
                                                            <option value="">Seleccionar provincia</option>
                                                            @foreach(($provinces ?? []) as $province)
                                                                <option value="{{ $province }}" {{ old('province') === $province ? 'selected' : '' }}>{{ $province }}</option>
                                                            @endforeach
                                                        </select>
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

					                                    <select class="form-control" required name="city" id="city-select" style="border-radius: 0 30px 30px 0;">
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

					                                    <input class="form-control" type="number" required name="postal_code" placeholder="C.P." value="{{ old('postal_code') }}" style="border-radius: 0 30px 30px 0;">
					                                </div>
				                    			</div>
	                    					</div>

                    					<div class="col-5">
	                    						<div class="form-group mt-2 mb-3">
	                    							<label class="label-control">Dirección</label>

					                    			<div class="input-group input-group-merge group-form">

					                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
					                                        <img src="{{url('assets/form-groups/admin/8.svg')}}" alt="">
					                                    </div>

					                                    <input class="form-control" type="text" required name="address" placeholder="Dirección" value="{{ old('address') }}" style="border-radius: 0 30px 30px 0;">
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

					                                    <input class="form-control" type="email" id="admin-email" required name="email" placeholder="ejemplo@cuentaemail.com" value="{{ old('email') }}" style="border-radius: 0 30px 30px 0;">
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

					                                    <input class="form-control" type="phone" required name="phone" placeholder="940 200 200" value="{{ old('phone') }}" style="border-radius: 0 30px 30px 0;">
					                                </div>
				                    			</div>
	                    					</div>

	                    					<div class="col-12">
	                    						<p class="text-muted small mb-0 mt-1"><i class="ri-information-line"></i> El acceso al panel se enviará por correo de forma manual desde la ficha de la administración (usuario fijo + enlace para establecer contraseña).</p>
	                    					</div>

	                    				</div>
	                    			</div>

	                    			<h4 class="mb-0 mt-1">
	                    				Datos Pago
	                    			</h4>
	                    			<small><i>Introduzca los detalles de su cuenta bancaria para procesar los pagos. Asegúrese de que la <br> información es correcta y está actualizada</i></small>

	                    			<div class="row">
	                    				
	                    				<div class="col-8">
	                    					
	                    					<div class="form-group mt-2">
				                    			<div class="input-group input-group-merge group-form">
				                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
				                                        <span style="font-weight: bold;">ES</span>
				                                    </div>
				                                    @php
				                                        $accountDisplay = '';
				                                        if (old('account')) {
				                                            $nums = preg_replace('/\D/', '', old('account'));
				                                            if (strlen($nums) >= 2) {
				                                                $accountDisplay = substr($nums, 0, 2);
				                                                if (strlen($nums) > 2) {
				                                                    $accountDisplay .= ' ' . substr($nums, 2, 4);
				                                                    if (strlen($nums) > 6) {
				                                                        $accountDisplay .= ' ' . substr($nums, 6, 4);
				                                                        if (strlen($nums) > 10) {
				                                                            $accountDisplay .= ' ' . substr($nums, 10, 2);
				                                                            if (strlen($nums) > 12) {
				                                                                $accountDisplay .= ' ' . substr($nums, 12, 10);
				                                                            }
				                                                        }
				                                                    }
				                                                }
				                                            } else {
				                                                $accountDisplay = $nums;
				                                            }
				                                        }
				                                    @endphp
				                                    <input class="form-control" type="text" id="account-input" placeholder="12 1234 1234 12 1234567890" value="{{ $accountDisplay }}" style="border-radius: 0 30px 30px 0;">
				                                </div>
			                    			</div>
			                    			<small class="text-muted">Ingrese el número de cuenta bancaria. El prefijo ES se añadirá automáticamente.</small>

	                    				</div>

	                    				<div class="col-4 text-end">
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
	// Si hay old() (error de validación), no sobrescribir con localStorage
	window.hasOldInput = {{ (is_array(old()) && count(old()) > 0) ? 'true' : 'false' }};

	// Al crear una administración nueva: borrar imagen de permanencia de otra creación (solo si no venimos del paso gestor "Atrás")
	if (!document.referrer || document.referrer.indexOf('add/manager') === -1) {
		localStorage.removeItem('image_admin_create');
		localStorage.removeItem('administration_manager_form_data');
	}

	document.getElementById('imagenInput').addEventListener('change', function(event) {
	    const archivo = event.target.files[0];

	    if (archivo) {
	        const lector = new FileReader();
	        lector.onload = function(e) {
	        	$('.photo-preview').css('background-image', 'url('+e.target.result+')');
	        	// Ocultar el icono cuando se carga una imagen
	        	$('.photo-preview i').hide();
	        	// Guardar en localStorage para persistencia
	        	localStorage.setItem('image_admin_create', e.target.result);
	        }
	        lector.readAsDataURL(archivo);
	    }
	    // Si el usuario cancela el diálogo, no hacer nada: mantener la imagen actual (solo se borra con "Eliminar Imagen")
	});

	// Botón Eliminar Imagen: quitar valor del input y limpiar preview
	document.getElementById('btnEliminarImagen').addEventListener('click', function() {
	    const input = document.getElementById('imagenInput');
	    if (input) input.value = '';
	    $('.photo-preview').css('background-image', 'none');
	    $('.photo-preview i').show();
	    localStorage.removeItem('image_admin_create');
	});

	// Restaurar imagen si hay error de validación
	document.addEventListener('DOMContentLoaded', function() {
	    const savedImage = localStorage.getItem('image_admin_create');
	    if (savedImage) {
	        $('.photo-preview').css('background-image', 'url('+savedImage+')');
	        $('.photo-preview i').hide();
	    }
	});

	// Limpiar localStorage al enviar exitosamente
	$('#add-form').on('submit', function() {
	    setTimeout(() => {
	        localStorage.removeItem('image_admin_create');
	    }, 1000);
	});

	// Persistir datos del formulario en localStorage
	function saveFormData() {
	    const formData = {
	        web: document.querySelector('input[name="web"]')?.value || '',
	        name: document.querySelector('input[name="name"]')?.value || '',
	        receiving: document.querySelector('input[name="receiving"]')?.value || '',
	        admin_number: document.querySelector('input[name="admin_number"]')?.value || '',
	        society: document.querySelector('input[name="society"]')?.value || '',
	        nif_cif: document.querySelector('input[name="nif_cif"]')?.value || '',
	        province: document.querySelector('select[name="province"]')?.value || '',
	        city: document.querySelector('select[name="city"]')?.value || '',
	        postal_code: document.querySelector('input[name="postal_code"]')?.value || '',
	        address: document.querySelector('input[name="address"]')?.value || '',
	        email: document.querySelector('input[name="email"]')?.value || '',
	        phone: document.querySelector('input[name="phone"]')?.value || '',
	        account: (document.getElementById('account-input') && document.getElementById('account-input').value) ? document.getElementById('account-input').value.trim() : ''
	    };
	    localStorage.setItem('administration_form_data', JSON.stringify(formData));
	}

	// Cargar datos guardados (no sobrescribir si hay old() por error de validación)
	function loadFormData() {
	    const savedData = localStorage.getItem('administration_form_data');
	    if (savedData && !window.hasOldInput) {
	        const formData = JSON.parse(savedData);
	        Object.keys(formData).forEach(key => {
	            if (key === 'account') {
	                const accountInput = document.getElementById('account-input');
	                if (accountInput && formData.account) {
	                    accountInput.value = formData.account;
	                }
	            } else {
	                const input = document.querySelector(`input[name="${key}"], select[name="${key}"]`);
	                if (input && formData[key]) {
	                    input.value = formData[key];
	                }
	            }
	        });
	    }
	}

	// Cargar datos al cargar la página
	loadFormData();

	// Sincronizar el campo web visible con el hidden dentro del formulario
	const webVisible = document.querySelector('input[name="web"]');
	const webHidden = document.getElementById('webHidden');
	if (webVisible && webHidden) {
	    // Inicializar
	    webHidden.value = webVisible.value || '';
	    // Sincronizar en tiempo real
	    webVisible.addEventListener('input', function() {
	        webHidden.value = this.value || '';
	    });
	    webVisible.addEventListener('change', function() {
	        webHidden.value = this.value || '';
	    });
	}

	// Guardar datos al cambiar cualquier campo
	document.querySelectorAll('input, select').forEach(input => {
	    input.addEventListener('input', saveFormData);
	    input.addEventListener('change', saveFormData);
	});

    // Enviar el campo 'web' (que está fuera del <form>) como hidden dentro del formulario (no borrar administration_form_data para poder restaurar al volver atrás)
    document.querySelector('form').addEventListener('submit', function(e) {
        const form = this;
        const webInputOutside = document.querySelector('input[name="web"]');
        if (webInputOutside) {
            const hiddenWeb = document.createElement('input');
            hiddenWeb.type = 'hidden';
            hiddenWeb.name = 'web';
            hiddenWeb.value = webInputOutside.value || '';
            form.appendChild(hiddenWeb);
        }
        // No borrar administration_form_data aquí: al volver atrás desde paso 2 se restaura la cuenta y el resto
    });

    // Validar cuenta bancaria antes de enviar: vacía o exactamente 22 dígitos
    document.querySelector('form').addEventListener('submit', function(e) {
        const accountInput = document.getElementById('account-input');
        if (accountInput) {
            const digits = accountInput.value.replace(/\s/g, '');
            if (digits.length > 0 && digits.length !== 22) {
                e.preventDefault();
                alert('La cuenta bancaria debe estar vacía o tener exactamente 22 dígitos.');
                return false;
            }
        }
    });

	// Máscara para Nº Receptor (solo números, máximo 5)
	const receivingInput = document.querySelector('input[name="receiving"]');
	if (receivingInput) {
		receivingInput.addEventListener('input', function(e) {
			// Solo permitir números
			this.value = this.value.replace(/[^0-9]/g, '');
			// Limitar a 5 dígitos
			if (this.value.length > 5) {
				this.value = this.value.slice(0, 5);
			}
		});
	}

	// Máscara para Nº Administración (solo números, máximo 9)
	const adminNumberInput = document.querySelector('input[name="admin_number"]');
	if (adminNumberInput) {
		adminNumberInput.addEventListener('input', function() {
			this.value = this.value.replace(/[^0-9]/g, '').slice(0, 9);
		});
	}

    // Provincia -> Localidad dependiente
    const provinceCityMap = @json($provinceCityMap ?? []);
    const provinceSelect = document.getElementById('province-select');
    const citySelect = document.getElementById('city-select');
    const oldCity = @json(old('city'));
    let provinceTs = null;
    let cityTs = null;
    function fillCitiesByProvince(province, preselect = '') {
        if (!citySelect) return;
        const cities = provinceCityMap[province] || [];
        if (cityTs) {
            cityTs.clear(true);
            cityTs.clearOptions();
            cities.forEach((city) => cityTs.addOption({ value: city, text: city }));
            cityTs.refreshOptions(false);
            const targetValue = preselect || oldCity || '';
            if (cities.includes(targetValue)) {
                cityTs.setValue(targetValue, true);
            } else {
                cityTs.clear(true);
            }
            return;
        }

        citySelect.innerHTML = '<option value="">Seleccionar localidad</option>';
        cities.forEach((city) => {
            const option = document.createElement('option');
            option.value = city;
            option.textContent = city;
            if ((preselect && preselect === city) || (!preselect && oldCity === city)) {
                option.selected = true;
            }
            citySelect.appendChild(option);
        });
    }
    if (provinceSelect && citySelect) {
        fillCitiesByProvince(provinceSelect.value, citySelect.value || oldCity || '');
        if (window.TomSelect) {
            provinceTs = new TomSelect(provinceSelect, (window.partilotTomSelectStartsWithOptions || function (o) { return o; })({
                create: false,
                allowEmptyOption: true,
                placeholder: 'Seleccionar provincia',
            }));
            cityTs = new TomSelect(citySelect, (window.partilotTomSelectStartsWithOptions || function (o) { return o; })({
                create: false,
                allowEmptyOption: true,
                placeholder: 'Seleccionar localidad',
            }));
            provinceTs.on('change', function(value) {
                fillCitiesByProvince(value || '', '');
                saveFormData();
            });
            if (!provinceSelect.value) {
                provinceTs.clear(true);
            }
            if (!citySelect.value) {
                cityTs.clear(true);
            }
        } else {
            provinceSelect.addEventListener('change', function() {
                fillCitiesByProvince(this.value, '');
                saveFormData();
            });
        }
    }

	// Inicializar validación de documento español
	document.addEventListener('DOMContentLoaded', function() {
	    initSpanishDocumentValidation('admin-nif-cif', {
	        showMessage: true
	    });
	    
	    // Inicializar validación de email
	    initEmailValidation('admin-email', {
	        context: 'administration',
	        showMessage: true
	    });
	});

	// Máscara para número de cuenta (formato: ES 12 1234 1234 12 123456789)
	const accountInput = document.getElementById('account-input');
	if (accountInput) {
		// Función para formatear el número de cuenta
		function formatAccountNumber(value) {
			// Remover todos los espacios
			const numbers = value.replace(/\s/g, '');
			
			// Aplicar formato: 12 1234 1234 12 123456789
			if (numbers.length <= 2) {
				return numbers;
			} else if (numbers.length <= 6) {
				return numbers.slice(0, 2) + ' ' + numbers.slice(2);
			} else if (numbers.length <= 10) {
				return numbers.slice(0, 2) + ' ' + numbers.slice(2, 6) + ' ' + numbers.slice(6);
			} else if (numbers.length <= 12) {
				return numbers.slice(0, 2) + ' ' + numbers.slice(2, 6) + ' ' + numbers.slice(6, 10) + ' ' + numbers.slice(10);
			} else {
				return numbers.slice(0, 2) + ' ' + numbers.slice(2, 6) + ' ' + numbers.slice(6, 10) + ' ' + numbers.slice(10, 12) + ' ' + numbers.slice(12, 22);
			}
		}

		// Aplicar máscara mientras el usuario escribe
		accountInput.addEventListener('input', function(e) {
			// Obtener la posición del cursor
			const cursorPosition = this.selectionStart;
			const oldValue = this.value;
			
			// Remover todo excepto números
			const numbers = this.value.replace(/[^0-9]/g, '');
			
			// Limitar a 22 dígitos (IBAN español sin ES: 2+4+4+2+10)
			const limitedNumbers = numbers.slice(0, 22);
			
			// Aplicar formato
			const formatted = formatAccountNumber(limitedNumbers);
			
			// Actualizar el valor
			this.value = formatted;
			
			// Ajustar posición del cursor
			const diff = formatted.length - oldValue.length;
			const newPosition = Math.max(0, cursorPosition + diff);
			this.setSelectionRange(newPosition, newPosition);
		});

		// Antes de enviar el formulario, remover espacios y guardar solo números
		document.querySelector('#add-form').addEventListener('submit', function(e) {
			// Crear un campo hidden con el valor sin espacios (incluso si está vacío)
			const hiddenInput = document.createElement('input');
			hiddenInput.type = 'hidden';
			hiddenInput.name = 'account';
			hiddenInput.value = accountInput.value.replace(/\s/g, '');
			this.appendChild(hiddenInput);
		});
	}

</script>

@endsection
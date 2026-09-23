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
                        <li class="breadcrumb-item"><a href="{{ route('administrations.index') }}">Administraciones</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('administrations.show', $administration->id) }}">Administración</a></li>
                        <li class="breadcrumb-item active">Editar</li>
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

                    <form action="{{ route('administrations.update', $administration->id) }}" method="POST" enctype="multipart/form-data" id="form-edit">
                        @csrf
                        @method('PUT')
                        
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

	                			<div class="form-wizard-element">
	                				
	                				<span>
	                					3
	                				</span>

	                				<img src="{{url('assets/api.svg')}}" alt="">

	                				<label>
	                					Configuración API
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

	                                    <input class="form-control" type="text" name="web" value="{{ old('web', $administration->web) }}" placeholder="www.administracion.es" style="border-radius: 0 30px 30px 0;">
	                                </div>
                    			</div>
                    		</div>

                    		<div class="form-card show-content bs">
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
                                        } elseif ((int) $statusValue === 1) {
                                            $statusText = 'Activo';
                                            $statusClass = 'bg-success';
                                        } elseif ((int) $statusValue === 3) {
                                            $statusText = 'Bloqueado';
                                            $statusClass = 'bg-warning';
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
	                    				<select name="status" id="admin_status" class="form-select">
	                    					<option value="-1" {{ ($statusValue === null || $statusValue === -1) ? 'selected' : '' }}>Pendiente</option>
	                    					<option value="1" {{ (int) $statusValue === 1 ? 'selected' : '' }}>Activo</option>
	                    					<option value="3" {{ (int) $statusValue === 3 ? 'selected' : '' }}>Bloqueado</option>
	                    					<option value="0" {{ $statusValue !== null && (int) $statusValue === 0 ? 'selected' : '' }}>Inactivo</option>
	                    				</select>
	                    			</div>
	                    			<script>
	                    			document.addEventListener('DOMContentLoaded', function() {
	                    				const select = document.getElementById('admin_status');
	                    				if (select) {
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
	                    							} else if (value === '3') {
	                    								badge.textContent = 'Bloqueado';
	                    								badge.className = 'badge badge-lg bg-warning float-end';
	                    							} else {
	                    								badge.textContent = 'Inactivo';
	                    								badge.className = 'badge badge-lg bg-danger float-end';
	                    							}
	                    						});
	                    					}
	                    				}
	                    			});
	                    			</script>
                    			</div>
                    		</div>

                    		<a href="{{ route('administrations.show', $administration->id) }}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9">
                    		<div class="form-card bs">
                    			<h4 class="mb-0 mt-1">
                    				Datos legales de la administración
                    			</h4>
                    			<small><i>Todos los campos son obligatorios</i></small>

                    			<div class="form-group mt-2 mb-3">

                    				<div class="photo-preview" @if($administration->image) style="background-image: url('{{ asset('images/' . $administration->image) }}');" @endif>
                    					@if(!$administration->image)
                    						<i class="ri-image-add-line"></i>
                    					@endif
                    				</div>

                    				<div>
                    					
                    					<small><i>Imágen empresa</i></small>
                    					 <br>
                    					<b>Logotipo</b>
                    					<br>

                    					<label style="border-radius: 30px; width: 150px; background-color: #333;" class="btn btn-md btn-dark mt-2">
                    						<small>Subir Imágen</small>
                    						<input type="file" id="imagenInput" name="image" style="display: none;" accept="image/*">
                    					</label>
                    					<button type="button" id="btnEliminarImagen" style="border-radius: 30px; width: 150px; background-color: transparent; color: #333;" class="btn btn-md btn-dark mt-2">
                    							<small>Eliminar Imágen</small>
                    						</button>

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

				                                    <input value="{{ old('name', $administration->name) }}" class="form-control" type="text" name="name" placeholder="Nombre Administración" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input value="{{ old('receiving', $administration->receiving) }}" class="form-control" type="text" name="receiving" placeholder="00000" maxlength="5" pattern="[0-9]{5}" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input value="{{ old('admin_number', $administration->admin_number) }}" class="form-control" type="text" name="admin_number" placeholder="260000001" maxlength="9" pattern="[0-9]{9}" inputmode="numeric" title="Exactamente 9 dígitos numéricos" style="border-radius: 0 30px 30px 0;">
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

				                                    <input value="{{ old('society', $administration->society) }}" class="form-control" type="text" name="society" placeholder="José Andrés / Administración S.L.U." style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input value="{{ old('nif_cif', $administration->nif_cif) }}" class="form-control" type="text" name="nif_cif" id="admin-edit-nif-cif" placeholder="B26262626" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <select class="form-control" name="province" id="province-select" style="border-radius: 0 30px 30px 0;" required>
                                                        <option value="">Seleccionar provincia</option>
                                                        @foreach(($provinces ?? []) as $province)
                                                            <option value="{{ $province }}" {{ old('province', $administration->province) === $province ? 'selected' : '' }}>{{ $province }}</option>
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

				                                    <select class="form-control" name="city" id="city-select" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input value="{{ old('postal_code', $administration->postal_code) }}" class="form-control" type="number" name="postal_code" placeholder="C.P." style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input value="{{ old('address', $administration->address) }}" class="form-control" type="text" name="address" placeholder="Dirección" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input value="{{ old('email', $administration->email) }}" class="form-control" type="email" id="admin-edit-email" name="email" placeholder="ejemplo@cuentaemail.com" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input value="{{ old('phone', $administration->phone) }}" class="form-control" type="phone" name="phone" placeholder="940 200 200" style="border-radius: 0 30px 30px 0;" required>
				                                </div>
			                    			</div>
                    					</div>

                    				</div>
                    			</div>

								@php
									$adminPanelUser = \App\Models\User::where('panel_account_type', 'administration')->where('panel_account_id', $administration->id)->first();
								@endphp
								@if($adminPanelUser)
								<h4 class="mb-0 mt-1 mt-3">Acceso al panel web</h4>
								<small class="text-muted">Usuario de acceso (no modificable): <strong>{{ $adminPanelUser->panel_login_username ?? '—' }}</strong>. El correo de la administración se usa para notificaciones. Deje en blanco para no cambiar la contraseña del panel.</small>
								<div class="row mt-2">
									<div class="col-md-4">
										<div class="form-group mb-3">
											<label class="label-control">Nueva contraseña del panel</label>
											<input class="form-control" type="password" name="panel_password" autocomplete="new-password" style="border-radius: 30px;">
										</div>
									</div>
									<div class="col-md-4">
										<div class="form-group mb-3">
											<label class="label-control">Confirmar contraseña</label>
											<input class="form-control" type="password" name="panel_password_confirmation" autocomplete="new-password" style="border-radius: 30px;">
										</div>
									</div>
								</div>
								@endif

								<br>
								<br>

                    			<h4 class="mb-0 mt-1">
                    				Datos Pago
                    			</h4>
                    			<small><i>Introduzca los detalles de su cuenta bancaria para procesar los pagos. Asegúrese de que la <br> información es correcta y está actualizada</i></small>

                    			<div class="row">
                    				
                    				<div class="col-8">
                    					
                    					<div class="form-group mt-2">
			                    			<div class="input-group input-group-merge group-form">
			                    				@php
			                    					// Priorizar old('account') cuando hay error de validación
			                    					if (old('account') !== null && old('account') !== '') {
			                    						$accountValue = preg_replace('/\D/', '', old('account'));
			                    					} else {
			                    						$accountValue = $administration->account ?? '';
			                    					}
			                    					// Si empieza con ES, quitarlo para mostrar solo los dígitos
			                    					if ($accountValue && str_starts_with($accountValue, 'ES')) {
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
			                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
			                                        <span style="font-weight: bold;">ES</span>
			                                    </div>
			                                    <input class="form-control" type="text" id="account-input" placeholder="12 1234 1234 12 1234567890" value="{{ old('account', $accountValue) }}" style="border-radius: 0 30px 30px 0;">
			                                </div>
		                    			</div>
		                    			<small class="text-muted">Ingrese el número de cuenta bancaria. El prefijo ES se añadirá automáticamente.</small>

                    				</div>

                    				<div class="col-4 text-end">
                    					<button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">
                    						Guardar
                    						<i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i>
                    					</button>
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

document.getElementById('imagenInput').addEventListener('change', function(event) {
    const archivo = event.target.files[0];

    if (archivo) {
        const lector = new FileReader();
        lector.onload = function(e) {
        	$('.photo-preview').css('background-image', 'url('+e.target.result+')');
        	// Ocultar el icono cuando se carga una imagen
        	$('.photo-preview i').hide();
        	// Guardar en localStorage para persistencia
        	localStorage.setItem('image_admin_edit_{{ $administration->id }}', e.target.result);
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
    localStorage.removeItem('image_admin_edit_{{ $administration->id }}');
});

// Restaurar imagen si hay error de validación
document.addEventListener('DOMContentLoaded', function() {
    const savedImage = localStorage.getItem('image_admin_edit_{{ $administration->id }}');
    if (savedImage) {
        $('.photo-preview').css('background-image', 'url('+savedImage+')');
        $('.photo-preview i').hide();
    }
});

// Limpiar localStorage al enviar exitosamente
$('#form-edit').on('submit', function() {
    setTimeout(() => {
        localStorage.removeItem('image_admin_edit_{{ $administration->id }}');
    }, 1000);
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
const selectedCity = @json(old('city', $administration->city));
function fillCitiesByProvince(province, preselect = '') {
	if (!citySelect) return;
	const cities = provinceCityMap[province] || [];
	if (cityTs) {
		cityTs.clear(true);
		cityTs.clearOptions();
		cities.forEach((city) => cityTs.addOption({ value: city, text: city }));
		cityTs.refreshOptions(false);
		const targetValue = preselect || selectedCity || '';
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
		if ((preselect && preselect === city) || (!preselect && selectedCity === city)) {
			option.selected = true;
		}
		citySelect.appendChild(option);
	});
}
let provinceTs = null;
let cityTs = null;
if (provinceSelect && citySelect) {
	fillCitiesByProvince(provinceSelect.value, selectedCity || '');
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
		});
	}
}

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

	// Validar cuenta bancaria antes de enviar: vacía o exactamente 22 dígitos
	document.querySelector('#form-edit').addEventListener('submit', function(e) {
		const digits = accountInput.value.replace(/\s/g, '');
		if (digits.length > 0 && digits.length !== 22) {
			e.preventDefault();
			alert('La cuenta bancaria debe estar vacía o tener exactamente 22 dígitos.');
			return false;
		}
	});
	// Antes de enviar el formulario, remover espacios y guardar solo números
	document.querySelector('#form-edit').addEventListener('submit', function(e) {
		// Crear un campo hidden con el valor sin espacios (incluso si está vacío)
		const hiddenInput = document.createElement('input');
		hiddenInput.type = 'hidden';
		hiddenInput.name = 'account';
		hiddenInput.value = accountInput.value.replace(/\s/g, '');
		this.appendChild(hiddenInput);
	});
}

// Inicializar validación de documento español
document.addEventListener('DOMContentLoaded', function() {
    initSpanishDocumentValidation('admin-edit-nif-cif', {
        showMessage: true
    });
    
    // Inicializar validación de email
    initEmailValidation('admin-edit-email', {
        context: 'administration',
        excludeId: {{ $administration->id }},
        showMessage: true
    });
});

</script>

@endsection
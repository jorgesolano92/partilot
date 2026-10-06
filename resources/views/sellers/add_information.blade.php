@extends('layouts.layout')

@section('title','Vendedores/Asignación')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
            	<div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Vendedores/Asignación</a></li>
                        <li class="breadcrumb-item active">Añadir</li>
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

                    	Invitación Vendedor

                    </h4>

                    <br>

                    <div class="row">
                    	
                    	<div class="col-md-3" style="position: relative;">
                    		<div class="form-card bs mb-3">

                    			<div class="form-wizard-element">
                    				
                    				<span>
                    					1
                    				</span>

                    				<img src="{{url('assets/entidad.svg')}}" alt="">

                    				<label>
                    					Selec. Entidad
                    				</label>

                    			</div>

                    			<div class="form-wizard-element active">
                    				
                    				<span>
                    					2
                    				</span>

                    				<img src="{{url('icons_/vendedores.svg')}}" alt="">

                    				<label>
                    					Dat. Vendedor
                    				</label>

                    			</div>
                    			
                    		</div>

                    		<a href="{{ route('sellers.index') }}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9">
                    		<div class="form-card bs" style="min-height: 658px;">
                    			<div class="d-flex justify-content-between align-items-center">
                    				<div>
                    					<h4 class="mb-0 mt-1">
                    						Invitación / Registro
                    					</h4>
                    					<small><i>Elige la manera en la que agregar al Vendedor</i></small>
                    				</div>
                    				<div class="d-none" id="back-to-buttons">
                    					<button class="btn btn-sm btn-light" id="back-button" style="border-radius: 50%; width: 40px; height: 40px; padding: 0;">
                    						<i class="ri-arrow-left-line"></i>
                    					</button>
                    				</div>
                    			</div>

                    			@if($errors->any())
                    			<div class="show-alerts mt-2">
                    				<div class="alert alert-danger mb-0" role="alert">
                    					@foreach($errors->all() as $error)
                    						<div>{{ $error }}</div>
                    					@endforeach
                    				</div>
                    			</div>
                    			@endif

                    			<div class="form-group mt-2 mb-3 admin-box">

                    				<div class="row">
                    					<div class="col-1">
                    						
		                    				<div class="photo-preview-3">
		                    					
		                    					<i class="ri-account-circle-fill"></i>

		                    				</div>
		                    				
		                    				<div style="clear: both;"></div>
                    					</div>

                    					<div class="col-4 text-center mt-3">

                    						<h4 class="mt-0 mb-0">{{ session('selected_entity')->name ?? 'Entidad' }}</h4>

                    						<small>{{ session('selected_entity')->province ?? 'Provincia' }}</small> <br>

                    						<small>{{ session('selected_entity')->administration->name ?? 'Administración' }}</small>

                    					</div>

                    					<div class="col-3">

                    						<div class="mt-3">
                    							Provincia: {{ session('selected_entity')->province ?? 'N/A' }} <br>
                    							Dirección: {{ session('selected_entity')->address ?? 'N/A' }}
                    						</div>
                    						
                    					</div>

                    					<div class="col-3">

                    						<div class="mt-3">
                    							Ciudad: {{ session('selected_entity')->city ?? 'N/A' }} <br>
                    							Tel: {{ session('selected_entity')->phone ?? 'N/A' }}
                    						</div>
                    						
                    					</div>

                    				</div>

                    			</div>

                    			<br>

                    			<div id="all-options">
                    				<div class="row">
                    					
                    					<div class="col-12">
                    						
                    						<div class="mt-4 text-center">

                    							<div class="" id="manager-buttons">

	                    							<button class="btn btn-light btn-xl text-center m-2 bs" id="invite-manager" style="border: 1px solid #f0f0f0; padding: 16px; width: 150px; border-radius: 16px;">
	                    								<img class="mt-2 mb-1" src="{{url('assets/vendedor.svg')}}" alt="" width="60%">
	                    								<h4 class="mb-0">Vendedor <br> PARTILOT</h4>
	                    							</button>

	                    							<button class="btn btn-light btn-xl text-center m-2 bs" id="register-manager" style="border: 1px solid #f0f0f0; padding: 16px; width: 150px; border-radius: 16px; position: relative;">
	                    								<img class="mt-2 mb-1" src="{{url('assets/vendedor.svg')}}" alt="" width="60%">
	                    								<img class="mt-2 mb-1" src="{{url('assets/deni.svg')}}" alt="" width="35%" style="position: absolute; margin: auto; left: 0; right: 0; top: 8px;">
	                    								<h4 class="mb-0">Vendedor <br> EXTERNO</h4>
	                    							</button>

                    							</div>

                    							<div class="d-none" id="invite-form">

                    								<div class="row">
                    									
                    									<div class="col-4 offset-4">
		                    								<div class="card bs" style="border-radius: 16px;">
		                    									
		                    									<div class="card-body">

		                    										<h4 class="mt-0"><b>¡Invitar Usuario!</b></h4>

		                    										<br>

		                    										<div class="input-group input-group-merge group-form" style="border: none;">

									                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
									                                        <img src="{{url('assets/form-groups/admin/9.svg')}}" alt="">
									                                    </div>

									                                    <input class="form-control invite-email" type="email" placeholder="ejemplo@cuentaemail.com" style="border-radius: 0 30px 30px 0;">
									                                </div>

									                                <button disabled style="border-radius: 30px; width: 100%; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-3" id="invite-button">Invitar</button>
		                    										
		                    									</div>

		                    								</div>
                    									</div>
                    								</div>



                    							</div>

                    							<div class="d-none" id="accept-invite">

                    								<div style="width: 400px; margin: auto;">

                    									<div class="d-none" id="no-coincidence">
		                    								<h2>¡Hay 0 coincidencias!</h2>

		                    								<p>
		                    									No hemos encontrado un <b>usuario registrado con el email "<span id="email-placeholder"></span>"</b>. Si haces clic en <b>Aceptar</b>, se le enviará una invitación para <b>unirse a tu entidad una vez se registre.</b>
		                    								</p>
                    									</div>

                    									<div class="d-none" id="coincidence">
		                    								<h2>¡Hay 1 coincidencia!</h2>

		                    								<p>
		                    									Hemos encontrado un <b>usuario registrado con el email "<span id="email-placeholder2"></span>"</b>. Si haces clic en <b>Aceptar</b>, se le enviará una invitación para <b>unirse a tu entidad.</b>
		                    								</p>
                    									</div>

                    									<div class="d-none" id="previously-rejected">
		                    								<h2>Rechazó una invitación anterior</h2>

		                    								<p>
		                    									El usuario con email "<b><span class="invite-email-placeholder"></span></b>" rechazó la última invitación de tu entidad. Si haces clic en <b>Aceptar</b>, se le volverá a enviar.
		                    								</p>
                    									</div>

                    									<div class="d-none" id="already-linked">
		                    								<h2>Ya es vendedor de tu entidad</h2>

		                    								<p>
		                    									El email "<b><span class="invite-email-placeholder"></span></b>" ya está vinculado a tu entidad (estado: <b id="already-linked-status"></b>). No hace falta volver a invitarle.
		                    								</p>
		                    								<a href="#" id="already-linked-link" class="btn btn-sm btn-outline-dark" style="border-radius: 20px;">Ver ficha del vendedor</a>
                    									</div>

                    									<div class="d-none" id="check-email-error">
		                    								<h2>No se pudo comprobar el email</h2>

		                    								<p id="check-email-error-text"></p>
                    									</div>

                    									<div class="text-muted d-none" id="check-email-loading">
                    										<p>Comprobando email…</p>
                    									</div>

	                    								<div class="row">
	                    									<div class="col-6">
	                    										<button style="border-radius: 30px; width: 100%; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-3" id="cancel-invite">Cancelar</button>
	                    									</div>

	                    									<div class="col-6" id="invite-accept-col">
	                    										<form action="{{ route('sellers.store-existing-user') }}" method="POST" id="invite-accept-form">
                    											@csrf
                    											<input type="hidden" name="invite_flow" value="1">
                    											<input type="hidden" name="email" id="invite-email-hidden">
                    											<input type="hidden" name="entity_id" value="{{ session('selected_entity')->id }}">
                    											<button type="submit" id="invite-accept-submit" style="border-radius: 30px; width: 100%; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-3">Aceptar</button>
                    										</form>
	                    									</div>
	                    								</div>
                    								</div>
                    							</div>
                    							
                    						</div>



                    					</div>

                    				</div>
                    			</div>

                    			<div id="register-manager-selected" class="d-none">

                    				<form action="{{ route('sellers.store-new-user') }}" method="POST" id="register-form">
                    					@csrf
                    					<input type="hidden" name="entity_id" value="{{ session('selected_entity')->id }}">
                    				<div style="min-height: 340px;">
                    					
                    				<div class="row">
                    					
                    					<div class="col-4">
                    						<div class="form-group mt-2 mb-3">
                    							<label class="label-control">Nombre</label>

				                    			<div class="input-group input-group-merge group-form">

				                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
				                                        <img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
				                                    </div>

				                                    <input class="form-control" type="text" name="name" value="{{ old('name') }}" placeholder="Nombre" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input class="form-control" type="text" name="last_name" value="{{ old('last_name') }}" placeholder="Primer Apellido" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input class="form-control" type="text" name="last_name2" value="{{ old('last_name2') }}" placeholder="Segundo Apellido" style="border-radius: 0 30px 30px 0;">
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

				                                    <input class="form-control" type="text" name="nif_cif" id="seller-nif-cif" value="{{ old('nif_cif') }}" placeholder="B26262626" style="border-radius: 0 30px 30px 0;">
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

				                                    <input class="form-control" type="date" name="birthday" value="{{ old('birthday') }}" min="1900-01-01" max="{{ now()->toDateString() }}" placeholder="01/01/1990" style="border-radius: 0 30px 30px 0;">
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

				                                    <input class="form-control" type="email" id="seller-email" name="email" value="{{ old('email') }}" placeholder="ejemplo@cuentaemail.com" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input class="form-control" type="phone" name="phone" value="{{ old('phone') }}" placeholder="940 200 200" style="border-radius: 0 30px 30px 0;">
				                                </div>
			                    			</div>
                    					</div>

                    				</div>

                    				</div>

                    				<div class="row">

                    					<div class="col-8">
             								
     									</div>

	                    				<div class="col-4 text-end">
	                    						<button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative; top: calc(100% - 51px);" class="btn btn-md btn-light mt-2">Guardar
	                    							<i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i></button>
	                    				</div>

	                    			</div>
	                    				</form>
                    				
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

$('#invite-manager').click(function (e) {
	e.preventDefault();

	$('#manager-buttons').addClass('d-none');
	$('#back-to-buttons').removeClass('d-none');

	$('#invite-form').removeClass('d-none');
});

function syncInviteButton() {
	var value = ($('.invite-email').val() || '').trim();
	$('#invite-button').prop('disabled', value === '');
}

// input/paste/change cubren pegado con ratón y autocompletado del navegador, no solo teclado.
$('.invite-email').on('input keyup change paste blur', function () {
	window.setTimeout(syncInviteButton, 0);
});
syncInviteButton();

var inviteCheckRequest = null;

function showInviteResult(state) {
	$('#no-coincidence, #coincidence, #previously-rejected, #already-linked, #check-email-error, #check-email-loading').addClass('d-none');
	if (state) {
		$('#' + state).removeClass('d-none');
	}
	var canAccept = state === 'no-coincidence' || state === 'coincidence' || state === 'previously-rejected';
	$('#invite-accept-col').toggleClass('d-none', !canAccept);
	$('#invite-accept-submit').prop('disabled', !canAccept);
}

$('#invite-button').click(function (e) {
	e.preventDefault();

	var email = ($('.invite-email').val() || '').trim();
	if (email === '') {
		return;
	}

	var emailInput = $('.invite-email').get(0);
	if (emailInput && typeof emailInput.checkValidity === 'function' && !emailInput.checkValidity()) {
		emailInput.reportValidity();
		return;
	}

	$('#invite-form').addClass('d-none');
	$('#accept-invite').removeClass('d-none');

	$('#email-placeholder').text(email);
	$('#email-placeholder2').text(email);
	$('.invite-email-placeholder').text(email);
	$('#invite-email-hidden').val(email);

	showInviteResult('check-email-loading');

	if (inviteCheckRequest) {
		inviteCheckRequest.abort();
	}

	inviteCheckRequest = $.ajax({
		url: '{{ route("sellers.check-user-email") }}',
		method: 'POST',
		data: {
			email: email,
			entity_id: {{ (int) (session('selected_entity')->id ?? 0) }},
			_token: $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}'
		},
		success: function(response) {
			if (($('#invite-email-hidden').val() || '') !== email) {
				return;
			}
			if (response.already_linked) {
				$('#already-linked-status').text(response.seller_status_text || 'vinculado');
				$('#already-linked-link').attr('href', response.seller_url || '{{ route("sellers.index") }}');
				showInviteResult('already-linked');
			} else if (response.previously_rejected) {
				showInviteResult('previously-rejected');
			} else if (response.exists) {
				showInviteResult('coincidence');
			} else {
				showInviteResult('no-coincidence');
			}
		},
		error: function(xhr, status) {
			if (status === 'abort') {
				return;
			}
			var text = 'Ha ocurrido un error al comprobar el email. Inténtalo de nuevo.';
			if (xhr.status === 419) {
				text = 'Tu sesión ha caducado por inactividad. Recarga la página (F5) y vuelve a intentarlo.';
			} else if (xhr.status === 422) {
				text = 'El email introducido no es válido.';
			}
			$('#check-email-error-text').text(text);
			showInviteResult('check-email-error');
		},
		complete: function () {
			inviteCheckRequest = null;
		}
	});
});

$('#invite-accept-form').on('submit', function () {
	$('#invite-accept-submit').prop('disabled', true).text('Enviando…');
});

$('#cancel-invite').click(function (e) {
	e.preventDefault();

	$('#accept-invite').addClass('d-none');
	
	$('#invite-form').removeClass('d-none');

});

$('#register-manager').click(function (e) {
	e.preventDefault();

	$('#all-options').addClass('d-none');
	$('#back-to-buttons').removeClass('d-none');

	$('#register-manager-selected').removeClass('d-none');

});

$('#back-button').click(function (e) {
	e.preventDefault();

	// Ocultar todos los formularios
	$('#invite-form').addClass('d-none');
	$('#accept-invite').addClass('d-none');
	$('#register-manager-selected').addClass('d-none');
	$('#back-to-buttons').addClass('d-none');

	// Mostrar los botones de selección
	$('#all-options').removeClass('d-none');
	$('#manager-buttons').removeClass('d-none');

	// Limpiar campos
	$('.invite-email').val('');
	$('#invite-button').prop('disabled', true);
});

// Inicializar validación de documento español
document.addEventListener('DOMContentLoaded', function() {
    initSpanishDocumentValidation('seller-nif-cif', {
        showMessage: true
    });
    
    // Inicializar validación de email
    initEmailValidation('seller-email', {
        context: 'seller',
        showMessage: true
    });
    
    // Si hay errores de validación y hay datos old() que indiquen que se intentó crear un vendedor externo
    @if($errors->any() && old('entity_id') && old('invite_flow'))
        $('#manager-buttons').addClass('d-none');
        $('#back-to-buttons').removeClass('d-none');
        $('#invite-form').removeClass('d-none');
        $('.invite-email').val(@json(old('email', '')));
        syncInviteButton();
    @elseif($errors->any() && old('entity_id'))
        // Verificar si hay datos del formulario de registro (vendedor externo)
        // Si hay name, last_name, email, etc. significa que se intentó crear un vendedor externo
        @if(old('name') || old('last_name') || old('email') || old('nif_cif') || old('birthday') || old('phone'))
            // Mostrar el formulario de vendedor externo
            $('#all-options').addClass('d-none');
            $('#back-to-buttons').removeClass('d-none');
            $('#register-manager-selected').removeClass('d-none');
        @endif
    @endif
});

</script>

@endsection
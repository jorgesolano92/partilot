@extends('layouts.layout')

@section('title','Entidades')

@section('content')

<style>
	
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

                    			<div class="form-wizard-element">
                    				
                    				<span>
                    					2
                    				</span>

                    				<img src="{{url('assets/entidad.svg')}}" alt="">

                    				<label>
                    					Datos Entidad
                    				</label>

                    			</div>

                    			<div class="form-wizard-element active">
                    				
                    				<span>
                    					3
                    				</span>

                    				<img src="{{url('assets/gestor.svg')}}" alt="">

                    				<label>
                    					Datos Gestor
                    				</label>

                    			</div>
                    			
                    		</div>

                    		<div class="form-card">
                    			
                    			<div class="row">
                					<div class="col-4">
                						@php $entityImg = data_get(session('entity_information'), 'image'); @endphp
	                    				<div class="photo-preview-3" @if($entityImg) style="background-image: url('{{ asset('uploads/' . $entityImg) }}'); background-size: cover; background-position: center;" @endif>
	                    					@if(!$entityImg)
	                    						<i class="ri-account-circle-fill"></i>
	                    					@endif
	                    				</div>
	                    				
	                    				<div style="clear: both;"></div>
                					</div>

                					<div class="col-8 text-center mt-2">

                						<h4 class="mt-0 mb-0">{{session('selected_administration')->name ?? 'Administración'}}</h4>

                						<small>{{session('entity_information.name') ?? 'Entidad'}}</small> <br>

                						<i style="position: relative; top: 3px; font-size: 16px; color: #333" class="ri-computer-line"></i> {{session('entity_information.province') ?? 'Provincia'}}
                						
                					</div>
                				</div>

                    		</div>

                    		<a href="{{route('entities.add-information')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9">
                    		<div class="form-card bs" style="min-height: 658px;">
                    			<h4 class="mb-0 mt-1">
                    				Email de invitación
                    			</h4>
                    			<small><i>Asegúrese de que el email sea el correcto</i></small>
                    			<p class="text-muted small mt-2 mb-0">El <strong>gestor responsable</strong> recibirá un correo para <strong>aceptar o rechazar</strong> el cargo. Si es usuario nuevo (registrar gestor), el correo incluirá también email y contraseña provisional. La cuenta de panel de la entidad recibe su contraseña provisional por correo por separado.</p>

                    			<div class="form-group mt-2 mb-3 admin-box">

                    				<div class="row">
                    					<div class="col-1">
		                    				<div class="photo-preview-3" @if($entityImg) style="background-image: url('{{ asset('uploads/' . $entityImg) }}'); background-size: cover; background-position: center;" @endif>
		                    					@if(!$entityImg)
		                    						<i class="ri-account-circle-fill"></i>
		                    					@endif
		                    				</div>
		                    				
		                    				<div style="clear: both;"></div>
                    					</div>

                    					<div class="col-4 text-center mt-3">

                    						<h4 class="mt-0 mb-0">{{session('entity_information.name') ?? 'Entidad'}}</h4>

                    						<small>{{session('entity_information.province') ?? 'Provincia'}}</small> <br>
                    						
                    					</div>

                    					<div class="col-4">

                    						<div class="mt-3">
                    							Provincia: {{session('entity_information.province') ?? 'Provincia'}} <br>
                    							Dirección: {{session('entity_information.address') ?? 'Dirección'}}
                    						</div>
                    						
                    					</div>

                    					<div class="col-3">

                    						<div class="mt-3">
                    							Ciudad: {{session('entity_information.city') ?? 'Ciudad'}} <br>
                    							Tel: {{session('entity_information.phone') ?? 'Teléfono'}}
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
	                    								<img class="mt-2" src="{{url('assets/invite.svg')}}" alt="">
	                    								<h4 class="mb-0">Invitar <br> GESTOR</h4>
	                    							</button>

	                    							<button class="btn btn-light btn-xl text-center m-2 bs" id="register-manager" style="border: 1px solid #f0f0f0; padding: 16px; width: 150px; border-radius: 16px;">
	                    								<img class="mt-2" src="{{url('assets/register.svg')}}" alt="">
	                    								<h4 class="mb-0">Registrar <br> GESTOR</h4>
	                    							</button>

                    							</div>

                    							<div class="d-none" id="invite-form">

                    								<div class="row">
                    									
                    									<div class="col-7">
		                    								<div class="card bs" style="border-radius: 16px;">
		                    									<div class="card-body">
		                    										<h4 class="mb-0 mt-1">
									                    				Permisos Gestor
									                    			</h4>
									                    			<small><i>Puedes modificar los permisos en cualquier momento</i></small>

									                    			<br>

									                    			<div style="width: 50%; margin: auto;" class="text-start">

									                    			<div class="form-check form-switch mt-2 mb-2">
																		<input class="form-check-input bg-dark" style="float: right;" type="checkbox" role="switch" id="all" checked disabled>
																		<label class="form-check-label" style="float: right; margin-right: 50px; width: 100%; padding-left: 16px;" for="all"><b>
																			Todos los permisos
																		</b></label>
																	</div>

																	</div>

																	<hr>

																	<div style="width: 50%; margin: auto;" class="text-start">

																	<div class="form-check form-switch mt-2 mb-2">
																		<input class="form-check-input bg-dark" style="float: right;" type="checkbox" role="switch" id="sellers" checked disabled>
																		<label class="form-check-label" style="float: right; margin-right: 50px; width: 100%; padding-left: 16px;" for="sellers"><b>
																			Administrar Vendedores
																		</b></label>
																	</div>

																	<div class="form-check form-switch mt-2 mb-2">
																		<input class="form-check-input bg-dark" style="float: right;" type="checkbox" role="switch" id="design" checked disabled>
																		<label class="form-check-label" style="float: right; margin-right: 50px; width: 100%; padding-left: 16px;" for="design"><b>
																			Diseñar Participaciones
																		</b></label>
																	</div>

																	<div class="form-check form-switch mt-2 mb-2">
																		<input class="form-check-input bg-dark" style="float: right;" type="checkbox" role="switch" id="total" checked disabled>
																		<label class="form-check-label" style="float: right; margin-right: 50px; width: 100%; padding-left: 16px;" for="total"><b>
																			Estadísticas Totales
																		</b></label>
																	</div>

																	<div class="form-check form-switch mt-2 mb-2">
																		<input class="form-check-input bg-dark" style="float: right;" type="checkbox" role="switch" id="pay" checked disabled>
																		<label class="form-check-label" style="float: right; margin-right: 50px; width: 100%; padding-left: 16px;" for="pay"><b>
																			Pagar Participaciones
																		</b></label>
																	</div>

																	</div>

		                    									</div>
		                    								</div>
                    									</div>
                    									<div class="col-5">
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



                    								<div class="row mt-3">
                    									<div class="col-12 text-end">
                    										<button type="button" class="btn btn-md btn-light mt-2 return-manager-options" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: relative;">
                    											<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i>
                    											<span style="display: block; margin-left: 16px;">Volver</span>
                    										</button>
                    									</div>
                    								</div>

                    							</div>

                    							<div class="d-none" id="accept-invite">

                    								<div style="width: 400px; margin: auto;">

                    									<div class="d-none" id="no-coincidence">
		                    								<h2>¡Hay 0 coincidencias!</h2>

		                    								<p>
		                    									No hemos encontrado un <b>usuario registrado con el email "<span id="no-coincidence-email"></span>"</b>. Si haces clic en <b>Aceptar</b>, se guardará la invitación: al <b>registrarse con ese mismo email</b> (web o app) recibirá el correo para aceptar y <b>definir su contraseña</b> de acceso al panel.
		                    								</p>
                    									</div>

                    									<div class="d-none" id="coincidence">
		                    								<h2>¡Hay 1 coincidencia!</h2>

		                    								<p>
		                    									Hemos encontrado un <b>usuario registrado con el email "<span id="coincidence-email"></span>"</b>. Si haces clic en <b>Aceptar</b>, se le enviará una invitación para unirse a la entidad y <b>definir su contraseña</b> al aceptar.
		                    								</p>
                    									</div>

	                    								<div class="row">
	                    									<div class="col-6">
	                    										<button style="border-radius: 30px; width: 100%; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-3" id="cancel-invite">Cancelar</button>
	                    									</div>

	                    									<div class="col-6">
	                    										<button style="border-radius: 30px; width: 100%; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-3" id="accept-invite-btn">Aceptar</button>
	                    									</div>
	                    								</div>
                    								</div>
                    							</div>
                    							
                    						</div>

                    						<!-- Formularios ocultos para manejar las invitaciones -->
                    						<form id="invite-manager-form" action="{{url('entities/invite-manager')}}" method="POST" style="display: none;">
                    							@csrf()
                    							<input type="hidden" name="entity_id" id="invite-entity-id-input" value="{{ data_get(session('entity_information'), 'id', '') }}">
                    							<input type="hidden" name="user_id" id="user-id-input">
                    							<input type="hidden" name="invite_email" id="invite-email-input">
                    							<input type="hidden" name="permission_sellers" id="invite-permission-sellers" value="1">
                    							<input type="hidden" name="permission_design" id="invite-permission-design" value="1">
                    							<input type="hidden" name="permission_statistics" id="invite-permission-statistics" value="1">
                    							<input type="hidden" name="permission_payments" id="invite-permission-payments" value="1">
                    						</form>

                    						<form id="create-pending-entity-form" action="{{url('entities/create-pending-entity')}}" method="POST" style="display: none;">
                    							@csrf()
                    							<input type="hidden" name="invite_email" id="pending-invite-email-input">
                    						</form>

                    					</div>

                    				</div>
                    			</div>

                    			<div id="register-manager-selected" class="d-none">
                    				<form action="{{url('entities/store-manager')}}" method="POST" enctype="multipart/form-data">
                    					@csrf()
                    					<div class="row">
                    						
                    						<div class="col-4">
                    							<div class="form-group mt-2 mb-3">
                    								<label class="label-control">Nombre</label>

				                    			<div class="input-group input-group-merge group-form">

				                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
				                                        <img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
				                                    </div>

				                                    <input class="form-control" type="text" name="manager_name" value="{{ old('manager_name', session('entity_manager.manager_name', '')) }}" placeholder="Nombre" style="border-radius: 0 30px 30px 0;">
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

				                                    <input class="form-control" type="text" name="manager_last_name" value="{{ old('manager_last_name', session('entity_manager.manager_last_name', '')) }}" placeholder="Primer Apellido" style="border-radius: 0 30px 30px 0;">
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

				                                    <input class="form-control" type="text" name="manager_last_name2" value="{{ old('manager_last_name2', session('entity_manager.manager_last_name2', '')) }}" placeholder="Segundo Apellido" style="border-radius: 0 30px 30px 0;">
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

				                                    <input class="form-control" type="text" name="manager_nif_cif" id="entity-manager-nif-cif" value="{{ old('manager_nif_cif', session('entity_manager.manager_nif_cif', '')) }}" placeholder="B26262626" style="border-radius: 0 30px 30px 0;">
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

				                                    <input class="form-control" type="date" name="manager_birthday" value="{{ old('manager_birthday', session('entity_manager.manager_birthday', '')) }}" min="1900-01-01" max="{{ now()->toDateString() }}" placeholder="01/01/1990" required style="border-radius: 0 30px 30px 0;">
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

				                                    <input class="form-control" type="email" name="manager_email" value="{{ old('manager_email', session('entity_manager.manager_email', '')) }}" placeholder="ejemplo@cuentaemail.com" style="border-radius: 0 30px 30px 0;">
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

				                                    <input class="form-control" type="phone" name="manager_phone" value="{{ old('manager_phone', session('entity_manager.manager_phone', '')) }}" placeholder="940 200 200" style="border-radius: 0 30px 30px 0;">
				                                </div>
			                    			</div>
                    						</div>

                    					</div>

                    					<div class="row">

                    						<div class="col-8">
            								<div class="card bs m-0" style="border-radius: 16px;">
            									<div class="card-body">
            										<h4 class="mb-0 mt-1">
					                    				Permisos Gestor
					                    			</h4>
					                    			<small><i>Este gestor es el principal y tiene todos los permisos</i></small>

					                    			<br>

					                    			<div class="row">
					                    				
						                    			<div class="text-start col-6">

							                    			<div class="form-check form-switch mt-2 mb-2">
																<input class="form-check-input bg-dark" style="float: right;" type="checkbox" role="switch" id="all" checked disabled>
																<label class="form-check-label" style="float: right; margin-right: 50px; width: 100%; padding-left: 16px;" for="all"><b>
																	Todos los permisos
																</b></label>
															</div>

														</div>
					                    			</div>

													<hr>

													<div class="row">
														
														<div class="text-start col-6">

															<div class="form-check form-switch mt-2 mb-2">
																<input class="form-check-input bg-dark" style="float: right;" type="checkbox" role="switch" id="sellers" checked disabled>
																<label class="form-check-label" style="float: right; margin-right: 50px; width: 100%; padding-left: 16px;" for="sellers"><b>
																	Administrar Vendedores
																</b></label>
															</div>

															<div class="form-check form-switch mt-2 mb-2">
																<input class="form-check-input bg-dark" style="float: right;" type="checkbox" role="switch" id="design" checked disabled>
																<label class="form-check-label" style="float: right; margin-right: 50px; width: 100%; padding-left: 16px;" for="design"><b>
																	Diseñar Participaciones
																</b></label>
															</div>

														</div>

														<div class="text-start col-6">

															<div class="form-check form-switch mt-2 mb-2">
																<input class="form-check-input bg-dark" style="float: right;" type="checkbox" role="switch" id="total" checked disabled>
																<label class="form-check-label" style="float: right; margin-right: 50px; width: 100%; padding-left: 16px;" for="total"><b>
																	Estadísticas Totales
																</b></label>
															</div>

															<div class="form-check form-switch mt-2 mb-2">
																<input class="form-check-input bg-dark" style="float: right;" type="checkbox" role="switch" id="pay" checked disabled>
																<label class="form-check-label" style="float: right; margin-right: 50px; width: 100%; padding-left: 16px;" for="pay"><b>
																	Pagar Participaciones
																</b></label>
															</div>
														</div>

													</div>

            									</div>
            								</div>
    									</div>

	                    				<div class="col-8 text-end">
	                    					<button type="button" class="btn btn-md btn-light mt-2 return-manager-options" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: relative; margin-right: 12px;">
	                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i>
	                    						<span style="display: block; margin-left: 16px;">Volver</span>
	                    					</button>
	                    					<button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">Guardar
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
const ENTITY_MANAGER_FLOW_KEY = '{{ md5((string) (session('selected_administration')->id ?? '')) . ':' . (string) (data_get(session('entity_information'), 'email', '')) }}';
const ENTITY_MANAGER_STORAGE_KEY = 'entity_manager_form_data_' + ENTITY_MANAGER_FLOW_KEY;
const ENTITY_MANAGER_MODE_KEY = 'entity_manager_mode_' + ENTITY_MANAGER_FLOW_KEY;

function saveEntityManagerData() {
	const data = {
		manager_name: document.querySelector('input[name="manager_name"]')?.value || '',
		manager_last_name: document.querySelector('input[name="manager_last_name"]')?.value || '',
		manager_last_name2: document.querySelector('input[name="manager_last_name2"]')?.value || '',
		manager_nif_cif: document.querySelector('input[name="manager_nif_cif"]')?.value || '',
		manager_birthday: document.querySelector('input[name="manager_birthday"]')?.value || '',
		manager_email: document.querySelector('input[name="manager_email"]')?.value || '',
		manager_phone: document.querySelector('input[name="manager_phone"]')?.value || '',
	};
	localStorage.setItem(ENTITY_MANAGER_STORAGE_KEY, JSON.stringify(data));
}

function loadEntityManagerData() {
	const hasOldInput = {{ (is_array(old()) && count(old()) > 0) ? 'true' : 'false' }};
	if (hasOldInput) return;

	const raw = localStorage.getItem(ENTITY_MANAGER_STORAGE_KEY);
	if (!raw) return;

	try {
		const data = JSON.parse(raw);
		Object.keys(data).forEach((key) => {
			const input = document.querySelector(`input[name="${key}"]`);
			if (input && data[key]) input.value = data[key];
		});
	} catch (e) {
		localStorage.removeItem(ENTITY_MANAGER_STORAGE_KEY);
	}
}

$('#invite-manager').click(function (e) {
	e.preventDefault();

	$('#manager-buttons').addClass('d-none');

	$('#invite-form').removeClass('d-none');
	localStorage.setItem(ENTITY_MANAGER_MODE_KEY, 'invite');
});

$('.invite-email').keyup(function(event) {
	
	if ($(this).val()) {
		$('#invite-button').prop('disabled',false);
	}else{
		$('#invite-button').prop('disabled',true);
	}
});

$('#invite-button').click(function (e) {
	e.preventDefault();

	var email = $('.invite-email').val();
	
	if (!email) {
		alert('Por favor, ingrese un email válido');
		return;
	}

	// Mostrar loading
	$('#invite-button').prop('disabled', true).text('Verificando...');

	// Hacer petición AJAX para verificar el email
	$.ajax({
		url: '{{url("entities/check-manager-email")}}',
		method: 'POST',
		data: {
			email: email,
			_token: '{{csrf_token()}}'
		},
		success: function(response) {
			$('#invite-form').addClass('d-none');
			$('#accept-invite').removeClass('d-none');

			if (response.exists) {
				// Hay coincidencia
				$('#coincidence').removeClass('d-none');
				$('#no-coincidence').addClass('d-none');
				$('#coincidence-email').text(email);
				
				// Guardar datos para el formulario
				                    $('#user-id-input').val(response.user_id);
				$('#invite-email-input').val(email);
			} else {
				// No hay coincidencia
				$('#coincidence').addClass('d-none');
				$('#no-coincidence').removeClass('d-none');
				$('#no-coincidence-email').text(email);
				
				// Guardar email para el formulario de entidad pendiente
				$('#pending-invite-email-input').val(email);
			}
		},
		error: function() {
			alert('Error al verificar el email. Por favor, intente nuevamente.');
		},
		complete: function() {
			$('#invite-button').prop('disabled', false).text('Invitar');
		}
	});
});

function syncInvitePermissions() {
	$('#invite-permission-sellers').val($('#sellers').is(':checked') ? '1' : '0');
	$('#invite-permission-design').val($('#design').is(':checked') ? '1' : '0');
	$('#invite-permission-statistics').val($('#total').is(':checked') ? '1' : '0');
	$('#invite-permission-payments').val($('#pay').is(':checked') ? '1' : '0');
}

$('#cancel-invite').click(function (e) {
	e.preventDefault();

	$('#accept-invite').addClass('d-none');
	
	$('#invite-form').removeClass('d-none');

});

function showEntityManagerOptions() {
	// Restablece la vista a la selección de Invitar/Registrar
	$('#all-options').removeClass('d-none');
	$('#manager-buttons').removeClass('d-none');
	$('#invite-form').addClass('d-none');
	$('#accept-invite').addClass('d-none');
	$('#register-manager-selected').addClass('d-none');

	// Evita que el botón de atrás/adelante vuelva a forzar el modo anterior
	localStorage.removeItem(ENTITY_MANAGER_MODE_KEY);
}

$(document).on('click', '.return-manager-options', function (e) {
	e.preventDefault();
	showEntityManagerOptions();
});

$('#register-manager').click(function (e) {
	e.preventDefault();

	$('#all-options').addClass('d-none');

	$('#register-manager-selected').removeClass('d-none');
	localStorage.setItem(ENTITY_MANAGER_MODE_KEY, 'register');

});

// Manejar el botón "Aceptar" para invitaciones
$('#accept-invite-btn').click(function (e) {
	e.preventDefault();
	syncInvitePermissions();
	
	// Determinar qué formulario enviar basado en si hay coincidencia o no
	if ($('#coincidence').is(':visible')) {
		// Hay coincidencia - enviar formulario de invitación
		$('#invite-manager-form').submit();
	} else {
		// No hay coincidencia - enviar formulario de entidad pendiente
		$('#create-pending-entity-form').submit();
	}
});

// Inicializar validación de documento español
document.addEventListener('DOMContentLoaded', function() {
    loadEntityManagerData();

    const savedMode = localStorage.getItem(ENTITY_MANAGER_MODE_KEY);
    if (savedMode === 'register') {
    	$('#all-options').addClass('d-none');
		$('#register-manager-selected').removeClass('d-none');
    } else if (savedMode === 'invite') {
    	$('#manager-buttons').addClass('d-none');
		$('#invite-form').removeClass('d-none');
    }

    document.querySelectorAll('input[name^="manager_"]').forEach(function(el) {
    	el.addEventListener('input', saveEntityManagerData);
    	el.addEventListener('change', saveEntityManagerData);
    });

    const backButton = document.querySelector('a[href*="entities/add/information"]');
    if (backButton) {
    	backButton.addEventListener('click', function() {
    		saveEntityManagerData();
    	});
    }

    const registerForm = document.querySelector('#register-manager-selected form');
    if (registerForm) {
    	registerForm.addEventListener('submit', function() {
    		localStorage.removeItem(ENTITY_MANAGER_STORAGE_KEY);
    		localStorage.removeItem(ENTITY_MANAGER_MODE_KEY);
    	});
    }

    initSpanishDocumentValidation('entity-manager-nif-cif', {
        showMessage: true
    });
});
	
</script>

@endsection

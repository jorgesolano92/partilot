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

                    	Datos Gestor

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
                    					Datos administración
                    				</label>

                    			</div>

                    			<div class="form-wizard-element active">
                    				
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

                                        <input class="form-control" type="text" id="web-field" value="{{ old('web', session('administration.web', '')) }}" placeholder="www.administracion.es" style="border-radius: 0 30px 30px 0;">
	                                </div>
                    			</div>
                    		</div>

                    		<a href="{{url('administrations/add')}}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9">
                    		<div class="form-card bs">
                    			<h4 class="mb-0 mt-1">
                    				Datos de contacto
                    			</h4>
                    			<small><i>Todos los campos son obligatorios</i></small>

                    			<div class="form-group mt-2 mb-3 admin-box">

                    				<div class="row">
                    					<div class="col-1">
                    						
		                    				<div class="photo-preview-2" @if(session('administration.image')) style="background-image: url('{{ url('images/' . session('administration.image')) }}'); background-size: cover; background-position: center;" @endif>
		                    					@if(!session('administration.image'))
		                    						<i class="ri-account-circle-fill"></i>
		                    					@endif
		                    				</div>
		                    				
		                    				<div style="clear: both;"></div>
                    					</div>

                    					<div class="col-4 text-center">

                    						<h4 class="mt-0 mb-0">{{ session('administration.name', 'Administración') }}</h4>

                    						<small>Gestor</small> <br>

                    						<i style="position: relative; top: 3px; font-size: 16px; color: #333" class="ri-computer-line"></i> {{ session('administration.receiving', '') }}
                    						
                    					</div>

                    					<div class="col-4">

                    						<div class="mt-2">
                    							Provincia: {{ session('administration.province', '') }} <br>
                    							Dirección: {{ session('administration.address', '') }}
                    						</div>
                    						
                    					</div>

                    					<div class="col-3">

                    						<div class="mt-2">
                    							Ciudad: {{ session('administration.city', '') }} <br>
                    							Tel: {{ session('administration.phone', '') }}
                    						</div>
                    						
                    					</div>
                    				</div>
                    			</div>

                    			
                    			<br>

                    			<form action="{{url('administrations/store')}}" method="POST" enctype="multipart/form-data">

                    				@csrf()

                    				<!-- Campo web oculto que se sincroniza con el campo visible -->
                    				<input type="hidden" name="web" id="web-hidden" value="{{ old('web', session('administration.web', '')) }}">

	                    			<div>

	                    				<div class="row">
	                    					
	                    					<div class="col-4">
	                    						<div class="form-group mt-2 mb-3">
	                    							<label class="label-control">Nombre</label>

					                    			<div class="input-group input-group-merge group-form">

					                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
					                                        <img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
					                                    </div>

					                                    <input class="form-control" type="text" name="name" value="{{ old('name', session('manager.name', '')) }}" placeholder="Nombre" style="border-radius: 0 30px 30px 0;">
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

					                                    <input class="form-control" type="text" name="last_name" value="{{ old('last_name', session('manager.last_name', '')) }}" placeholder="Primer Apellido" style="border-radius: 0 30px 30px 0;">
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

					                                    <input class="form-control" type="text" name="last_name2" value="{{ old('last_name2', session('manager.last_name2', '')) }}" placeholder="Segundo Apellido" style="border-radius: 0 30px 30px 0;">
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

					                                    <input class="form-control" type="text" name="nif_cif" id="admin-manager-nif-cif" value="{{ old('nif_cif', session('manager.nif_cif', '')) }}" placeholder="B26262626" style="border-radius: 0 30px 30px 0;">
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

					                                    <input class="form-control" type="date" name="birthday" value="{{ old('birthday', session('manager.birthday', '')) }}" min="1900-01-01" max="{{ now()->toDateString() }}" placeholder="01/01/1990" style="border-radius: 0 30px 30px 0;">
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

					                                    <input class="form-control" type="email" name="email" value="{{ old('email', session('manager.email', '')) }}" placeholder="ejemplo@cuentaemail.com" style="border-radius: 0 30px 30px 0;">
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

					                                    <input class="form-control" type="phone" name="phone" value="{{ old('phone', session('manager.phone', '')) }}" placeholder="940 200 200" style="border-radius: 0 30px 30px 0;">
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

				                    			<div class="input-group input-group-merge group-form">

				                                    <textarea class="form-control" name="comment" placeholder="Añade tu comentario" id="" rows="6">{{ old('comment', session('manager.comment', '')) }}</textarea>
				                                </div>
			                    			</div>

	                    				</div>

	                    				<div class="col-4 text-end">
	                    					<button style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative; top: calc(100% - 51px);" class="btn btn-md btn-light mt-2">Guardar
	                    						<i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i></button>
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

<script>
	// Si hay old() (error de validación), no sobrescribir con localStorage
	window.hasOldInputManager = {{ (is_array(old()) && count(old()) > 0) ? 'true' : 'false' }};

	// Sincronizar el campo web visible con el campo oculto del formulario
	document.getElementById('web-field').addEventListener('input', function() {
	    document.getElementById('web-hidden').value = this.value;
	});

	function saveManagerFormData() {
	    const managerData = {
	        name: document.querySelector('input[name="name"]')?.value || '',
	        last_name: document.querySelector('input[name="last_name"]')?.value || '',
	        last_name2: document.querySelector('input[name="last_name2"]')?.value || '',
	        nif_cif: document.querySelector('input[name="nif_cif"]')?.value || '',
	        birthday: document.querySelector('input[name="birthday"]')?.value || '',
	        email: document.querySelector('input[name="email"]')?.value || '',
	        phone: document.querySelector('input[name="phone"]')?.value || '',
	        comment: document.querySelector('textarea[name="comment"]')?.value || '',
	        web: document.getElementById('web-field')?.value || ''
	    };
	    localStorage.setItem('administration_manager_form_data', JSON.stringify(managerData));
	}

	function loadManagerFormData() {
	    const raw = localStorage.getItem('administration_manager_form_data');
	    if (!raw || window.hasOldInputManager) return;
	    try {
	        const managerData = JSON.parse(raw);
	        Object.keys(managerData).forEach((key) => {
	            if (key === 'comment') {
	                const textarea = document.querySelector('textarea[name="comment"]');
	                if (textarea && managerData[key]) textarea.value = managerData[key];
	                return;
	            }
	            if (key === 'web') {
	                const webField = document.getElementById('web-field');
	                const webHidden = document.getElementById('web-hidden');
	                if (webField && managerData[key]) webField.value = managerData[key];
	                if (webHidden && managerData[key]) webHidden.value = managerData[key];
	                return;
	            }
	            const input = document.querySelector(`input[name="${key}"]`);
	            if (input && managerData[key]) input.value = managerData[key];
	        });
	    } catch (e) {
	        localStorage.removeItem('administration_manager_form_data');
	    }
	}

	// Guardar mientras se escribe para soportar "Atrás" del wizard
	document.querySelectorAll('input, textarea').forEach(function(el) {
	    el.addEventListener('input', saveManagerFormData);
	    el.addEventListener('change', saveManagerFormData);
	});

	// Al pulsar el botón Atrás, guardar antes de navegar
	const backButton = document.querySelector('a[href$="administrations/add"]');
	if (backButton) {
	    backButton.addEventListener('click', function() {
	        saveManagerFormData();
	    });
	}

	// Limpiar datos al enviar correctamente
	document.querySelector('form').addEventListener('submit', function() {
	    localStorage.removeItem('administration_form_data');
	    localStorage.removeItem('administration_manager_form_data');
	});

	// Inicializar validación de documento español
	document.addEventListener('DOMContentLoaded', function() {
	    loadManagerFormData();
	    initSpanishDocumentValidation('admin-manager-nif-cif', {
	        showMessage: true
	    });
	});
</script>

@endsection

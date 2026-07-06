@extends('layouts.layout')

@section('title','Usuarios')

@section('content')

<!-- Start Content-->
<div class="container-fluid">
    
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
            	<div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Usuarios</a></li>
                        <li class="breadcrumb-item active">Añadir</li>
                    </ol>
                </div>
                <h4 class="page-title">Usuarios</h4>
            </div>
        </div>
    </div>     

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

            		<h4 class="header-title">

                    	Datos Usuario

                    </h4>

                    <br>

                    <div class="row">
                    	
                    	<div class="col-md-3" style="position: relative;">
                    		<div class="form-card bs mb-3">

                    			<div class="form-wizard-element active">
                    				
                    				<span>
                    					1
                    				</span>

                    				<img src="{{url('icons_/usuarios.svg')}}" alt="">

                    				<label>
                    					Datos Usuario
                    				</label>

                    			</div>
                    			
                    		</div>

                    		<a href="{{ route('users.index') }}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    						<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span></a>
                    	</div>
                    	<div class="col-md-9">
                    		<div class="form-card bs" style="min-height: 658px;">
                    			<h4 class="mb-0 mt-1">
                    				Datos de Identificación
                    			</h4>
                    			<small><i>Todos los campos son obligatorios</i></small>

                    			<br>
                    			<br>

                    			<form id="user-form" action="{{ route('users.store') }}" method="POST" enctype="multipart/form-data">
                    				@csrf

                    				<!-- Imagen del Usuario -->
                    				<div class="form-group mt-2 mb-3">
                    					<div class="photo-preview" id="user-avatar">
                    						<i class="ri-image-add-line"></i>
                    					</div>
                    					<div>
                    						<small><i>Imagen Usuario</i></small>
                    						<br>
                    						<b>Avatar</b>
                    						<br>
                    						<label style="border-radius: 30px; width: 150px; background-color: #333;" class="btn btn-md btn-dark mt-2" onclick="document.getElementById('user-image').click()">
                    							<small>Subir Imagen</small>
                    						</label>
                    						<label style="border-radius: 30px; width: 150px; background-color: transparent; color: #333;" class="btn btn-md btn-dark mt-2" onclick="removeImage()">
                    							<small>Eliminar Imagen</small>
                    						</label>
                    						<input type="file" id="user-image" name="image" style="display: none;" accept="image/*" onchange="previewImage(this)">
                    					</div>
                    					<div style="clear: both;"></div>
                    				</div>

                    				<br>

                    				<div>
                    						<div class="row">
                    							<div class="col-4">
                    								<div class="form-group mt-2 mb-3">
                    									<label class="label-control">Nombre *</label>
                    									<div class="input-group input-group-merge group-form">
                    										<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                    											<img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
                    										</div>
                    										<input class="form-control" type="text" id="name" name="name" 
                    										       value="{{ old('name') }}" placeholder="Nombre" style="border-radius: 0 30px 30px 0;" required>
                    									</div>
                    									@error('name')
                    										<div class="text-danger">{{ $message }}</div>
                    									@enderror
                    								</div>
                    							</div>
                    							<div class="col-4">
                    								<div class="form-group mt-2 mb-3">
                    									<label class="label-control">Primer Apellido *</label>
                    									<div class="input-group input-group-merge group-form">
                    										<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                    											<img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
                    										</div>
                    										<input class="form-control" type="text" id="last_name" name="last_name" 
                    										       value="{{ old('last_name') }}" placeholder="Apellido" style="border-radius: 0 30px 30px 0;" required>
                    									</div>
                    									@error('last_name')
                    										<div class="text-danger">{{ $message }}</div>
                    									@enderror
                    								</div>
                    							</div>
                    							<div class="col-4">
                    								<div class="form-group mt-2 mb-3">
                    									<label class="label-control">Segundo Apellido</label>
                    									<div class="input-group input-group-merge group-form">
                    										<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                    											<img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
                    										</div>
                    										<input class="form-control" type="text" id="last_name2" name="last_name2" 
                    										       value="{{ old('last_name2') }}" placeholder="Apellido" style="border-radius: 0 30px 30px 0;">
                    									</div>
                    									@error('last_name2')
                    										<div class="text-danger">{{ $message }}</div>
                    									@enderror
                    								</div>
                    							</div>
                    						</div>
                    						<div class="row">
                    							<div class="col-2">
                    								<div class="form-group mt-2 mb-3">
                    									<label class="label-control">NIF/CIF *</label>
                    									<div class="input-group input-group-merge group-form">
                    										<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                    											<img src="{{url('assets/form-groups/admin/4.svg')}}" alt="">
                    										</div>
                    										<input class="form-control" type="text" id="nif_cif" name="nif_cif" 
                    										       value="{{ old('nif_cif') }}" placeholder="B26262626" style="border-radius: 0 30px 30px 0;" required>
                    									</div>
                    									@error('nif_cif')
                    										<div class="text-danger">{{ $message }}</div>
                    									@enderror
                    								</div>
                    							</div>
                    							<div class="col-3">
                    								<div class="form-group mt-2 mb-3">
                    									<label class="label-control">F. Nacimiento *</label>
                    									<div class="input-group input-group-merge group-form">
                    										<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                    											<img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
                    										</div>
                    										<input class="form-control" type="date" id="birthday" name="birthday" 
                    										       value="{{ old('birthday') }}" min="1900-01-01" max="{{ now()->toDateString() }}" placeholder="01/01/1990" style="border-radius: 0 30px 30px 0;" required>
                    									</div>
                    									@error('birthday')
                    										<div class="text-danger">{{ $message }}</div>
                    									@enderror
                    								</div>
                    							</div>
                    							<div class="col-4">
                    								<div class="form-group mt-2 mb-3">
                    									<label class="label-control">Email *</label>
                    									<div class="input-group input-group-merge group-form">
                    										<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                    											<img src="{{url('assets/form-groups/admin/9.svg')}}" alt="">
                    										</div>
                    										<input class="form-control" type="email" id="email" name="email" 
                    										       value="{{ old('email') }}" placeholder="ejemplo@cuentaemail.com" style="border-radius: 0 30px 30px 0;" required>
                    									</div>
                    									@error('email')
                    										<div class="text-danger">{{ $message }}</div>
                    									@enderror
                    								</div>
                    							</div>
                    							<div class="col-3">
                    								<div class="form-group mt-2 mb-3">
                    									<label class="label-control">Teléfono *</label>
                    									<div class="input-group input-group-merge group-form">
                    										<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                    											<img src="{{url('assets/form-groups/admin/10.svg')}}" alt="">
                    										</div>
                    										<input class="form-control" type="phone" id="phone" name="phone" 
                    										       value="{{ old('phone') }}" placeholder="940 200 200" style="border-radius: 0 30px 30px 0;" required>
                    									</div>
                    									@error('phone')
                    										<div class="text-danger">{{ $message }}</div>
                    									@enderror
                    								</div>
                    							</div>
                    						</div>
                    						<div class="row">
                    							<div class="col-4">
                    								<div class="form-group mt-2 mb-3">
                    									<label class="label-control">Contraseña *</label>
                    									<div class="input-group input-group-merge group-form">
                    										<div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                    											<img src="{{url('assets/form-groups/admin/13.svg')}}" alt="">
                    										</div>
                    										<input class="form-control" type="password" id="password" name="password" 
                    										       placeholder="Contraseña" style="border-radius: 0 30px 30px 0;" required>
                    									</div>
                    									@error('password')
                    										<div class="text-danger">{{ $message }}</div>
                    									@enderror
                    								</div>
                    							</div>
                    						</div>
                    				</div>

                    				<div class="row">

                    					<div class="col-12 text-end">
                    						<button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative;" class="btn btn-md btn-light mt-2">Guardar
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

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const avatar = document.getElementById('user-avatar');
            avatar.style.backgroundImage = `url(${e.target.result})`;
            avatar.innerHTML = '';
            // Guardar en localStorage para persistencia
            localStorage.setItem('image_user_create', e.target.result);
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeImage() {
    document.getElementById('user-image').value = '';
    const avatar = document.getElementById('user-avatar');
    avatar.style.backgroundImage = 'none';
    avatar.innerHTML = `<i class="ri-image-add-line"></i>`;
    localStorage.removeItem('image_user_create');
}

// Restaurar imagen si hay error de validación
document.addEventListener('DOMContentLoaded', function() {
    const savedImage = localStorage.getItem('image_user_create');
    if (savedImage) {
        const avatar = document.getElementById('user-avatar');
        avatar.style.backgroundImage = `url(${savedImage})`;
        avatar.innerHTML = '';
    }
});

// Limpiar localStorage al enviar exitosamente
document.getElementById('user-form').addEventListener('submit', function() {
    // Limpiar después de un pequeño delay para asegurar que se guardó
    setTimeout(() => {
        localStorage.removeItem('image_user_create');
    }, 1000);
});

// Inicializar validación de documento español
document.addEventListener('DOMContentLoaded', function() {
    initSpanishDocumentValidation('nif_cif', {
        showMessage: true
    });
    
    // Inicializar validación de email
    initEmailValidation('email', {
        context: 'user',
        showMessage: true
    });
});

// Validación del formulario
document.getElementById('user-form').addEventListener('submit', function(e) {
    const requiredFields = ['name', 'last_name', 'nif_cif', 'birthday', 'email', 'phone', 'password'];
    let isValid = true;
    
    requiredFields.forEach(field => {
        const input = document.getElementById(field);
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        alert('Por favor, completa todos los campos obligatorios.');
    }
});

// Limpiar validación al escribir
document.querySelectorAll('input[required]').forEach(input => {
    input.addEventListener('input', function() {
        this.classList.remove('is-invalid');
    });
});

</script>

@endsection
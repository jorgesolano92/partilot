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
                        <li class="breadcrumb-item active">Editar Gestor</li>
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

                    @php
                        $primaryManager = $administration->manager;
                        $primaryManagerUser = $primaryManager?->user;
                    @endphp

                    @if(session('info'))
                        <div class="alert alert-info">{{ session('info') }}</div>
                    @endif

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                        </div>
                    @endif

                    @php
                        $adminStatusValue = $administration->status;
                        if ($adminStatusValue === null || $adminStatusValue === -1) {
                            $adminStatusText = 'Pendiente';
                            $adminStatusClass = 'bg-secondary';
                        } elseif ($adminStatusValue == 1) {
                            $adminStatusText = 'Activo';
                            $adminStatusClass = 'bg-success';
                        } else {
                            $adminStatusText = 'Inactivo';
                            $adminStatusClass = 'bg-danger';
                        }
                    @endphp

                    <div class="row">

                    	<div class="col-md-3" style="position: relative;">
                    		<div class="form-card bs mb-3">

                    			<div class="form-wizard-element">
                    				<span>1</span>
                    				<img src="{{url('assets/admin.svg')}}" alt="">
                    				<label>Datos administración</label>
                    			</div>

                    			<div class="form-wizard-element active">
                    				<span>2</span>
                    				<img src="{{url('assets/gestor.svg')}}" alt="">
                    				<label>Datos Gestor</label>
                    			</div>

                    			<div class="form-wizard-element">
                    				<span>3</span>
                    				<img src="{{url('assets/api.svg')}}" alt="">
                    				<label>Configuración API</label>
                    			</div>

                    		</div>

                    		<div class="form-card bs mb-3">
                    			<h4 class="mb-0 mt-1">Página web</h4>
                    			<small><i>Este campo no es obligatorio</i></small>
                    			<div class="form-group mt-2">
	                    			<label class="label-control">Web</label>
	                    			<div class="input-group input-group-merge group-form">
	                                    <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
	                                        <img src="{{url('assets/form-groups/admin/0.svg')}}" alt="">
	                                    </div>
	                                    <input class="form-control" readonly value="{{ $administration->web }}" type="text" placeholder="www.administracion.es" style="border-radius: 0 30px 30px 0;">
	                                </div>
                    			</div>
                    		</div>

                    		<div class="form-card show-content bs">
                    			<h4 class="mb-0 mt-1">Estado Administración</h4>
                    			<small><i>Bloquea o desbloquea la administración</i></small>
                    			<div class="form-group mt-2">
	                    			<label class="">Estado Actual</label>
                                    <label class="badge badge-lg {{ $adminStatusClass }} float-end">{{ $adminStatusText }}</label>
	                    			<div style="clear: both;"></div>
                    			</div>
                    		</div>

                    		<a href="{{ route('administrations.show', $administration->id) }}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                    			<i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span>
                    		</a>
                    	</div>

                    	<div class="col-md-9">

                    @if(!$primaryManager || !$primaryManagerUser)
                        <form action="{{ route('administrations.assign-primary-manager', $administration->id) }}" method="POST" class="assign-primary-admin-manager">
                            @csrf
                            <div class="form-card bs" style="min-height: 658px;">
                                <h4 class="mb-0 mt-1">Datos del gestor principal</h4>
                                <small><i>Complete los campos obligatorios. El correo del gestor debe ser distinto al de acceso al panel ({{ $administration->email }}).</i></small>

                                <div class="alert alert-warning mt-2 mb-0">
                                    No hay gestor principal asignado (p. ej. usuario eliminado). Registre uno nuevo con los datos siguientes.
                                </div>

                                <div class="form-group mt-3 mb-3 admin-box">
                                    <div class="row">
                                        <div class="col-1">
                                            <div class="photo-preview-2">
                                                <i class="ri-account-circle-fill"></i>
                                            </div>
                                            <div style="clear: both;"></div>
                                        </div>
                                        <div class="col-4 text-center">
                                            <h4 class="mt-0 mb-0">{{ $administration->name ?? 'Sin nombre' }}</h4>
                                            <small class="text-muted">Nuevo gestor principal</small> <br>
                                            <i style="position: relative; top: 3px; font-size: 16px; color: #333" class="ri-computer-line"></i> {{ $administration->postal_code ?? '' }}
                                        </div>
                                        <div class="col-4">
                                            <div class="mt-2">
                                                Provincia: {{ $administration->province ?? '' }} <br>
                                                Dirección: {{ $administration->address ?? '' }}
                                            </div>
                                        </div>
                                        <div class="col-3">
                                            <div class="mt-2">
                                                Ciudad: {{ $administration->city ?? '' }} <br>
                                                Tel: {{ $administration->phone ?? '' }}
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
                                                    <input class="form-control" type="text" name="nif_cif" id="admin-assign-primary-nif-cif" value="{{ old('nif_cif') }}" placeholder="B26262626" style="border-radius: 0 30px 30px 0;">
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
                                                    <input class="form-control" type="date" name="birthday" value="{{ old('birthday') }}" style="border-radius: 0 30px 30px 0;">
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
                                                    <input class="form-control" type="email" name="email" value="{{ old('email') }}" placeholder="ejemplo@cuentaemail.com" style="border-radius: 0 30px 30px 0;" required>
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
                                                    <input class="form-control" type="text" name="phone" value="{{ old('phone') }}" placeholder="940 200 200" style="border-radius: 0 30px 30px 0;">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <h4 class="mb-0 mt-1">Comentarios</h4>
                                <small><i>Puedes añadir un comentario si necesitas añadir información adicional sobre el gestor.</i></small>

                                <div class="row">
                                    <div class="col-8">
                                        <div class="form-group mt-2">
                                            <label class="label-control">Comentario</label>
                                            <div class="input-group input-group-merge group-form" style="border: none">
                                                <textarea name="comment" class="form-control" placeholder="Añade tu comentario" rows="6" style="border-radius: 0 30px 30px 0;">{{ old('comment') }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-4 text-end">
                                        <button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative; top: calc(100% - 51px);" class="btn btn-md btn-light mt-2">Registrar gestor principal
                                            <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    @else

                    <form action="{{ route('managers.update', $primaryManager->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                    		<div class="form-card bs" style="min-height: 658px;">
                    			<h4 class="mb-0 mt-1">
                    				Datos de contacto
                    			</h4>
                    			<small><i>Todos los campos son obligatorios</i></small>

                    			<div class="form-group mt-2 mb-3 admin-box">

                    				<div class="row">
                    					<div class="col-1">
                    						
		                    				<div class="photo-preview-2">
		                    					
		                    				@if(!empty($primaryManagerUser->image))
		                    						<img src="{{url('manager/'.$primaryManagerUser->image)}}" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">
		                    					@else
		                    						<i class="ri-account-circle-fill"></i>
		                    					@endif

		                    				</div>
		                    				
		                    				<div style="clear: both;"></div>
                    					</div>

                    					<div class="col-4 text-center">

                    						<h4 class="mt-0 mb-0">{{ $administration->name ?? 'Sin nombre' }}</h4>

                    						<small>{{ $primaryManagerUser->name ?? '' }} {{ $primaryManagerUser->last_name ?? '' }}</small> <br>

                    						<i style="position: relative; top: 3px; font-size: 16px; color: #333" class="ri-computer-line"></i> {{ $administration->postal_code ?? '' }}
                    						
                    					</div>

                    					<div class="col-4">

                    						<div class="mt-2">
                    							Provincia: {{ $administration->province ?? '' }} <br>
                    							Dirección: {{ $administration->address ?? '' }}
                    						</div>
                    						
                    					</div>

                    					<div class="col-3">

                    						<div class="mt-2">
                    							Ciudad: {{ $administration->city ?? '' }} <br>
                    							Tel: {{ $administration->phone ?? '' }}
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

				                                    <input name="name" value="{{ $primaryManagerUser->name ?? '' }}" class="form-control" type="text" placeholder="Nombre" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input name="last_name" value="{{ $primaryManagerUser->last_name ?? '' }}" class="form-control" type="text" placeholder="Primer Apellido" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input name="last_name2" value="{{ $primaryManagerUser->last_name2 ?? '' }}" class="form-control" type="text" placeholder="Segundo Apellido" style="border-radius: 0 30px 30px 0;">
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

				                                    <input name="nif_cif" id="admin-edit-manager-nif-cif" value="{{ $primaryManagerUser->nif_cif ?? '' }}" class="form-control" type="text" placeholder="B26262626" style="border-radius: 0 30px 30px 0;">
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

				                                    <input name="birthday" value="{{ $primaryManagerUser->birthday?->format('Y-m-d') ?? '' }}" class="form-control" type="date" placeholder="01/01/1990" style="border-radius: 0 30px 30px 0;">
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

				                                    <input name="email" value="{{ $primaryManagerUser->email ?? '' }}" class="form-control" type="email" placeholder="ejemplo@cuentaemail.com" style="border-radius: 0 30px 30px 0;" required>
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

				                                    <input name="phone" value="{{ $primaryManagerUser->phone ?? '' }}" class="form-control" type="phone" placeholder="940 200 200" style="border-radius: 0 30px 30px 0;">
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
			                                    <textarea name="comment" class="form-control" placeholder="Añade tu comentario" rows="6">{{ $primaryManagerUser->comment ?? '' }}</textarea>
			                                </div>
		                    			</div>
                    				</div>
                    				<div class="col-4 text-end">
                    					<button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative; top: calc(100% - 51px);" class="btn btn-md btn-light mt-2">Guardar
                    						<i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i></button>
                    				</div>
                    			</div>

                    		</div>

                    </form>

                    @endif

                    	</div>
                    </div>

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
    if (document.getElementById('admin-edit-manager-nif-cif')) {
        initSpanishDocumentValidation('admin-edit-manager-nif-cif', { showMessage: true });
    }
    if (document.getElementById('admin-assign-primary-nif-cif')) {
        initSpanishDocumentValidation('admin-assign-primary-nif-cif', { showMessage: true });
    }
});
</script>

@endsection
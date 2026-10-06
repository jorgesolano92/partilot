@extends('layouts.layout')

@section('title', 'Vendedores/Asignación')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('sellers.index') }}">Vendedores/Asignación</a></li>
                        <li class="breadcrumb-item active">Editar Vendedor</li>
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
                    <h4 class="header-title">Editar Vendedor</h4>
                    <br>
                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <form action="{{ route('sellers.update', $seller->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-3" style="position: relative;">
                                <div class="form-card bs mb-3">
                                    <div class="form-wizard-element active">
                                        <span>&nbsp;&nbsp;</span>
                                        <img src="{{url('icons_/vendedores.svg')}}" alt="">
                                        <label>Datos Vendedor</label>
                                    </div>
                                </div>
                                <!-- Bloque de Estado Vendedor -->
                                <div class="form-card show-content mb-3 bs">
                                    <h4 class="mb-0 mt-1">Estado Vendedor</h4>
                                    <small><i>Selecciona el estado del vendedor</i></small>
                                    <div class="form-group mt-2">
                                        <label class="form-label">Estado Actual</label>
                                        <label class="badge badge-lg bg-{{ $seller->status_class }} float-end">
                                            {{ $seller->status_text }}
                                        </label>
                                        <div style="clear: both;"></div>
                                        <select name="status" class="form-select mt-3" id="seller_status" required>
                                            <option value="0" {{ old('status', $seller->getRawOriginal('status')) == 0 ? 'selected' : '' }}>Inactivo</option>
                                            <option value="1" {{ old('status', $seller->getRawOriginal('status')) == 1 ? 'selected' : '' }}>Activo</option>
                                            <option value="3" {{ old('status', $seller->getRawOriginal('status')) == 3 ? 'selected' : '' }}>Bloqueado</option>
                                        </select>
                                        <small class="text-muted">El estado "Pendiente" solo se usa durante la creación del vendedor.</small>
                                    </div>
                                </div>
                                <a href="{{ route('sellers.show', $seller->id) }}" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; padding: 8px; font-weight: bolder; position: absolute; bottom: 16px;" class="btn btn-md btn-light mt-2">
                                    <i style="top: 6px; left: 32%; font-size: 18px; position: absolute;" class="ri-arrow-left-circle-line"></i> <span style="display: block; margin-left: 16px;">Atrás</span>
                                </a>
                            </div>
                            <div class="col-md-9">
                                <div class="form-card bs" style="min-height: 658px;">
                                    <h4 class="mb-0 mt-1">Información del Vendedor</h4>
                                    <small><i>Edita los datos del vendedor</i></small>
                                    <div class="form-group mt-2 mb-3 admin-box">
                                        <div class="row">
                                            <div class="col-1">
                                                <div class="photo-preview-3">
                                                    <i class="ri-account-circle-fill"></i>
                                                </div>
                                                <div style="clear: both;"></div>
                                            </div>
                                            <div class="col-4 text-center mt-3">
                                                <h4 class="mt-0 mb-0">{{ $seller->getPrimaryEntity()?->name ?? 'Entidad' }}</h4>
                                                <small>{{ $seller->getPrimaryEntity()?->province ?? 'Provincia' }}</small> <br>
                                                <small>{{ $seller->getPrimaryEntity()?->administration->name ?? 'Administración' }}</small>
                                            </div>
                                            <div class="col-3">
                                                <div class="mt-3">
                                                    Provincia: {{ $seller->getPrimaryEntity()?->province ?? 'N/A' }} <br>
                                                    Dirección: {{ $seller->getPrimaryEntity()?->address ?? 'N/A' }}
                                                </div>
                                            </div>
                                            <div class="col-3">
                                                <div class="mt-3">
                                                    Ciudad: {{ $seller->getPrimaryEntity()?->city ?? 'N/A' }} <br>
                                                    Tel: {{ $seller->getPrimaryEntity()?->phone ?? 'N/A' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <br>
                                    <div style="min-height: 340px;">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Nombre</label>
                                                    <div class="input-group input-group-merge group-form">
                                                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                            <img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
                                                        </div>
                                                        <input class="form-control" type="text" name="name" value="{{ old('name', $seller->name) }}" placeholder="Nombre" style="border-radius: 0 30px 30px 0;" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Primer Apellido</label>
                                                    <div class="input-group input-group-merge group-form">
                                                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                            <img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
                                                        </div>
                                                        <input class="form-control" type="text" name="last_name" value="{{ old('last_name', $seller->last_name) }}" placeholder="Primer Apellido" style="border-radius: 0 30px 30px 0;" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Segundo Apellido</label>
                                                    <div class="input-group input-group-merge group-form">
                                                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                            <img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
                                                        </div>
                                                        <input class="form-control" type="text" name="last_name2" value="{{ old('last_name2', $seller->last_name2) }}" placeholder="Segundo Apellido" style="border-radius: 0 30px 30px 0;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">NIF/CIF</label>
                                                    <div class="input-group input-group-merge group-form">
                                                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                            <img src="{{url('assets/form-groups/admin/4.svg')}}" alt="">
                                                        </div>
                                                        <input class="form-control" type="text" name="nif_cif" id="seller-edit-nif-cif" value="{{ old('nif_cif', $seller->nif_cif) }}" placeholder="B26262626" style="border-radius: 0 30px 30px 0;">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">F. Nacimiento</label>
                                                    <div class="input-group input-group-merge group-form">
                                                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                            <img src="{{url('assets/form-groups/admin/12.svg')}}" alt="">
                                                        </div>
                                                        <input class="form-control" type="date" name="birthday" value="{{ old('birthday', $seller->birthday ? $seller->birthday->format('Y-m-d') : null) }}" min="1900-01-01" max="{{ now()->toDateString() }}" placeholder="01/01/1990" style="border-radius: 0 30px 30px 0;">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Email</label>
                                                    <div class="input-group input-group-merge group-form">
                                                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                            <img src="{{url('assets/form-groups/admin/9.svg')}}" alt="">
                                                        </div>
                                                        <input class="form-control" type="email" id="seller-edit-email" name="email" value="{{ old('email', $seller->email) }}" placeholder="ejemplo@cuentaemail.com" style="border-radius: 0 30px 30px 0;" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Teléfono</label>
                                                    <div class="input-group input-group-merge group-form">
                                                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                            <img src="{{url('assets/form-groups/admin/10.svg')}}" alt="">
                                                        </div>
                                                        <input class="form-control" type="text" name="phone" value="{{ old('phone', $seller->phone) }}" placeholder="940 200 200" style="border-radius: 0 30px 30px 0;">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Selección de grupo sin vista previa -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mt-2 mb-3">
                                                    <label class="label-control">Grupo del Vendedor</label>
                                                    <div class="input-group input-group-merge group-form">
                                                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                                                            <img src="{{url('assets/form-groups/admin/11.svg')}}" alt="">
                                                        </div>
                                                        <select class="form-control" name="group_id" style="border-radius: 0 30px 30px 0;">
                                                            <option value="">Sin grupo</option>
                                                            @foreach($groups as $group)
                                                                <option value="{{ $group->id }}" {{ old('group_id', $seller->groups->first()?->id) == $group->id ? 'selected' : '' }}>{{ $group->name }} ({{ $group->entity->name ?? 'N/A' }})</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-8"></div>
                                            <div class="col-4 text-end">
                                                <button type="submit" style="border-radius: 30px; width: 200px; background-color: #e78307; color: #333; padding: 8px; font-weight: bolder; position: relative; top: calc(100% - 51px);" class="btn btn-md btn-light mt-2">Guardar
                                                    <i style="top: 6px; margin-left: 6px; font-size: 18px; position: absolute;" class="ri-save-line"></i>
                                                </button>
                                            </div>
                                        </div>
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
    initSpanishDocumentValidation('seller-edit-nif-cif', {
        showMessage: true
    });
    
    // Inicializar validación de email
    initEmailValidation('seller-edit-email', {
        context: 'seller',
        excludeId: {{ $seller->id }},
        showMessage: true
    });
});
</script>
@endsection 

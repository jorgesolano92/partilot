@extends('layouts.layout')

@section('title','Editar Gestor')

@section('content')
@php
    $entity = $manager->entity;
    $managerPending = $manager->isPendingActivation();
    $managerRoleLegalOk = $manager->hasAcceptedRoleLegal();
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        @if($entity)
                            <li class="breadcrumb-item"><a href="{{ route('entities.index') }}">Entidades</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('entities.show', $entity->id) }}">Entidad</a></li>
                        @endif
                        <li class="breadcrumb-item active">Editar Gestor</li>
                    </ol>
                </div>
                <h4 class="page-title">Editar Gestor</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h4 class="mb-1">Estado del gestor</h4>
                            <label class="badge {{ $manager->statusBadgeClass() }}">{{ $manager->statusLabel() }}</label>
                            @if($managerPending)
                                <p class="small text-warning mb-0 mt-2"><i class="ri-time-line"></i> Pendiente de aceptar la invitación o el cargo.</p>
                            @endif
                            @if(! $managerRoleLegalOk)
                                <p class="small text-warning mb-0 mt-2"><i class="ri-file-warning-line"></i> No ha firmado / aceptado el marco legal del rol.</p>
                            @endif
                        </div>
                        @if($entity)
                        <div class="col-md-6">
                            <h4 class="mb-1">Estado de la entidad</h4>
                            <label class="badge bg-{{ $entity->status_class }}">{{ $entity->status_text === 'Activo' ? 'Activa' : $entity->status_text }}</label>
                            @if(! $entity->hasSignedFrameworkContract())
                                <p class="small text-warning mb-0 mt-2"><i class="ri-file-warning-line"></i> Contrato marco de la entidad pendiente de firma.</p>
                            @endif
                        </div>
                        @endif
                    </div>

                    <form action="{{ route('managers.update', $manager->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Nombre</label>
                                <input name="name" class="form-control" type="text" value="{{ old('name', $manager->user->name ?? '') }}" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Primer Apellido</label>
                                <input name="last_name" class="form-control" type="text" value="{{ old('last_name', $manager->user->last_name ?? '') }}" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Segundo Apellido</label>
                                <input name="last_name2" class="form-control" type="text" value="{{ old('last_name2', $manager->user->last_name2 ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">NIF/CIF</label>
                                <input name="nif_cif" class="form-control" type="text" value="{{ old('nif_cif', $manager->user->nif_cif ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">F. Nacimiento</label>
                                <input name="birthday" class="form-control" type="date" value="{{ old('birthday', $manager->user->birthday?->format('Y-m-d') ?? '') }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Email</label>
                                <input name="email" class="form-control" type="email" value="{{ old('email', $manager->user->email ?? '') }}" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input name="phone" class="form-control" type="text" value="{{ old('phone', $manager->user->phone ?? '') }}">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Comentario</label>
                                <textarea name="comment" class="form-control" rows="4">{{ old('comment', $manager->user->comment ?? '') }}</textarea>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            @if($entity)
                                <a href="{{ route('entities.show', $entity->id) }}" class="btn btn-light">Atrás</a>
                            @endif
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

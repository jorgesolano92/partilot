@extends('layouts.layout')

@section('title','Entidades')

@section('content')

<style>
	.form-check-input:checked {
		border-color: #333;
	}
</style>

<div class="container-fluid">
	<div class="row">
		<div class="col-12">
			<div class="page-title-box">
				<div class="page-title-right">
					<ol class="breadcrumb m-0">
						<li class="breadcrumb-item"><a href="{{ route('entities.index') }}">Entidades</a></li>
						<li class="breadcrumb-item"><a href="{{ route('entities.show', $entity->id) }}">{{ $entity->name }}</a></li>
						<li class="breadcrumb-item active">Editar firmante</li>
					</ol>
				</div>
				<h4 class="page-title">Editar firmante autorizado</h4>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-12">
			<div class="card">
				<div class="card-body">
					<form action="{{ route('entities.update-signer', $entity->id) }}" method="POST" id="entity-edit-signer-form">
						@csrf
						@method('PUT')

						<div class="form-card bs">
							<h4 class="mb-0 mt-1">Datos del firmante</h4>
							<small><i>
								Corrige los datos del representante autorizado. Mientras el contrato no esté firmado puedes actualizarlos y reenviar el enlace de firma (el enlace anterior dejará de valer).
							</i></small>

							@if($errors->any())
								<div class="alert alert-danger mt-3 mb-0">
									<ul class="mb-0">
										@foreach($errors->all() as $error)
											<li>{{ $error }}</li>
										@endforeach
									</ul>
								</div>
							@endif

							<div class="alert alert-info mt-3">
								<strong>Entidad:</strong> {{ $entity->name }}
								· <strong>Tipo:</strong> {{ $entity->clientTypeLabel() }}
								· <strong>Contrato:</strong> {{ $entity->contractStatusLabel() }}
							</div>

							<div class="row mt-2">
								<div class="col-4">
									<div class="form-group mt-2 mb-3">
										<label class="label-control">Nombre firmante</label>
										<input class="form-control" type="text" name="signer_name" value="{{ old('signer_name', $entity->signer_name) }}" required style="border-radius: 30px;">
									</div>
								</div>
								<div class="col-4">
									<div class="form-group mt-2 mb-3">
										<label class="label-control">Primer apellido</label>
										<input class="form-control" type="text" name="signer_last_name" value="{{ old('signer_last_name', $entity->signer_last_name) }}" required style="border-radius: 30px;">
									</div>
								</div>
								<div class="col-4">
									<div class="form-group mt-2 mb-3">
										<label class="label-control">Segundo apellido</label>
										<input class="form-control" type="text" name="signer_last_name2" value="{{ old('signer_last_name2', $entity->signer_last_name2) }}" style="border-radius: 30px;">
									</div>
								</div>
								<div class="col-4">
									<div class="form-group mt-2 mb-3">
										<label class="label-control">DNI/NIE</label>
										<input class="form-control" type="text" name="signer_nif" value="{{ old('signer_nif', $entity->signer_nif) }}" required style="border-radius: 30px;">
									</div>
								</div>
								<div class="col-4">
									<div class="form-group mt-2 mb-3">
										<label class="label-control">Email firmante</label>
										<input class="form-control" type="email" name="signer_email" value="{{ old('signer_email', $entity->signer_email) }}" required style="border-radius: 30px;" placeholder="ejemplo@cuentaemail.com">
									</div>
								</div>
								<div class="col-4">
									<div class="form-group mt-2 mb-3">
										<label class="label-control">Fecha de nacimiento</label>
										<input class="form-control" type="date" name="signer_birthday" value="{{ old('signer_birthday', optional($entity->signer_birthday)->format('Y-m-d')) }}" style="border-radius: 30px;">
									</div>
								</div>
								@unless($entity->isNaturalOrganizer())
								<div class="col-12">
									<div class="form-check form-switch mt-2 mb-3">
										<input type="hidden" name="signer_is_primary_manager" value="0">
										<input class="form-check-input" type="checkbox" role="switch" name="signer_is_primary_manager" id="signer_is_primary_manager" value="1"
											{{ old('signer_is_primary_manager', $entity->signer_is_primary_manager) ? 'checked' : '' }}>
										<label class="form-check-label" for="signer_is_primary_manager">
											¿Firmante = gestor responsable?
										</label>
									</div>
								</div>
								@endunless
								<div class="col-12">
									<div class="form-check form-switch mt-2 mb-3">
										<input type="hidden" name="resend_contract" value="0">
										<input class="form-check-input" type="checkbox" role="switch" name="resend_contract" id="resend_contract" value="1"
											{{ (string) old('resend_contract', '1') === '1' ? 'checked' : '' }}>
										<label class="form-check-label" for="resend_contract">
											Reenviar email de firma al correo del firmante (recomendado)
										</label>
									</div>
								</div>
							</div>

							<div class="row mt-3">
								<div class="col-6">
									<a href="{{ route('entities.show', $entity->id) }}" class="btn btn-md btn-light" style="border-radius: 30px; width: 200px; background-color: #333; color: #fff; font-weight: bolder;">
										Atrás
									</a>
								</div>
								<div class="col-6 text-end">
									<button type="submit" id="entity-edit-signer-submit" class="btn btn-md btn-light" style="border-radius: 30px; width: 220px; background-color: #e78307; color: #333; font-weight: bolder;">
										Guardar firmante
									</button>
								</div>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
document.getElementById('entity-edit-signer-form')?.addEventListener('submit', function () {
	var btn = document.getElementById('entity-edit-signer-submit');
	if (btn) {
		btn.disabled = true;
		btn.textContent = 'Guardando…';
	}
});
</script>

@endsection

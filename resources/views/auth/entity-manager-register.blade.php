<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Registro gestor de entidad | PARTILOT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ url('/') }}/logo.svg">
    <link href="{{ url('default') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ url('default') }}/assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <style>
        .group-login { border: 2px solid silver; padding: 5px 0; border-radius: 30px; background: #fff; display: flex; align-items: center; }
        .group-login input { border: none !important; box-shadow: none !important; background: transparent !important; }
        .group-login input[readonly] { color: #6c757d; }
        .pending-summary { background: #fff8f0; border: 2px solid #F59200; border-radius: 12px; padding: 16px 20px; margin-bottom: 8px; }
    </style>
</head>
<body class="auth-fluid-pages pb-0">
<div class="container py-4" style="max-width: 560px;">
    <div class="text-center mb-4">
        <img src="{{ url('/') }}/logo.svg" alt="PARTILOT" height="40">
        <h4 class="mt-3">{{ $pending->is_primary ? 'Gestor responsable de entidad' : 'Gestor de entidad' }}</h4>
        <div class="pending-summary text-start">
            <p class="mb-2 fw-semibold">Te han invitado a la entidad <strong>{{ $pending->entity->name ?? '—' }}</strong>.</p>
            @if($pending->entity?->administration?->name)
                <p class="mb-0 small text-muted"><strong>Administración:</strong> {{ $pending->entity->administration->name }}</p>
            @endif
        </div>
        <p class="text-muted small mb-0">Completa el registro con el correo indicado. En el siguiente paso deberás aceptar el cargo y firmar el contrato marco en nombre de la entidad.</p>
    </div>
    <div class="card">
        <div class="card-body p-4">
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="post" action="{{ route('entity-managers.pending.register.store', ['token' => $token]) }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="group-login"><input type="email" class="form-control" value="{{ $email }}" readonly></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre *</label>
                        <div class="group-login"><input type="text" name="name" class="form-control" required value="{{ old('name') }}"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Primer apellido *</label>
                        <div class="group-login"><input type="text" name="last_name" class="form-control" required value="{{ old('last_name') }}"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Segundo apellido</label>
                    <div class="group-login"><input type="text" name="last_name2" class="form-control" value="{{ old('last_name2') }}"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">NIF/CIF *</label>
                    <div class="group-login"><input type="text" name="nif_cif" class="form-control" required value="{{ old('nif_cif') }}" placeholder="12345678Z"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Teléfono</label>
                    <div class="group-login"><input type="tel" name="phone" class="form-control" value="{{ old('phone') }}"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Fecha de nacimiento *</label>
                    <div class="group-login"><input type="date" name="birthday" class="form-control" required value="{{ old('birthday') }}"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña *</label>
                    <div class="group-login"><input type="password" name="password" class="form-control" required minlength="8"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Repetir contraseña *</label>
                    <div class="group-login"><input type="password" name="password_confirmation" class="form-control" required minlength="8"></div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="marco_legal" value="1" id="marco_legal" required {{ old('marco_legal') ? 'checked' : '' }}>
                    <label class="form-check-label" for="marco_legal">
                        He leído y acepto el <a href="{{ route('legal.terminos-y-condiciones') }}" target="_blank" rel="noopener">Marco Legal de PARTILOT</a>.
                    </label>
                </div>
                <button type="submit" class="btn w-100 text-white" style="background:#F59200;border-radius:30px;font-weight:bold;">Crear cuenta y continuar</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>

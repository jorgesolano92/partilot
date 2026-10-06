<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Aceptar invitación y contraseña | PARTILOT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ url('/') }}/logo.svg">
    <link href="{{ url('default') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ url('default') }}/assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <style>
        .group-login {
            border: 2px solid silver;
            padding: 5px 0;
            border-radius: 30px;
            background: #fff;
            display: flex;
            align-items: center;
        }
        .group-login input {
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
        }
        .group-login input[readonly] {
            color: #6c757d;
            cursor: not-allowed;
        }
        .toggle-password-btn {
            border: none;
            background: transparent;
            color: #6c757d;
            padding: 0 14px 0 8px;
            line-height: 1;
            font-size: 16px;
            cursor: pointer;
        }
    </style>
</head>
<body class="auth-fluid-pages pb-0">
<div class="container py-5" style="max-width: 520px;">
    <div class="text-center mb-4">
        <img src="{{ url('/') }}/logo.svg" alt="PARTILOT" height="40">
        <h4 class="mt-3">Aceptar invitación como gestor</h4>
        <p class="text-muted mb-0">Entidad: <strong>{{ $entity->name ?? '—' }}</strong></p>
    </div>
    <div class="card">
        <div class="card-body p-4">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <p class="small text-muted">Revise sus datos. Puede modificarlos, excepto <strong>DNI/NIF</strong> y <strong>email</strong>.</p>
            @if(!empty($invitation))
                @include('partials.role-invitation-legal', ['invitation' => $invitation])
            @endif
            <form method="post" action="{{ route('entity-managers.confirm-accept.store', ['token' => $token]) }}">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre</label>
                        <div class="group-login">
                            <input type="text" name="name" class="form-control" required value="{{ old('name', $manager->user->name) }}">
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Primer apellido</label>
                        <div class="group-login">
                            <input type="text" name="last_name" class="form-control" required value="{{ old('last_name', $manager->user->last_name) }}">
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Segundo apellido</label>
                        <div class="group-login">
                            <input type="text" name="last_name2" class="form-control" value="{{ old('last_name2', $manager->user->last_name2) }}">
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha de nacimiento</label>
                        <div class="group-login">
                            <input type="date" name="birthday" class="form-control" value="{{ old('birthday', optional($manager->user->birthday)->format('Y-m-d')) }}">
                        </div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Email</label>
                        <div class="group-login">
                            <input type="email" class="form-control" value="{{ $manager->user->email }}" readonly>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">DNI / NIF</label>
                        <div class="group-login">
                            <input type="text" class="form-control" value="{{ $manager->user->nif_cif }}" readonly>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Teléfono</label>
                        <div class="group-login">
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $manager->user->phone) }}">
                        </div>
                    </div>
                </div>
                @if($manager->requires_password_setup)
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <div class="group-login">
                        <input type="password" name="password" id="password" class="form-control" required autocomplete="new-password" minlength="8">
                        <button type="button" class="toggle-password-btn" data-target="password" aria-label="Mostrar u ocultar contraseña">👁</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar contraseña</label>
                    <div class="group-login">
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required autocomplete="new-password" minlength="8">
                        <button type="button" class="toggle-password-btn" data-target="password_confirmation" aria-label="Mostrar u ocultar contraseña">👁</button>
                    </div>
                </div>
                @endif
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="terms_accepted" name="terms_accepted" value="1" {{ old('terms_accepted') ? 'checked' : '' }} required>
                    <label class="form-check-label" for="terms_accepted">
                        He leído y acepto las <a href="{{ route('legal.terminos-y-condiciones') }}" target="_blank" rel="noopener">condiciones de uso</a>.
                    </label>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success" style="border-radius: 30px; padding: 13px 0;">{{ $manager->requires_password_setup ? 'Aceptar y guardar' : 'Aceptar' }}</button>
                </div>
            </form>
        </div>
    </div>
    <p class="text-center text-muted small mt-3"><a href="{{ route('login') }}">Ir al inicio de sesión</a></p>
</div>
<script>
    document.querySelectorAll('.toggle-password-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(this.dataset.target);
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    });
</script>
</body>
</html>

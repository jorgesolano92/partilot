<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Contraseña provisional | PARTILOT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ url('/') }}/logo.svg">
    <link href="{{ url('default') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ url('default') }}/assets/css/app.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="auth-fluid-pages pb-0">
<div class="container py-5" style="max-width: 480px;">
    <div class="text-center mb-4">
        <img src="{{ url('/') }}/logo.svg" alt="PARTILOT" height="40">
        <h4 class="mt-3">Contraseña provisional</h4>
    </div>
    <div class="card">
        <div class="card-body p-4">
            <p class="text-muted small">Su cuenta usa una <strong>contraseña provisional</strong> enviada por correo. Por seguridad, le recomendamos establecer una nueva contraseña (mínimo 8 caracteres). También puede continuar y cambiarla más tarde.</p>
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="post" action="{{ route('provisional-password.update') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Nueva contraseña</label>
                    <input type="password" name="password" class="form-control" required autocomplete="new-password" minlength="8">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar contraseña</label>
                    <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password" minlength="8">
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-dark" style="border-radius: 30px;">Cambiar contraseña</button>
                </div>
            </form>
            <form method="post" action="{{ route('provisional-password.skip') }}" class="mt-3">
                @csrf
                <div class="d-grid">
                    <button type="submit" class="btn btn-outline-secondary" style="border-radius: 30px;">Continuar sin cambiar ahora</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>

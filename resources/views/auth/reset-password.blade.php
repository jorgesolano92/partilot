<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Nueva contraseña | PARTILOT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ url('/') }}/logo.svg">
    <link href="{{ url('default') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ url('default') }}/assets/css/app.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="auth-fluid-pages pb-0">
<div class="container py-5" style="max-width: 480px;">
    <div class="text-center mb-4">
        <img src="{{ url('/') }}/logo.svg" alt="PARTILOT" height="40">
        <h4 class="mt-3">Nueva contraseña</h4>
        <p class="text-muted small mb-0">Elija una contraseña segura de al menos 8 caracteres.</p>
    </div>
    <div class="card" style="border-radius: 24px;">
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
            <form method="post" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control @error('email') is-invalid @enderror"
                        value="{{ old('email', $email) }}"
                        required
                        autocomplete="username"
                        readonly
                    >
                </div>
                <div class="mb-3">
                    <label class="form-label">Nueva contraseña</label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password" minlength="8">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmar contraseña</label>
                    <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password" minlength="8">
                </div>
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-dark" style="border-radius: 30px;">Guardar contraseña</button>
                </div>
            </form>
            <p class="text-center mb-0">
                <a href="{{ route('login') }}" class="text-muted small">Volver al inicio de sesión</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>

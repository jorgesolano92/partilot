<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Recuperar contraseña | PARTILOT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ url('/') }}/logo.svg">
    <link href="{{ url('default') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ url('default') }}/assets/css/app.min.css" rel="stylesheet" type="text/css" />
    <style>
        .group-login {
            border: 2px solid silver;
            border-radius: 30px;
            background: #fff;
        }
        .group-login div,
        .group-login input {
            border: none;
            padding: .7rem .9rem;
        }
    </style>
</head>
<body class="auth-fluid-pages pb-0">
<div class="container py-5" style="max-width: 480px;">
    <div class="text-center mb-4">
        <img src="{{ url('/') }}/logo.svg" alt="PARTILOT" height="40">
        <h4 class="mt-3">Recuperar contraseña</h4>
        <p class="text-muted small mb-0">Indique el email o usuario de panel de su cuenta. Le enviaremos un enlace para establecer una nueva contraseña.</p>
    </div>
    <div class="card" style="border-radius: 24px;">
        <div class="card-body p-4">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="post" action="{{ route('password.email') }}">
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">Email o usuario de panel</label>
                    <div class="input-group input-group-merge group-login">
                        <div class="input-group-text" style="border-radius: 30px 0 0 30px;">
                            <span class="ri-user-line"></span>
                        </div>
                        <input
                            type="text"
                            name="email"
                            id="email"
                            class="form-control @error('email') is-invalid @enderror"
                            value="{{ old('email') }}"
                            placeholder="Email o usuario de panel"
                            required
                            autocomplete="username"
                            style="border-radius: 0 30px 30px 0;"
                        >
                    </div>
                </div>
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-dark" style="border-radius: 30px;">Enviar enlace</button>
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

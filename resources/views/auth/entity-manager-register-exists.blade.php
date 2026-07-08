<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Cuenta existente | PARTILOT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="{{ url('default') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="auth-fluid-pages pb-0">
<div class="container py-5 text-center" style="max-width: 520px;">
    <img src="{{ url('/') }}/logo.svg" alt="PARTILOT" height="40" class="mb-4">
    <h4>Ya tienes una cuenta</h4>
    <p class="text-muted">El correo <strong>{{ $email }}</strong> ya está registrado en Partilot. Inicia sesión con tu contraseña habitual; si la administración te invita de nuevo como gestor existente, recibirás un correo distinto para aceptar o rechazar sin registrarte otra vez.</p>
    <a href="{{ route('login') }}" class="btn btn-dark mt-2" style="border-radius:30px;">Iniciar sesión</a>
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Enlace no válido | PARTILOT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ url('/') }}/logo.svg">
    <link href="{{ url('default') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ url('default') }}/assets/css/app.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="auth-fluid-pages pb-0">
<div class="container py-5 text-center" style="max-width: 480px;">
    <img src="{{ url('/') }}/logo.svg" alt="PARTILOT" height="40" class="mb-3">
    <h4>Enlace no válido o caducado</h4>
    <p class="text-muted">Este enlace ya se ha usado o ha caducado. Puede recuperar el acceso con «¿Olvidaste tu contraseña?» usando su email.</p>
    <p class="mb-1"><a href="{{ route('login') }}">Acceso al panel</a></p>
    <p><a href="{{ config('partilot.webapp_url') }}/login">Acceso a la app de Partilot</a></p>
</div>
</body>
</html>

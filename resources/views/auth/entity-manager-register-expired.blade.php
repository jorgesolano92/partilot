<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Enlace caducado | PARTILOT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="{{ url('default') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="auth-fluid-pages pb-0">
<div class="container py-5 text-center" style="max-width: 520px;">
    <img src="{{ url('/') }}/logo.svg" alt="PARTILOT" height="40" class="mb-4">
    <h4>Enlace no válido</h4>
    <p class="text-muted">Esta invitación ya no está disponible, ha sido rechazada o el enlace ha caducado. Contacta con la administración de la entidad si necesitas una nueva invitación.</p>
    <a href="{{ route('login') }}" class="btn btn-dark mt-2" style="border-radius:30px;">Ir al inicio de sesión</a>
</div>
</body>
</html>

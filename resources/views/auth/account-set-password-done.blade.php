<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Contraseña creada | PARTILOT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ url('/') }}/logo.svg">
    <link href="{{ url('default') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="{{ url('default') }}/assets/css/app.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="auth-fluid-pages pb-0">
<div class="container py-5 text-center" style="max-width: 480px;">
    <img src="{{ url('/') }}/logo.svg" alt="PARTILOT" height="40" class="mb-3">
    <h4>Contraseña creada</h4>
    <p class="text-muted">Ya puede entrar en la app de Partilot con <strong>{{ $email }}</strong> y la contraseña que acaba de crear.</p>
    <a href="{{ $webappUrl }}/login" class="btn btn-dark" style="border-radius: 30px;">Ir a la app</a>
</div>
</body>
</html>

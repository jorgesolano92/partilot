@php
    $reason = trim((string) ($exception?->getMessage() ?? ''));
    if ($reason === '' || $reason === 'Forbidden' || $reason === 'This action is unauthorized.') {
        $reason = 'No tienes permisos para acceder a esta sección con tu usuario actual.';
    }
    $previous = url()->previous();
    $showBack = $previous && $previous !== url()->current();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>Acceso no permitido | PARTILOT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="{{ url('/') }}/logo.svg">
    <link href="{{ url('default') }}/assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="auth-fluid-pages pb-0">
<div class="container py-5" style="max-width: 520px;">
    <div class="text-center mb-4">
        <img src="{{ url('/') }}/logo.svg" alt="PARTILOT" height="40">
    </div>
    <div class="card shadow-sm" style="border-radius: 16px;">
        <div class="card-body p-4 text-center">
            <h4 class="mb-2">Acceso no permitido</h4>
            <p class="text-muted mb-4">{{ $reason }}</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-dark" style="border-radius: 30px;">Volver a mi panel</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-dark" style="border-radius: 30px;">Ir al inicio de sesión</a>
                @endauth
                @if($showBack)
                    <a href="{{ $previous }}" class="btn btn-outline-dark" style="border-radius: 30px;">Volver atrás</a>
                @endif
            </div>
        </div>
    </div>
</div>
</body>
</html>

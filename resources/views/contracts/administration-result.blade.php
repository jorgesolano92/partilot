<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Partilot' }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6f7; margin: 0; padding: 40px 16px; }
        .card { max-width: 520px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 2px 12px rgba(0,0,0,.08); text-align: center; }
        .icon { font-size: 48px; margin-bottom: 16px; }
        h1 { font-size: 22px; margin: 0 0 12px; }
        p { color: #555; line-height: 1.5; }
        .success { color: #198754; }
        .error { color: #dc3545; }
        a.btn { display: inline-block; margin-top: 16px; padding: 10px 20px; background: #212529; color: #fff; text-decoration: none; border-radius: 24px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">{{ ($success ?? false) ? '✓' : '!' }}</div>
        <h1 class="{{ ($success ?? false) ? 'success' : 'error' }}">{{ $title ?? '' }}</h1>
        <p>{{ $message ?? '' }}</p>
        @if ($success ?? false)
            <a class="btn" href="{{ route('login') }}">Ir al acceso del panel</a>
        @endif
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato pendiente - Partilot</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6f7; margin: 0; padding: 40px 16px; }
        .card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        h1 { font-size: 24px; margin: 0 0 12px; }
        p { color: #444; line-height: 1.55; }
        .alert { background: #fff3cd; border-radius: 12px; padding: 16px; margin: 20px 0; color: #664d03; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
        button, a.btn { border: 0; border-radius: 24px; padding: 12px 22px; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block; }
        .primary { background: #212529; color: #fff; }
        .secondary { background: #f8f9fa; color: #212529; border: 1px solid #ced4da; }
        .success { color: #198754; margin-top: 12px; }
        .error { color: #dc3545; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Firma del contrato SaaS pendiente</h1>
        <p>Para utilizar el panel de PARTILOT, la administración <strong>{{ $administration->name ?? $administration->society }}</strong> debe firmar el contrato de prestación de servicios.</p>

        <div class="alert">
            Hemos enviado un enlace de firma a <strong>{{ $administration->email }}</strong>.
            Revise la bandeja de entrada y la carpeta de spam.
        </div>

        @if (session('success'))
            <p class="success">{{ session('success') }}</p>
        @endif
        @if (session('error'))
            <p class="error">{{ session('error') }}</p>
        @endif

        <div class="actions">
            <form method="POST" action="{{ route('administration-contract.resend') }}">
                @csrf
                <button type="submit" class="primary">Reenviar correo de firma</button>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="secondary">Cerrar sesión</button>
            </form>
        </div>
    </div>
</body>
</html>

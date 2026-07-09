<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Propuesta de asignación - Partilot</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #e78307; }
        .header h1 { color: #333; margin: 0; font-size: 22px; }
        .preview-box { background: #fff8ee; border-left: 4px solid #e78307; padding: 14px 16px; margin: 20px 0; }
        .info-box { background-color: #f8f9fa; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0; }
        .buttons { text-align: center; margin: 28px 0; }
        .btn { display: inline-block; padding: 12px 20px; margin: 8px 4px; text-decoration: none; border-radius: 30px; font-weight: bold; font-size: 14px; }
        .btn-conditions { background-color: #333; color: #fff; }
        .btn-accept { background-color: #28a745; color: #fff; }
        .btn-reject { background-color: #dc3545; color: #fff; }
        .footer { text-align: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
        ul { padding-left: 18px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Propuesta de asignación de participaciones</h1>
        </div>

        <p>Hola <strong>{{ $proposal->seller->name ?? $proposal->seller->email }}</strong>,</p>

        <p>
            <strong>{{ $proposal->entity->name ?? 'Tu entidad' }}</strong>
            te propone vender participaciones del sorteo
            <strong>{{ $proposal->lottery->name ?? 'N/A' }}</strong>.
        </p>

        <div class="preview-box">
            <strong>Resumen:</strong> se te proponen
            <strong>{{ $proposal->participation_count }}</strong>
            participación(es) física(s) pendientes de tu aceptación del recibo.
        </div>

        <div class="info-box">
            <p><strong>Antes de aceptar:</strong></p>
            <ul>
                @foreach(config('legal_roles.recibo_participaciones.summary_bullets', []) as $bullet)
                    <li>{{ $bullet }}</li>
                @endforeach
            </ul>
        </div>

        <p>Este enlace caduca el {{ $proposal->expires_at?->format('d/m/Y H:i') }}.</p>

        <div class="buttons">
            <a href="{{ $conditionsUrl }}" class="btn btn-conditions">Ver condiciones</a>
            <a href="{{ $acceptUrl }}" class="btn btn-accept">Acepto recibo de participaciones</a>
            <a href="{{ $rejectUrl }}" class="btn btn-reject">Rechazar</a>
        </div>

        <div class="footer">
            <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
            <p>&copy; {{ date('Y') }} Partilot. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo aceptado - Partilot</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { border-bottom: 2px solid #28a745; padding-bottom: 12px; margin-bottom: 20px; }
        .footer { text-align: center; margin-top: 24px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Recibo de participaciones aceptado</h1>
        </div>

        <p>Hola,</p>

        <p>
            El vendedor <strong>{{ $seller->full_name ?? $seller->email }}</strong>
            ha aceptado el recibo de <strong>{{ $assignedCount }}</strong>
            participación(es) del sorteo <strong>{{ $proposal->lottery->name ?? 'N/A' }}</strong>.
        </p>

        <p>Las participaciones ya figuran como asignadas a este vendedor en la plataforma.</p>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Partilot.</p>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cobro de premio disponible</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.5;">
    <p>Hola {{ $user->name ?? 'usuario' }},</p>
    <p>{{ $bodyMessage }}</p>
    <p><strong>Entidad:</strong> {{ $entity->name }}</p>
    <p><strong>Sorteo:</strong> {{ $lottery->name }}</p>
    <p>Accede a la app Partilot y entra en <em>Cobrar / Gestionar</em> para tramitar tu premio.</p>
    <p style="color:#666;font-size:12px;">Este mensaje ha sido enviado por Partilot.</p>
</body>
</html>

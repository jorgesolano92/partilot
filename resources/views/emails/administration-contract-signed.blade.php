<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato SaaS firmado</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.5;">
    <p>Hola,</p>
    <p>
        Adjuntamos copia del contrato SaaS firmado para la administración
        <strong>{{ $administration->name ?? $administration->society }}</strong>
        (referencia {{ $administration->contract_reference }}).
    </p>
    <p>Fecha de firma: {{ optional($administration->contract_signed_at)->format('d/m/Y H:i') }}</p>
    <p>Gracias,<br>Equipo Partilot</p>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato SaaS PARTILOT</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.5;">
    <p>Hola,</p>
    <p>
        Para activar el acceso de la administración <strong>{{ $administration->name ?? $administration->society }}</strong>
        a la plataforma PARTILOT es necesario firmar el contrato de prestación de servicios SaaS.
    </p>
    <p>
        Referencia del contrato: <strong>{{ $administration->contract_reference }}</strong>
    </p>
    <p>
        <a href="{{ $signUrl }}" style="display:inline-block;padding:12px 24px;background:#212529;color:#fff;text-decoration:none;border-radius:24px;">
            Revisar y firmar contrato
        </a>
    </p>
    <p style="font-size:12px;color:#666;">Si el botón no funciona, copia este enlace en tu navegador:<br>{{ $signUrl }}</p>
    <p>Gracias,<br>Equipo Partilot</p>
</body>
</html>

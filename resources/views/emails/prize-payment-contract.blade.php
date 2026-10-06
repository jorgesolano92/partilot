<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato de cobro de premios</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.5;">
    <p>Hola,</p>
    <p>
        Para el sorteo <strong>{{ $setting->lottery->name ?? '—' }}</strong> de la entidad
        <strong>{{ $setting->entity->name ?? '—' }}</strong> es necesario firmar el contrato
        específico de cobro de premios a través de PARTILOT.
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

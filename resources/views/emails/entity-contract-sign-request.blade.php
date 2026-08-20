<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato marco PARTILOT</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.5;">
    <p>Hola,</p>
    <p>
        Para activar <strong>{{ $entity->name }}</strong> en la plataforma PARTILOT es necesario que el
        <strong>firmante autorizado</strong> revise y firme el contrato marco de prestación de servicios.
    </p>
    @if($entity->signer_name || $entity->signer_last_name)
        <p>
            Firmante registrado:
            <strong>{{ trim(($entity->signer_name ?? '').' '.($entity->signer_last_name ?? '').' '.($entity->signer_last_name2 ?? '')) }}</strong>
        </p>
    @endif
    <p>
        Referencia del contrato: <strong>{{ $entity->contract_reference }}</strong>
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

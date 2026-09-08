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
        Este correo se ha enviado a la dirección del representante autorizado
        @if(!empty($entity->signer_email))
            (<strong>{{ $entity->signer_email }}</strong>)
        @endif
        para que pueda firmar el contrato.
    </p>
    <p>
        Referencia del contrato: <strong>{{ $entity->contract_reference }}</strong>
    </p>
    <p>
        <a href="{{ $signUrl }}" style="display:inline-block;padding:12px 24px;background:#212529;color:#fff;text-decoration:none;border-radius:24px;">
            Revisar y firmar contrato
        </a>
    </p>
    <p style="font-size:12px;color:#666;">Si el botón no funciona, copia este enlace en tu navegador:<br>{{ $signUrl }}</p>

    <hr style="border:none;border-top:1px solid #ddd;margin:28px 0;">
    <p style="font-size:13px;color:#555;">
        <strong>Próximos pasos (importante):</strong><br>
        Este correo es <strong>solo para firmar el contrato</strong>; no incluye aún el acceso a la plataforma.<br>
        Cuando firme, el <strong>gestor responsable</strong> recibirá un correo para aceptar su cargo y condiciones.
        Después de esa aceptación se enviará a la entidad el correo con el <strong>acceso al panel</strong>.
    </p>

    <p>Gracias,<br>Equipo Partilot</p>
</body>
</html>

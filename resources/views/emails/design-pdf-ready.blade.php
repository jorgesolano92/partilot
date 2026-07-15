<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>PDF listo</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; background:#f4f4f4; }
        .container { background:#fff; padding: 28px; border-radius: 10px; }
        .box { background:#f0f7ff; border-left: 4px solid #0d6efd; padding: 14px 16px; margin: 14px 0; }
        .btn { display: inline-block; margin-top: 12px; padding: 10px 18px; background:#e78307; color:#222 !important; text-decoration:none; border-radius: 24px; font-weight: 700; }
        .footer { margin-top: 24px; color:#666; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <p>Hola,</p>
    <p>La generación del PDF <strong>{{ $pdfLabel }}</strong> (diseño #{{ $designId }}) ha terminado.</p>
    <div class="box">
        <p>Puede descargarlo desde el enlace siguiente (necesita haber iniciado sesión en Partilot).</p>
    </div>
    <p><a href="{{ $downloadUrl }}" class="btn">Descargar PDF</a></p>
    <p class="footer">El enlace suele estar disponible durante un día. Este mensaje se ha generado automáticamente.</p>
</div>
</body>
</html>

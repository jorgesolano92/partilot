<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diseño pendiente de aprobación</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; background:#f4f4f4; }
        .container { background:#fff; padding: 28px; border-radius: 10px; }
        .box { background:#fff8e6; border-left: 4px solid #e78307; padding: 14px 16px; margin: 14px 0; }
        .btn { display: inline-block; margin-top: 12px; padding: 10px 18px; background:#e78307; color:#222 !important; text-decoration:none; border-radius: 24px; font-weight: 700; }
        .footer { margin-top: 24px; color:#666; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <p>Hola,</p>
    <p>La administración ha enviado a su revisión el diseño de participaciones del set <strong>{{ $design->set->set_name ?? ('#'.$design->set_id) }}</strong> de la entidad <strong>{{ $design->entity->name ?? '—' }}</strong>.</p>
    <div class="box">
        <p><strong>Acción requerida:</strong> revise el diseño en el panel de Partilot y confírmelo o indique las correcciones necesarias.</p>
        @if($design->lottery)
            <p><strong>Sorteo:</strong> {{ $design->lottery->name }}</p>
        @endif
    </div>
    <p>Puede acceder directamente desde este enlace:</p>
    <p><a href="{{ $reviewUrl }}" class="btn">Revisar diseño</a></p>
    <p>También puede entrar en <em>Diseño e Impresión → Aprobaciones</em> en su panel.</p>
    <p class="footer">Este mensaje se ha generado automáticamente.</p>
</div>
</body>
</html>

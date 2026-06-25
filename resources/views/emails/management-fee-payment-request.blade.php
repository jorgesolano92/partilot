<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cuota de gestión PARTILOT</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; background:#f4f4f4; }
        .container { background:#fff; padding: 28px; border-radius: 10px; }
        .box { background:#fff8e6; border-left: 4px solid #e78307; padding: 14px 16px; margin: 14px 0; }
    </style>
</head>
<body>
<div class="container">
    <p>Hola,</p>
    <p>La administración ha iniciado el diseño de participaciones para el set <strong>{{ $design->set->set_name ?? ('#'.$design->set_id) }}</strong> de la entidad <strong>{{ $design->entity->name ?? '—' }}</strong>.</p>
    <div class="box">
        <p><strong>Acción requerida:</strong> debe abonar la cuota de gestión PARTILOT desde el panel, en <em>Diseño e Impresión</em>, antes de que la administración pueda continuar con el diseño.</p>
    </div>
    <p>Entre en su panel de Partilot y localice el diseño con estado <strong>Cuota gestión impagada</strong> para pagar con tarjeta.</p>
    <p style="color:#666;font-size:12px;">Este mensaje se ha generado automáticamente.</p>
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato marco pendiente - Partilot</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6f7; margin: 0; padding: 40px 16px; }
        .card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        h1 { font-size: 24px; margin: 0 0 12px; }
        p { color: #444; line-height: 1.55; }
        .alert { background: #fff3cd; border-radius: 12px; padding: 16px; margin: 20px 0; color: #664d03; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 24px; }
        button, a.btn { border: 0; border-radius: 24px; padding: 12px 22px; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block; }
        .primary { background: #212529; color: #fff; }
        .secondary { background: #f8f9fa; color: #212529; border: 1px solid #ced4da; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Contrato marco de entidad pendiente</h1>

        @if ($waitingForManager)
            <p>La entidad <strong>{{ $entity->name }}</strong> no puede operar en PARTILOT hasta que el <strong>representante autorizado</strong> firme el contrato marco y, a continuación, el Gestor Responsable acepte el cargo.</p>
            <div class="alert">
                Está pendiente la firma del representante autorizado (enlace enviado al email de la entidad). Cuando firme, el gestor responsable recibirá la invitación para aceptar el cargo.
            </div>
        @elseif ($manager && $manager->confirmation_token && $entity->contract_status === \App\Models\Entity::CONTRACT_SIGNED)
            <p>El contrato marco de <strong>{{ $entity->name }}</strong> ya está firmado. Para activar la entidad debe aceptar el cargo de Gestor Responsable.</p>
            <div class="actions">
                <a class="btn primary" href="{{ route('entity-managers.confirm-accept', ['token' => $manager->confirmation_token]) }}">
                    Aceptar cargo
                </a>
            </div>
        @else
            <p>La entidad <strong>{{ $entity->name }}</strong> tiene pendiente la firma del contrato marco por el representante autorizado.</p>
        @endif

        <div class="actions">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="secondary">Cerrar sesión</button>
            </form>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firmar contrato de premios - Partilot</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6f7; margin: 0; padding: 24px 16px; color: #212529; }
        .card { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        h1 { font-size: 24px; margin: 0 0 8px; }
        .meta { color: #6c757d; margin-bottom: 24px; }
        .terms { background: #f8f9fa; border-radius: 12px; padding: 20px; max-height: 320px; overflow-y: auto; font-size: 14px; line-height: 1.6; margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        input[type="text"] { width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 8px; margin-bottom: 16px; box-sizing: border-box; }
        .checkbox { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 20px; font-size: 14px; }
        button { background: #212529; color: #fff; border: 0; border-radius: 24px; padding: 12px 28px; font-size: 15px; cursor: pointer; }
        .error { color: #dc3545; font-size: 13px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Contrato de cobro de premios</h1>
        <p class="meta">
            Entidad: <strong>{{ $setting->entity->name ?? '—' }}</strong> ·
            Sorteo: <strong>{{ $setting->lottery->name ?? '—' }}</strong>
        </p>

        <div class="terms">
            <p><strong>Objeto.</strong> El presente acuerdo regula la gestión del cobro de participaciones premiadas vendidas en modalidad digital para el sorteo indicado, cuando la entidad ha optado por la gestión a través de PARTILOT o cuando, en modalidad presencial, existen participaciones digitales premiadas.</p>
            <p><strong>Obligaciones de la entidad.</strong> La entidad se compromete a ingresar en PARTILOT el importe íntegro de los premios de participaciones digitales sujetas a cobro online, en el plazo acordado con la administración, antes de la activación del cobro a usuarios finales.</p>
            <p><strong>Activación.</strong> PARTILOT activará el cobro online a los titulares una vez confirmado el ingreso de fondos y registrada la firma de este contrato.</p>
            <p><strong>Responsabilidad.</strong> La entidad garantiza la veracidad de los datos facilitados y el cumplimiento de la normativa aplicable en materia de loterías y protección de datos.</p>
            <p><strong>Aceptación.</strong> Al firmar electrónicamente, la persona firmante declara tener capacidad de representación de la entidad y aceptar las condiciones anteriores para el sorteo indicado.</p>
        </div>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('prize-contract.sign.submit', $token) }}">
            @csrf
            <label for="signer_name">Nombre y apellidos del firmante</label>
            <input type="text" id="signer_name" name="signer_name" value="{{ old('signer_name') }}" required>

            <label class="checkbox">
                <input type="checkbox" name="accept_terms" value="1" {{ old('accept_terms') ? 'checked' : '' }} required>
                <span>He leído y acepto el contrato específico de cobro de premios para este sorteo.</span>
            </label>

            <button type="submit">Firmar contrato</button>
        </form>
    </div>
</body>
</html>

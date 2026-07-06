<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firmar contrato SaaS - Partilot</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6f7; margin: 0; padding: 24px 16px; color: #212529; }
        .card { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        h1 { font-size: 24px; margin: 0 0 8px; }
        .meta { color: #6c757d; margin-bottom: 24px; }
        .terms { background: #f8f9fa; border-radius: 12px; padding: 20px; max-height: 420px; overflow-y: auto; font-size: 13px; line-height: 1.55; margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        input[type="text"] { width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 8px; margin-bottom: 16px; box-sizing: border-box; }
        .checkbox { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 20px; font-size: 14px; }
        button { background: #212529; color: #fff; border: 0; border-radius: 24px; padding: 12px 28px; font-size: 15px; cursor: pointer; }
        .error { color: #dc3545; font-size: 13px; margin-bottom: 12px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="card">
        <h1>Contrato SaaS de administración</h1>
        <p class="meta">
            Administración: <strong>{{ $viewData['commercialName'] }}</strong> ·
            Referencia: <strong>{{ $viewData['contractReference'] }}</strong>
        </p>

        <div class="terms">
            @include('contracts.administration_saas_content', $viewData)
        </div>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('administration-contract.sign.submit', $token) }}">
            @csrf
            <div class="grid">
                <div>
                    <label for="signer_name">Nombre y apellidos del firmante (representante)</label>
                    <input type="text" id="signer_name" name="signer_name" value="{{ old('signer_name', $viewData['representativeName'] !== '—' ? $viewData['representativeName'] : '') }}" required>
                </div>
                <div>
                    <label for="signer_nif">DNI / NIE del firmante</label>
                    <input type="text" id="signer_nif" name="signer_nif" value="{{ old('signer_nif', $viewData['representativeNif'] !== '—' ? $viewData['representativeNif'] : '') }}" required>
                </div>
            </div>

            <label class="checkbox">
                <input type="checkbox" name="accept_terms" value="1" {{ old('accept_terms') ? 'checked' : '' }} required>
                <span>Declaro tener capacidad de representación de la administración de lotería, haber leído el contrato y el Anexo I, y acepto sus condiciones.</span>
            </label>

            <button type="submit">Firmar contrato electrónicamente</button>
        </form>
    </div>
</body>
</html>

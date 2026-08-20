<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma contrato marco - Partilot</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6f7; margin: 0; padding: 24px 16px; color: #212529; }
        .card { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        h1 { font-size: 24px; margin: 0 0 8px; }
        .meta { color: #6c757d; margin-bottom: 20px; }
        .terms { background: #f8f9fa; border-radius: 12px; padding: 20px; max-height: 520px; overflow-y: auto; font-size: 12px; line-height: 1.55; margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        input[type="text"] { width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 8px; margin-bottom: 16px; box-sizing: border-box; }
        .checkbox { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 16px; font-size: 14px; }
        button { border: 0; border-radius: 24px; padding: 12px 28px; font-size: 15px; cursor: pointer; font-weight: 600; background: #198754; color: #fff; width: 100%; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .error { color: #dc3545; font-size: 13px; margin-bottom: 12px; }
        @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="card">
        <h1>Firma del Contrato Marco</h1>
        <p class="meta">
            Firmante autorizado de <strong>{{ $viewData['entityName'] }}</strong>.
            Referencia: <strong>{{ $viewData['contractReference'] }}</strong>
        </p>

        <p><strong>Contrato Marco de Prestación de Servicios — Versión {{ $viewData['contractVersion'] }}</strong></p>
        <div class="terms">
            @include('contracts.partials.contract_table_styles')
            @include('contracts.entity_framework_content', $viewData)
        </div>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('entity-contract.sign.store', $token) }}">
            @csrf
            <div class="grid">
                <div>
                    <label for="signer_name">Nombre y apellidos del firmante autorizado</label>
                    <input type="text" id="signer_name" name="signer_name" value="{{ old('signer_name', $viewData['signerName'] !== '—' ? $viewData['signerName'] : '') }}" required>
                </div>
                <div>
                    <label for="signer_nif">DNI / NIE</label>
                    <input type="text" id="signer_nif" name="signer_nif" value="{{ old('signer_nif', $viewData['signerNif'] !== '—' ? $viewData['signerNif'] : '') }}" required>
                </div>
            </div>

            <label class="checkbox">
                <input type="checkbox" name="accept_contract" value="1" {{ old('accept_contract') ? 'checked' : '' }} required>
                <span>Declaro tener capacidad para representar a la entidad/organizador y acepto el Contrato Marco de Uso de la Plataforma.</span>
            </label>

            <button type="submit">Firmar contrato</button>
        </form>
    </div>
</body>
</html>

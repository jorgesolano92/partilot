<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma contrato marco - Partilot</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6f7; margin: 0; padding: 24px 16px 280px; color: #212529; }
        .card { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        h1 { font-size: 24px; margin: 0 0 8px; }
        .meta { color: #6c757d; margin-bottom: 16px; }
        .hint { background: #eef6ff; border: 1px solid #cfe2ff; border-radius: 10px; padding: 12px 14px; font-size: 14px; margin-bottom: 16px; }
        .terms { background: #f8f9fa; border-radius: 12px; padding: 20px; max-height: 420px; overflow-y: auto; font-size: 12px; line-height: 1.55; margin-bottom: 12px; }
        .accept-panel {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 50;
            background: #fff; border-top: 1px solid #dee2e6;
            box-shadow: 0 -6px 24px rgba(0,0,0,.08);
            padding: 14px 16px calc(14px + env(safe-area-inset-bottom, 0px));
        }
        .accept-panel-inner { max-width: 900px; margin: 0 auto; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        input[type="text"] { width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 8px; margin-bottom: 12px; box-sizing: border-box; }
        .checkbox { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 12px; font-size: 14px; }
        button { border: 0; border-radius: 24px; padding: 12px 28px; font-size: 15px; cursor: pointer; font-weight: 600; background: #198754; color: #fff; width: 100%; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .error { color: #dc3545; font-size: 13px; margin-bottom: 12px; }
        @media (max-width: 700px) {
            .grid { grid-template-columns: 1fr; }
            body { padding-bottom: 340px; }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Firma del Contrato Marco</h1>
        <p class="meta">
            Firmante autorizado de <strong>{{ $viewData['entityName'] }}</strong>.
            Referencia: <strong>{{ $viewData['contractReference'] }}</strong>
        </p>

        <div class="hint">
            Revise el contrato a continuación. Las líneas de firma del documento aparecerán rellenadas
            <strong>después</strong> de aceptar. Use el panel inferior para confirmar su identidad y firmar.
        </div>

        <p><strong>Contrato Marco de Prestación de Servicios — Versión {{ $viewData['contractVersion'] }}</strong></p>
        <div class="terms">
            @include('contracts.partials.contract_table_styles')
            @include('contracts.entity_framework_content', $viewData)
        </div>
    </div>

    <div class="accept-panel">
        <div class="accept-panel-inner">
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
                    <span>
                        @if (!empty($viewData['isNaturalOrganizer']))
                            Declaro actuar en nombre propio como Organizador y acepto el Contrato Marco de Prestación de Servicios.
                        @else
                            Declaro tener capacidad para representar a la entidad y acepto el Contrato Marco de Prestación de Servicios.
                        @endif
                    </span>
                </label>

                @if (!empty($viewData['isNaturalOrganizer']))
                    <label class="checkbox" style="background:#fff4e5;padding:12px;border-radius:10px;">
                        <input type="checkbox" name="accept_organizer_declaration" value="1" {{ old('accept_organizer_declaration') ? 'checked' : '' }} required>
                        <span><strong>Declaro que actúo en nombre propio y no en representación de ninguna entidad, y asumo personal e ilimitadamente las obligaciones derivadas de este contrato.</strong></span>
                    </label>
                @endif

                <button type="submit">Firmar contrato</button>
            </form>
        </div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aceptación Gestor Responsable - Partilot</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6f7; margin: 0; padding: 24px 16px; color: #212529; }
        .card { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 28px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        h1 { font-size: 24px; margin: 0 0 8px; }
        .meta { color: #6c757d; margin-bottom: 20px; }
        .terms { background: #f8f9fa; border-radius: 12px; padding: 20px; max-height: 520px; overflow-y: auto; font-size: 12px; line-height: 1.55; margin-bottom: 20px; }
        .terms .contract-annex-table { font-size: 11px; }
        .terms .contract-annex-table th { background: #e9ecef; }
        .role-block { background: #fff8f0; border: 2px solid #F59200; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        input[type="text"] { width: 100%; padding: 10px 12px; border: 1px solid #ced4da; border-radius: 8px; margin-bottom: 16px; box-sizing: border-box; }
        .checkbox { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 16px; font-size: 14px; }
        button { border: 0; border-radius: 24px; padding: 12px 28px; font-size: 15px; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #198754; color: #fff; width: 100%; }
        .btn-secondary { background: #6c757d; color: #fff; width: 100%; margin-top: 8px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .error { color: #dc3545; font-size: 13px; margin-bottom: 12px; }
        @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="card">
        <h1>Aceptación del cargo de Gestor Responsable</h1>
        <p class="meta">
            <strong>{{ $viewData['administrationName'] }}</strong> te ha designado Gestor Responsable de
            <strong>{{ $viewData['entityName'] }}</strong>.
            Referencia del contrato: <strong>{{ $viewData['contractReference'] }}</strong>
        </p>

        <div class="role-block">
            <p class="mb-2"><strong>Al aceptar este cargo:</strong></p>
            <ul class="small mb-0">
                @foreach(($invitation['summary_bullets'] ?? []) as $bullet)
                    <li>{{ $bullet }}</li>
                @endforeach
            </ul>
            <p class="small mb-0 mt-2">
                <a href="{{ route('legal.terminos-y-condiciones') }}" target="_blank" rel="noopener">Ver condiciones del cargo</a>
            </p>
        </div>

        <p><strong>Contrato Marco de Prestación de Servicios (B2) — Versión {{ $viewData['contractVersion'] }}</strong></p>
        <div class="terms">
            @include('contracts.partials.contract_table_styles')
            @include('contracts.entity_framework_content', $viewData)
        </div>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('entity-contract.accept-primary.store', $token) }}">
            @csrf
            <input type="hidden" name="action" value="accept">

            <div class="grid">
                <div>
                    <label for="signer_name">Nombre y apellidos del Gestor Responsable</label>
                    <input type="text" id="signer_name" name="signer_name" value="{{ old('signer_name', $viewData['signerName'] !== '—' ? $viewData['signerName'] : '') }}" required>
                </div>
                <div>
                    <label for="signer_nif">DNI / NIE</label>
                    <input type="text" id="signer_nif" name="signer_nif" value="{{ old('signer_nif', $viewData['signerNif'] !== '—' ? $viewData['signerNif'] : '') }}" required>
                </div>
            </div>

            <label class="checkbox">
                <input type="checkbox" name="accept_contract" value="1" {{ old('accept_contract') ? 'checked' : '' }} required>
                <span>Acepto el Contrato Marco de Uso de la Plataforma en nombre de la Entidad y declaro tener capacidad para ello.</span>
            </label>

            <label class="checkbox">
                <input type="checkbox" name="role_terms" value="1" {{ old('role_terms') ? 'checked' : '' }} required>
                <span>Acepto el cargo de Gestor Responsable y las responsabilidades indicadas.</span>
            </label>

            <button type="submit" class="btn-primary">Acepto el cargo y el contrato en nombre de la entidad</button>
        </form>

        <form method="POST" action="{{ route('entity-contract.accept-primary.store', $token) }}">
            @csrf
            <input type="hidden" name="action" value="reject">
            <button type="submit" class="btn-secondary">Rechazar designación</button>
        </form>
    </div>
</body>
</html>

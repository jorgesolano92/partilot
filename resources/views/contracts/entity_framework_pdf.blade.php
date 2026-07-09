<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato Marco Entidad {{ $contractReference }}</title>
    <style>
        @page { margin: 20mm 16mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; line-height: 1.45; color: #111; }
        .contract-document p { margin: 0 0 8px; text-align: justify; }
        .contract-document hr { border: 0; border-top: 1px solid #ccc; margin: 16px 0; }
        strong { font-weight: 700; }
        .contract-annex-table { width: 100%; border-collapse: collapse; margin: 0 0 18px; font-size: 10px; }
        .contract-annex-table th,
        .contract-annex-table td { border: 1px solid #333; padding: 6px 8px; text-align: left; vertical-align: top; }
        .contract-annex-table th { background: #f2f2f2; font-weight: 600; width: 36%; }
        .contract-section-title { font-weight: 700; margin: 16px 0 8px; }
        .contract-signature-box { width: 100%; border-collapse: collapse; margin: 16px 0 24px; }
        .contract-signature-box td { border: 1px solid #333; padding: 12px; vertical-align: top; width: 50%; }
        .contract-signature-line { display: inline-block; min-width: 160px; border-bottom: 1px solid #333; }
    </style>
</head>
<body>
    @include('contracts.entity_framework_content')
</body>
</html>

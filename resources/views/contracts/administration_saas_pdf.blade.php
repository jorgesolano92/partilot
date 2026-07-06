<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato SaaS Administración {{ $contractReference }}</title>
    <style>
        @page { margin: 24mm 18mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; line-height: 1.45; color: #111; }
        .contract-document p { margin: 0 0 8px; text-align: justify; }
        .contract-document hr { border: 0; border-top: 1px solid #ccc; margin: 16px 0; }
        strong { font-weight: 700; }
    </style>
</head>
<body>
    @include('contracts.administration_saas_content')
</body>
</html>

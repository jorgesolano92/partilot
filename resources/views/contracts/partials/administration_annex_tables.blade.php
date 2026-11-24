@php
    $th = 'border:1px solid #333;padding:6px 8px;text-align:left;vertical-align:top;background:#f2f2f2;font-weight:700;width:36%;';
    $td = 'border:1px solid #333;padding:6px 8px;text-align:left;vertical-align:top;';
@endphp

<p class="contract-section-title" style="font-weight:700;margin:16px 0 8px;">ANEXO I — IDENTIFICACIÓN DE LAS PARTES Y CONDICIONES INICIALES</p>
<p>El presente Anexo forma parte integrante del contrato y debe completarse y firmarse conjuntamente con el documento principal.</p>

<p class="contract-section-title" style="font-weight:700;margin:16px 0 8px;">Datos de PARTILOT (Proveedor)</p>
<table class="contract-annex-table" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;margin:0 0 18px;font-size:10px;">
    <tr><th style="{{ $th }}">Razón social</th><td style="{{ $td }}">PARTILOT, SOCIEDAD LIMITADA UNIPERSONAL</td></tr>
    <tr><th style="{{ $th }}">NIF</th><td style="{{ $td }}">B26681551</td></tr>
    <tr><th style="{{ $th }}">Domicilio fiscal</th><td style="{{ $td }}">Calle Lope de Vega, 5, 1º B, 26007 Logroño (La Rioja)</td></tr>
    <tr><th style="{{ $th }}">Registro Mercantil</th><td style="{{ $td }}">Registro Mercantil de La Rioja, Hoja LO-21703, Inscripción 1</td></tr>
    <tr><th style="{{ $th }}">Email legal</th><td style="{{ $td }}">legal@partilot.es</td></tr>
    <tr><th style="{{ $th }}">Email de protección de datos</th><td style="{{ $td }}">lopd@partilot.es</td></tr>
    <tr><th style="{{ $th }}">Email de soporte</th><td style="{{ $td }}">soporte@partilot.es</td></tr>
</table>

<p class="contract-section-title" style="font-weight:700;margin:16px 0 8px;">Datos del Punto de Venta Autorizado (Cliente)</p>
<table class="contract-annex-table" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;margin:0 0 18px;font-size:10px;">
    <tr><th style="{{ $th }}">Modalidad</th><td style="{{ $td }}">{{ $pvaModality ?? 'Administración de Lotería' }}</td></tr>
    <tr><th style="{{ $th }}">Nombre comercial</th><td style="{{ $td }}">{{ $commercialName }}</td></tr>
    @if(!empty($society) && $society !== $commercialName)
        <tr><th style="{{ $th }}">Razón social</th><td style="{{ $td }}">{{ $society }}</td></tr>
    @endif
    <tr><th style="{{ $th }}">Código SELAE</th><td style="{{ $td }}">{{ $selaeCode }}</td></tr>
    <tr><th style="{{ $th }}">Código de receptor</th><td style="{{ $td }}">{{ $receivingCode }}</td></tr>
    <tr><th style="{{ $th }}">NIF / CIF titular</th><td style="{{ $td }}">{{ $nifCif }}</td></tr>
    <tr><th style="{{ $th }}">Domicilio</th><td style="{{ $td }}">{{ $address }}</td></tr>
    <tr><th style="{{ $th }}">Municipio y provincia</th><td style="{{ $td }}">{{ $city }}, {{ $province }}</td></tr>
    <tr><th style="{{ $th }}">Código postal</th><td style="{{ $td }}">{{ $postalCode }}</td></tr>
    <tr><th style="{{ $th }}">Email de contacto</th><td style="{{ $td }}">{{ $email }}</td></tr>
    <tr><th style="{{ $th }}">Teléfono</th><td style="{{ $td }}">{{ $phone }}</td></tr>
    <tr><th style="{{ $th }}">Titular / Representante</th><td style="{{ $td }}">{{ $representativeName }}</td></tr>
    <tr><th style="{{ $th }}">DNI / NIE del titular</th><td style="{{ $td }}">{{ $representativeNif }}</td></tr>
    <tr><th style="{{ $th }}">Fecha de firma</th><td style="{{ $td }}">{{ $acceptanceDate }}</td></tr>
    <tr><th style="{{ $th }}">Marca temporal (timestamp)</th><td style="{{ $td }}">{{ $acceptanceTimestamp }}</td></tr>
    <tr><th style="{{ $th }}">Dirección IP</th><td style="{{ $td }}">{{ $acceptanceIp }}</td></tr>
</table>

<p class="contract-section-title" style="font-weight:700;margin:16px 0 8px;">Configuración inicial del servicio</p>
<table class="contract-annex-table" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;margin:0 0 18px;font-size:10px;">
    <tr><th style="{{ $th }}">Cuota de incorporación</th><td style="{{ $td }}">Sin coste</td></tr>
    <tr><th style="{{ $th }}">Modalidad de cobro habilitada</th><td style="{{ $td }}">{{ $paymentMode }}</td></tr>
    <tr><th style="{{ $th }}">IBAN para domiciliación (si se activa)</th><td style="{{ $td }}">{{ $iban }}</td></tr>
    <tr><th style="{{ $th }}">Switch de facturación por defecto</th><td style="{{ $td }}">{{ $billingSwitchDefault }}</td></tr>
    <tr><th style="{{ $th }}">Tarifa por Set</th><td style="{{ $td }}">Conforme a las tarifas vigentes publicadas en la Plataforma</td></tr>
    <tr><th style="{{ $th }}">Fecha de activación prevista</th><td style="{{ $td }}">{{ $activationDate }}</td></tr>
</table>

<p>Ambas partes declaran que los datos anteriores son correctos y completos.</p>

<p style="font-size:11px;color:#666;">© PARTILOT, S.L.U. — Contrato de Prestación de Servicios SaaS con Punto de Venta Autorizado — Versión {{ $contractVersion }}</p>

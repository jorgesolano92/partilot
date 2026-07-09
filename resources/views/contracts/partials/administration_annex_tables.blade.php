<p class="contract-section-title">ANEXO I — IDENTIFICACIÓN DE LAS PARTES Y CONDICIONES INICIALES</p>
<p>El presente Anexo forma parte integrante del contrato y debe completarse y firmarse conjuntamente con el documento principal.</p>

<p class="contract-section-title">Datos de PARTILOT (Proveedor)</p>
<table class="contract-annex-table">
    <tr><th>Razón social</th><td>PARTILOT, SOCIEDAD LIMITADA UNIPERSONAL</td></tr>
    <tr><th>NIF</th><td>B26681551</td></tr>
    <tr><th>Domicilio fiscal</th><td>Calle Lope de Vega, 5, 1º B, 26007 Logroño (La Rioja)</td></tr>
    <tr><th>Registro Mercantil</th><td>Registro Mercantil de La Rioja, Hoja LO-21703, Inscripción 1</td></tr>
    <tr><th>Email legal</th><td>legal@partilot.es</td></tr>
    <tr><th>Email de soporte</th><td>soporte@partilot.es</td></tr>
</table>

<p class="contract-section-title">Datos de la Administración de Lotería (Cliente)</p>
<table class="contract-annex-table">
    <tr><th>Nombre comercial</th><td>{{ $commercialName }}</td></tr>
    @if(!empty($society) && $society !== $commercialName)
        <tr><th>Razón social</th><td>{{ $society }}</td></tr>
    @endif
    <tr><th>Código SELAE</th><td>{{ $selaeCode }}</td></tr>
    <tr><th>Código de receptor</th><td>{{ $receivingCode }}</td></tr>
    <tr><th>NIF / CIF titular</th><td>{{ $nifCif }}</td></tr>
    <tr><th>Domicilio</th><td>{{ $address }}</td></tr>
    <tr><th>Municipio y provincia</th><td>{{ $city }}, {{ $province }}</td></tr>
    <tr><th>Código postal</th><td>{{ $postalCode }}</td></tr>
    <tr><th>Email de contacto</th><td>{{ $email }}</td></tr>
    <tr><th>Teléfono</th><td>{{ $phone }}</td></tr>
    <tr><th>Titular / Representante</th><td>{{ $representativeName }}</td></tr>
    <tr><th>DNI / NIE del titular</th><td>{{ $representativeNif }}</td></tr>
</table>

<p class="contract-section-title">Configuración inicial del servicio</p>
<table class="contract-annex-table">
    <tr><th>Cuota de incorporación</th><td>Sin coste</td></tr>
    <tr><th>Modalidad de cobro habilitada</th><td>{{ $paymentMode }}</td></tr>
    <tr><th>IBAN para domiciliación (si se activa)</th><td>{{ $iban }}</td></tr>
    <tr><th>Switch de facturación por defecto</th><td>{{ $billingSwitchDefault }}</td></tr>
    <tr><th>Tarifa por set</th><td>Conforme a las tarifas vigentes publicadas en la Plataforma</td></tr>
    <tr><th>Fecha de activación prevista</th><td>{{ $activationDate }}</td></tr>
</table>

<p>Ambas partes declaran que los datos anteriores son correctos y completos.</p>

<p style="font-size:11px;color:#666;">© PARTILOT, S.L.U. — Contrato de Prestación de Servicios SaaS con Administración de Lotería — Versión {{ $contractVersion }}</p>

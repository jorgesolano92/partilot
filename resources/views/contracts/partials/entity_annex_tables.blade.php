<p class="contract-section-title">ANEXO I — IDENTIFICACIÓN DE LAS PARTES Y CONDICIONES INICIALES</p>
<p>El presente Anexo forma parte integrante del contrato y se completa con los datos registrados en la Plataforma en el momento de la aceptación.</p>

<p class="contract-section-title">Modalidad de contratación</p>
<table class="contract-annex-table">
    <tr><th>Modalidad aplicable</th><td>{{ $contractModalityLabel }}</td></tr>
    <tr><th>Referencia</th><td>{{ $contractReference }}</td></tr>
    <tr><th>Versión del contrato</th><td>{{ $contractVersion }}</td></tr>
</table>

<p class="contract-section-title">Datos de PARTILOT</p>
<table class="contract-annex-table">
    <tr><th>Razón social</th><td>PARTILOT, SOCIEDAD LIMITADA UNIPERSONAL</td></tr>
    <tr><th>NIF</th><td>B26681551</td></tr>
    <tr><th>Domicilio fiscal</th><td>Calle Lope de Vega, 5, 1º B, 26007 Logroño (La Rioja)</td></tr>
    <tr><th>Registro Mercantil</th><td>Registro Mercantil de La Rioja, Hoja LO-21703, Inscripción 1</td></tr>
    <tr><th>Email legal</th><td>legal@partilot.es</td></tr>
    <tr><th>Email de protección de datos</th><td>lopd@partilot.es</td></tr>
    <tr><th>Email de soporte</th><td>soporte@partilot.es</td></tr>
</table>

<p class="contract-section-title">Datos de la Entidad / Organizador</p>
<table class="contract-annex-table">
    <tr><th>Nombre</th><td>{{ $entityName }}</td></tr>
    <tr><th>CIF / NIF</th><td>{{ $entityNif }}</td></tr>
    <tr><th>Domicilio</th><td>{{ $entityFullAddress }}</td></tr>
    <tr><th>Email de contacto</th><td>{{ $entityEmail }}</td></tr>
    <tr><th>Teléfono</th><td>{{ $entityPhone }}</td></tr>
    <tr><th>Punto de Venta Autorizado vinculado</th><td>{{ $administrationLinked }}</td></tr>
</table>

<p class="contract-section-title">Datos del Firmante Autorizado{{ !empty($isNaturalOrganizer) ? ' / Organizador' : '' }}</p>
<table class="contract-annex-table">
    <tr><th>Nombre y apellidos</th><td>{{ $signerName }}</td></tr>
    <tr><th>DNI / NIE</th><td>{{ $signerNif }}</td></tr>
    <tr><th>Cargo / capacidad</th><td>{{ $signerRoleLabel }}</td></tr>
    <tr><th>Email registrado</th><td>{{ $signerEmail }}</td></tr>
    <tr><th>Fecha de aceptación</th><td>{{ $acceptanceDate }}</td></tr>
    <tr><th>Marca temporal (timestamp)</th><td>{{ $acceptanceTimestamp }}</td></tr>
    <tr><th>Dirección IP</th><td>{{ $acceptanceIp }}</td></tr>
</table>

<p class="contract-section-title">Datos del Gestor Responsable designado</p>
<table class="contract-annex-table">
    <tr><th>Nombre y apellidos</th><td>{{ $managerName }}</td></tr>
    <tr><th>DNI / NIE</th><td>{{ $managerNif }}</td></tr>
    <tr><th>Email registrado</th><td>{{ $managerEmail }}</td></tr>
</table>

<p class="contract-section-title">Configuración inicial</p>
<table class="contract-annex-table">
    <tr><th>Espacio web de la Entidad</th><td>{{ $entityWebStatus }}</td></tr>
    <tr><th>Cuota de gestión por defecto a cargo de</th><td>{{ $managementFeePayer }}</td></tr>
    <tr><th>Servicio de gestión de pagos de premios</th><td>{{ $prizePaymentStatus }}</td></tr>
    <tr><th>Facultad de firma del Mandato de Pago delegada en el Gestor Responsable</th><td>{{ $mandateDelegation }}</td></tr>
</table>

<p style="font-size:11px;color:#666;">© PARTILOT, S.L.U. — Contrato Marco de Prestación de Servicios con la Entidad — Versión {{ $contractVersion }}</p>

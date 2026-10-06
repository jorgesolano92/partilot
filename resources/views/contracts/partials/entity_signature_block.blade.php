<p style="text-align:center;"><strong>FIRMA DEL CONTRATO</strong></p>

<p>En prueba de conformidad con todo lo anterior, ambas partes suscriben el presente contrato en la fecha y lugar indicados en el Anexo I, habiendo leído íntegramente su contenido y entendido su alcance y consecuencias.</p>

<p>La firma electrónica del presente documento a través del sistema habilitado por PARTILOT tiene plena validez jurídica conforme al Reglamento (UE) nº 910/2014 y la Ley 6/2020, de 11 de noviembre. El sistema registrará la identidad del firmante, la marca temporal, la dirección IP y la versión del documento suscrito. Ambas partes recibirán copia del documento firmado en su correo electrónico registrado.</p>

<table class="contract-signature-box">
    <tr>
        <td>
            <strong>Por PARTILOT, S.L.U.</strong><br><br>
            Nombre y apellidos:
            @if($isSigned)
                {{ $partilotSignerName }}
            @else
                <span class="contract-signature-line">&nbsp;</span>
            @endif
            <br><br>
            Cargo:
            @if($isSigned)
                {{ $partilotSignerRole }}
            @else
                <span class="contract-signature-line">&nbsp;</span>
            @endif
            <br><br>
            Fecha:
            @if($isSigned)
                {{ $acceptanceDate }}
            @else
                <span class="contract-signature-line">&nbsp;</span>
            @endif
        </td>
        <td>
            <strong>Por el Firmante Autorizado / Organizador</strong><br>
            <span style="font-size:10px;">(según modalidad del Anexo I)</span><br><br>
            Nombre y apellidos:
            @if($isSigned)
                {{ $signerName }}
            @else
                <span class="contract-signature-line">&nbsp;</span>
            @endif
            <br><br>
            DNI / NIE:
            @if($isSigned)
                {{ $signerNif }}
            @else
                <span class="contract-signature-line">&nbsp;</span>
            @endif
            <br><br>
            Fecha:
            @if($isSigned)
                {{ $acceptanceDate }}
            @else
                <span class="contract-signature-line">&nbsp;</span>
            @endif
        </td>
    </tr>
</table>

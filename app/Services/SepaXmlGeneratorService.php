<?php

namespace App\Services;

use App\Models\SepaPaymentOrder;
use App\Support\SepaText;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;

class SepaXmlGeneratorService
{
    /**
     * BIC conocidos por código de entidad español (posiciones 5-8 del IBAN).
     * Si no hay match se usa NOTPROVIDED.
     */
    private const BANK_BIC_BY_ENTITY = [
        '0049' => 'BSCHESMMXXX', // Santander
        '0075' => 'POPUESMMXXX', // Popular (legado)
        '0081' => 'BSABESBBXXX', // Sabadell
        '0128' => 'BKBKESMMXXX', // Bankinter
        '0182' => 'BBVAESMMXXX', // BBVA
        '1465' => 'INGDESMMXXX', // ING
        '2080' => 'CAGLESMMXXX', // Abanca
        '2085' => 'CAYHES2AXXX', // Liberbank (Unicaja)
        '2100' => 'CAIXESBBXXX', // CaixaBank
        '2103' => 'UCJAES2MXXX', // Unicaja
        '0239' => 'EVOCESMMXXX', // Evo
        '2038' => 'CAHMESMMXXX', // Bankia (legado)
        '2095' => 'BASKES2BXXX', // Kutxabank
        '3058' => 'CCRIES2AXXX', // Cajamar
    ];

    /**
     * Generar XML SEPA pain.001.001.03 desde una orden de pago
     */
    public function generateXml(SepaPaymentOrder $order): string
    {
        $beneficiaries = $order->beneficiaries
            ->filter(fn ($beneficiary) => $beneficiary->isExportableToSepa())
            ->values();

        if ($beneficiaries->isEmpty()) {
            throw new \RuntimeException('No hay beneficiarios para incluir en el XML.');
        }

        $controlSum = (float) $beneficiaries->sum('amount');
        $numberOfTransactions = $beneficiaries->count();
        $executionDate = $this->resolveExecutionDate($order->execution_date);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Root element
        $document = $dom->createElementNS('urn:iso:std:iso:20022:tech:xsd:pain.001.001.03', 'Document');
        $document->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.03');
        $dom->appendChild($document);

        // CstmrCdtTrfInitn
        $cstmrCdtTrfInitn = $dom->createElement('CstmrCdtTrfInitn');
        $document->appendChild($cstmrCdtTrfInitn);

        // GrpHdr (Group Header)
        $grpHdr = $dom->createElement('GrpHdr');
        $cstmrCdtTrfInitn->appendChild($grpHdr);

        $grpHdr->appendChild($dom->createElement('MsgId', $order->message_id));
        $grpHdr->appendChild($dom->createElement('CreDtTm', now()->format('Y-m-d\TH:i:s')));
        $grpHdr->appendChild($dom->createElement('NbOfTxs', (string) $numberOfTransactions));
        $grpHdr->appendChild($dom->createElement('CtrlSum', number_format($controlSum, 2, '.', '')));

        // InitgPty (Initiating Party)
        $initgPty = $dom->createElement('InitgPty');
        $grpHdr->appendChild($initgPty);

        $initgPty->appendChild($dom->createElement('Nm', $this->escapeXml($order->debtor_name)));

        if ($order->debtor_nif_cif) {
            $id = $dom->createElement('Id');
            $initgPty->appendChild($id);

            // Empresa / CIF → OrgId; persona física → PrvtId
            $idContainer = $this->looksLikeCompanyTaxId($order->debtor_nif_cif)
                ? $dom->createElement('OrgId')
                : $dom->createElement('PrvtId');
            $id->appendChild($idContainer);

            $othr = $dom->createElement('Othr');
            $idContainer->appendChild($othr);

            $othr->appendChild($dom->createElement('Id', $order->debtor_nif_cif));
        }

        // PmtInf (Payment Information)
        $pmtInf = $dom->createElement('PmtInf');
        $cstmrCdtTrfInitn->appendChild($pmtInf);

        $pmtInf->appendChild($dom->createElement('PmtInfId', $order->payment_info_id));
        $pmtInf->appendChild($dom->createElement('PmtMtd', 'TRF'));
        $pmtInf->appendChild($dom->createElement('BtchBookg', $order->batch_booking ? 'true' : 'false'));

        // PmtTpInf
        $pmtTpInf = $dom->createElement('PmtTpInf');
        $pmtInf->appendChild($pmtTpInf);

        $svcLvl = $dom->createElement('SvcLvl');
        $pmtTpInf->appendChild($svcLvl);
        $svcLvl->appendChild($dom->createElement('Cd', 'SEPA'));

        // ReqdExctnDt (nunca en pasado al generar)
        $pmtInf->appendChild($dom->createElement('ReqdExctnDt', $executionDate->format('Y-m-d')));

        // Dbtr (Debtor) — Ctry ES siempre (bancos españoles lo exigen)
        $dbtr = $dom->createElement('Dbtr');
        $pmtInf->appendChild($dbtr);

        $dbtr->appendChild($dom->createElement('Nm', $this->escapeXml($order->debtor_name)));

        $pstlAdr = $dom->createElement('PstlAdr');
        $dbtr->appendChild($pstlAdr);
        $pstlAdr->appendChild($dom->createElement('Ctry', 'ES'));
        if ($order->debtor_address) {
            $pstlAdr->appendChild($dom->createElement('AdrLine', $this->escapeXml($order->debtor_address)));
        }

        // DbtrAcct (Debtor Account)
        $dbtrAcct = $dom->createElement('DbtrAcct');
        $pmtInf->appendChild($dbtrAcct);

        $dbtrAcctId = $dom->createElement('Id');
        $dbtrAcct->appendChild($dbtrAcctId);
        $dbtrAcctId->appendChild($dom->createElement('IBAN', $order->debtor_iban));

        // DbtrAgt: BIC real si se puede inferir del IBAN; si no, NOTPROVIDED
        $dbtrAgt = $dom->createElement('DbtrAgt');
        $pmtInf->appendChild($dbtrAgt);
        $dbtrAgt->appendChild($this->finInstnIdForIban($dom, $order->debtor_iban));

        // ChrgBr
        $pmtInf->appendChild($dom->createElement('ChrgBr', $order->charge_bearer));

        // CdtTrfTxInf (Credit Transfer Transaction Information) - Para cada beneficiario exportable
        foreach ($beneficiaries as $beneficiary) {
            $cdtTrfTxInf = $dom->createElement('CdtTrfTxInf');
            $pmtInf->appendChild($cdtTrfTxInf);

            // PmtId
            $pmtId = $dom->createElement('PmtId');
            $cdtTrfTxInf->appendChild($pmtId);
            $pmtId->appendChild($dom->createElement('EndToEndId', $beneficiary->end_to_end_id));

            // Amt
            $amt = $dom->createElement('Amt');
            $cdtTrfTxInf->appendChild($amt);

            $instdAmt = $dom->createElement('InstdAmt');
            $amt->appendChild($instdAmt);
            $instdAmt->setAttribute('Ccy', $beneficiary->currency);
            // El texto debe ir como nodo hijo, no como atributo
            $instdAmt->nodeValue = number_format($beneficiary->amount, 2, '.', '');

            // Cdtr (Creditor)
            $cdtr = $dom->createElement('Cdtr');
            $cdtTrfTxInf->appendChild($cdtr);

            $cdtr->appendChild($dom->createElement('Nm', $this->escapeXml($beneficiary->creditor_name)));

            if ($beneficiary->creditor_nif_cif) {
                $cdtrId = $dom->createElement('Id');
                $cdtr->appendChild($cdtrId);

                $cdtrIdContainer = $this->looksLikeCompanyTaxId($beneficiary->creditor_nif_cif)
                    ? $dom->createElement('OrgId')
                    : $dom->createElement('PrvtId');
                $cdtrId->appendChild($cdtrIdContainer);

                $cdtrOthr = $dom->createElement('Othr');
                $cdtrIdContainer->appendChild($cdtrOthr);

                $cdtrOthr->appendChild($dom->createElement('Id', $beneficiary->creditor_nif_cif));
            }

            // CdtrAcct (Creditor Account)
            $cdtrAcct = $dom->createElement('CdtrAcct');
            $cdtTrfTxInf->appendChild($cdtrAcct);

            $cdtrAcctId = $dom->createElement('Id');
            $cdtrAcct->appendChild($cdtrAcctId);
            $cdtrAcctId->appendChild($dom->createElement('IBAN', $beneficiary->creditor_iban));

            // Purp (Purpose)
            if ($beneficiary->purpose_code) {
                $purp = $dom->createElement('Purp');
                $cdtTrfTxInf->appendChild($purp);
                $purp->appendChild($dom->createElement('Cd', $beneficiary->purpose_code));
            }

            // RmtInf (Remittance Information)
            if ($beneficiary->remittance_info) {
                $rmtInf = $dom->createElement('RmtInf');
                $cdtTrfTxInf->appendChild($rmtInf);
                $rmtInf->appendChild($dom->createElement('Ustrd', $this->escapeXml($beneficiary->remittance_info, 140)));
            }
        }

        return $dom->saveXML();
    }

    /**
     * Guardar XML en archivo
     */
    public function saveXmlToFile(SepaPaymentOrder $order, string $xmlContent, string $directory = 'sepa_payments'): string
    {
        $filename = $order->message_id . '.xml';
        $path = storage_path("app/{$directory}");
        
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        $fullPath = $path . '/' . $filename;
        file_put_contents($fullPath, $xmlContent);

        // Actualizar el nombre del archivo en la orden
        $order->update(['xml_filename' => $filename, 'status' => 'descargado']);

        return $fullPath;
    }

    private function escapeXml(?string $string, int $maxLength = 70): string
    {
        return htmlspecialchars(SepaText::clean($string, $maxLength), ENT_XML1, 'UTF-8');
    }

    /**
     * Si la fecha de ejecución está en el pasado, usar hoy (día hábil no se fuerza aquí).
     */
    private function resolveExecutionDate(mixed $executionDate): Carbon
    {
        $date = $executionDate instanceof Carbon
            ? $executionDate->copy()->startOfDay()
            : Carbon::parse($executionDate)->startOfDay();

        $today = now()->startOfDay();

        return $date->lt($today) ? $today : $date;
    }

    /**
     * CIF español típico: letra + 7-8 dígitos (+ letra opcional). NIF persona: 8 dígitos + letra.
     */
    private function looksLikeCompanyTaxId(?string $taxId): bool
    {
        if ($taxId === null || $taxId === '') {
            return false;
        }

        $clean = strtoupper(preg_replace('/[\s\-]/', '', $taxId) ?? '');

        return (bool) preg_match('/^[ABCDEFGHJNPQRSUVW]\d{7}[0-9A-J]?$/', $clean);
    }

    private function finInstnIdForIban(DOMDocument $dom, ?string $iban): DOMElement
    {
        $bic = $this->bicFromSpanishIban($iban);

        $finInstnId = $dom->createElement('FinInstnId');
        if ($bic) {
            $finInstnId->appendChild($dom->createElement('BIC', $bic));
        } else {
            $othr = $dom->createElement('Othr');
            $othr->appendChild($dom->createElement('Id', 'NOTPROVIDED'));
            $finInstnId->appendChild($othr);
        }

        return $finInstnId;
    }

    private function bicFromSpanishIban(?string $iban): ?string
    {
        if ($iban === null || $iban === '') {
            return null;
        }

        $clean = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
        if (! str_starts_with($clean, 'ES') || strlen($clean) < 8) {
            return null;
        }

        $entity = substr($clean, 4, 4);

        return self::BANK_BIC_BY_ENTITY[$entity] ?? null;
    }

    /**
     * Generar y guardar XML
     */
    public function generateAndSave(SepaPaymentOrder $order, string $directory = 'sepa_payments'): string
    {
        $xmlContent = $this->generateXml($order);
        return $this->saveXmlToFile($order, $xmlContent, $directory);
    }
}

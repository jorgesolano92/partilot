<?php

namespace App\Services;

use App\Models\BillingDirectDebitOrder;
use DOMDocument;

class BillingDirectDebitXmlGeneratorService
{
    public function generateXml(BillingDirectDebitOrder $order): string
    {
        $order->load('charges');
        $charges = $order->charges;

        if ($charges->isEmpty()) {
            throw new \RuntimeException('No hay cargos para incluir en el XML de adeudo.');
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $document = $dom->createElementNS('urn:iso:std:iso:20022:tech:xsd:pain.008.001.02', 'Document');
        $document->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02');
        $dom->appendChild($document);

        $initn = $dom->createElement('CstmrDrctDbtInitn');
        $document->appendChild($initn);

        $grpHdr = $dom->createElement('GrpHdr');
        $initn->appendChild($grpHdr);
        $grpHdr->appendChild($dom->createElement('MsgId', $order->message_id));
        $grpHdr->appendChild($dom->createElement('CreDtTm', $order->creation_date->format('Y-m-d\TH:i:s')));
        $grpHdr->appendChild($dom->createElement('NbOfTxs', (string) $charges->count()));
        $grpHdr->appendChild($dom->createElement('CtrlSum', number_format((float) $order->control_sum, 2, '.', '')));

        $initgPty = $dom->createElement('InitgPty');
        $grpHdr->appendChild($initgPty);
        $initgPty->appendChild($dom->createElement('Nm', $this->escapeXml($order->creditor_name)));

        $pmtInf = $dom->createElement('PmtInf');
        $initn->appendChild($pmtInf);

        $pmtInf->appendChild($dom->createElement('PmtInfId', $order->payment_info_id));
        $pmtInf->appendChild($dom->createElement('PmtMtd', 'DD'));
        $pmtInf->appendChild($dom->createElement('BtchBookg', 'true'));
        $pmtInf->appendChild($dom->createElement('NbOfTxs', (string) $charges->count()));
        $pmtInf->appendChild($dom->createElement('CtrlSum', number_format((float) $order->control_sum, 2, '.', '')));

        $pmtTpInf = $dom->createElement('PmtTpInf');
        $pmtInf->appendChild($pmtTpInf);
        $svcLvl = $dom->createElement('SvcLvl');
        $pmtTpInf->appendChild($svcLvl);
        $svcLvl->appendChild($dom->createElement('Cd', 'SEPA'));
        $lclInstrm = $dom->createElement('LclInstrm');
        $pmtTpInf->appendChild($lclInstrm);
        $lclInstrm->appendChild($dom->createElement('Cd', 'CORE'));
        $pmtTpInf->appendChild($dom->createElement('SeqTp', $order->sequence_type));

        $pmtInf->appendChild($dom->createElement('ReqdColltnDt', $order->collection_date->format('Y-m-d')));

        $cdtr = $dom->createElement('Cdtr');
        $pmtInf->appendChild($cdtr);
        $cdtr->appendChild($dom->createElement('Nm', $this->escapeXml($order->creditor_name)));

        $cdtrAcct = $dom->createElement('CdtrAcct');
        $pmtInf->appendChild($cdtrAcct);
        $cdtrAcctId = $dom->createElement('Id');
        $cdtrAcct->appendChild($cdtrAcctId);
        $cdtrAcctId->appendChild($dom->createElement('IBAN', $order->creditor_iban));

        $cdtrAgt = $dom->createElement('CdtrAgt');
        $pmtInf->appendChild($cdtrAgt);
        $cdtrAgt->appendChild($dom->createElement('FinInstnId'));

        $cdtrSchmeId = $dom->createElement('CdtrSchmeId');
        $pmtInf->appendChild($cdtrSchmeId);
        $cdtrSchmeIdId = $dom->createElement('Id');
        $cdtrSchmeId->appendChild($cdtrSchmeIdId);
        $prvtId = $dom->createElement('PrvtId');
        $cdtrSchmeIdId->appendChild($prvtId);
        $othr = $dom->createElement('Othr');
        $prvtId->appendChild($othr);
        $othr->appendChild($dom->createElement('Id', $order->creditor_scheme_id));
        $schmeNm = $dom->createElement('SchmeNm');
        $othr->appendChild($schmeNm);
        $schmeNm->appendChild($dom->createElement('Prtry', 'SEPA'));

        $pmtInf->appendChild($dom->createElement('ChrgBr', 'SLEV'));

        foreach ($charges as $index => $charge) {
            $drctDbtTxInf = $dom->createElement('DrctDbtTxInf');
            $pmtInf->appendChild($drctDbtTxInf);

            $pmtId = $dom->createElement('PmtId');
            $drctDbtTxInf->appendChild($pmtId);
            $endToEndId = 'CHG'.$charge->id.'-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $pmtId->appendChild($dom->createElement('EndToEndId', $endToEndId));

            $instdAmt = $dom->createElement('InstdAmt', number_format((float) $charge->amount, 2, '.', ''));
            $instdAmt->setAttribute('Ccy', $charge->currency ?: 'EUR');
            $drctDbtTxInf->appendChild($instdAmt);

            $drctDbtTx = $dom->createElement('DrctDbtTx');
            $drctDbtTxInf->appendChild($drctDbtTx);
            $mndtRltdInf = $dom->createElement('MndtRltdInf');
            $drctDbtTx->appendChild($mndtRltdInf);
            $mndtRltdInf->appendChild($dom->createElement('MndtId', $order->debtor_mandate_id));
            $mndtRltdInf->appendChild($dom->createElement('DtOfSgntr', $order->debtor_mandate_signed_at->format('Y-m-d')));

            $dbtrAgt = $dom->createElement('DbtrAgt');
            $drctDbtTxInf->appendChild($dbtrAgt);
            $dbtrAgt->appendChild($dom->createElement('FinInstnId'));

            $dbtr = $dom->createElement('Dbtr');
            $drctDbtTxInf->appendChild($dbtr);
            $dbtr->appendChild($dom->createElement('Nm', $this->escapeXml($order->debtor_name)));

            $dbtrAcct = $dom->createElement('DbtrAcct');
            $drctDbtTxInf->appendChild($dbtrAcct);
            $dbtrAcctId = $dom->createElement('Id');
            $dbtrAcct->appendChild($dbtrAcctId);
            $dbtrAcctId->appendChild($dom->createElement('IBAN', $order->debtor_iban));

            $rmtInf = $dom->createElement('RmtInf');
            $drctDbtTxInf->appendChild($rmtInf);
            $description = $charge->description ?: $charge->conceptLabel();
            $rmtInf->appendChild($dom->createElement('Ustrd', $this->escapeXml(mb_substr($description, 0, 140))));
        }

        return $dom->saveXML();
    }

    public function saveXmlToFile(BillingDirectDebitOrder $order, string $xmlContent, string $directory = 'billing_direct_debits'): string
    {
        $filename = $order->message_id.'.xml';
        $path = storage_path("app/{$directory}");

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $fullPath = $path.'/'.$filename;
        file_put_contents($fullPath, $xmlContent);

        return $fullPath;
    }

    private function escapeXml(string $string): string
    {
        return htmlspecialchars($string, ENT_XML1, 'UTF-8');
    }
}

<?php

namespace App\Support\InvoiceScan;

use Carbon\CarbonImmutable;
use horstoeko\zugferd\ZugferdDocumentPdfReader;
use horstoeko\zugferd\ZugferdDocumentReader;
use Throwable;

/**
 * Stufe 1 der Scan-Leiter: E-Rechnungen (ZUGFeRD/Factur-X/XRechnung im
 * PDF eingebettet) tragen alle Daten als XML — die Auslese ist exakt,
 * kostenlos und braucht keine KI. Je nach Belegart ist der gesuchte
 * Partner der Verkäufer (Eingangsrechnung) oder der Käufer (eigene
 * Ausgangsrechnung/Angebot). PDFs ohne eingebettetes XML liefern null
 * und fallen auf die nächste Stufe.
 */
class ERechnungReader
{
    public function read(string $pdfContent, ScanDocumentKind $kind = ScanDocumentKind::IncomingInvoice): ?ScannedInvoice
    {
        try {
            $reader = ZugferdDocumentPdfReader::readAndGuessFromContent($pdfContent);
        } catch (Throwable) {
            return null;
        }

        try {
            return $this->extract($reader, $kind);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function extract(ZugferdDocumentReader $reader, ScanDocumentKind $kind): ScannedInvoice
    {
        $documentNo = $typeCode = $currency = $taxCurrency = $documentName = $language = null;
        $documentDate = $period = null;
        $reader->getDocumentInformation($documentNo, $typeCode, $documentDate, $currency, $taxCurrency, $documentName, $language, $period);

        $partnerName = $partnerDescription = null;
        $partnerIds = $taxRegistrations = null;

        if ($kind->partnerIsSeller()) {
            $reader->getDocumentSeller($partnerName, $partnerIds, $partnerDescription);
            $reader->getDocumentSellerTaxRegistration($taxRegistrations);
        } else {
            $reader->getDocumentBuyer($partnerName, $partnerIds, $partnerDescription);
            $reader->getDocumentBuyerTaxRegistration($taxRegistrations);
        }

        $grandTotal = $duePayable = $lineTotal = $chargeTotal = $allowanceTotal = $taxBasisTotal = $taxTotal = $rounding = $prepaid = null;
        $reader->getDocumentSummation($grandTotal, $duePayable, $lineTotal, $chargeTotal, $allowanceTotal, $taxBasisTotal, $taxTotal, $rounding, $prepaid);

        $vatRate = null;
        $reverseCharge = false;

        if ($reader->firstDocumentTax()) {
            $categoryCode = $taxTypeCode = $exemptionReason = $exemptionReasonCode = $dueDateTypeCode = null;
            $basisAmount = $calculatedAmount = $rateApplicablePercent = $lineTotalBasis = $allowanceChargeBasis = null;
            $taxPointDate = null;
            $reader->getDocumentTax($categoryCode, $taxTypeCode, $basisAmount, $calculatedAmount, $rateApplicablePercent, $exemptionReason, $exemptionReasonCode, $lineTotalBasis, $allowanceChargeBasis, $taxPointDate, $dueDateTypeCode);

            $vatRate = $rateApplicablePercent;
            // Steuerkategorie AE = Übergang der Steuerschuld (Reverse Charge).
            $reverseCharge = $categoryCode === 'AE';
        }

        $iban = null;

        // Die Empfänger-IBAN auf dem Beleg ist die des Verkäufers — nur
        // bei Eingangsrechnungen ist das der gesuchte Partner.
        if ($kind->partnerIsSeller() && $reader->firstGetDocumentPaymentMeans()) {
            $meansType = $information = $cardType = $cardId = $cardHolder = $buyerIban = $payeeIban = $payeeAccountName = $payeePropId = $payeeBic = null;
            $reader->getDocumentPaymentMeans($meansType, $information, $cardType, $cardId, $cardHolder, $buyerIban, $payeeIban, $payeeAccountName, $payeePropId, $payeeBic);
            $iban = $payeeIban !== null && $payeeIban !== '' ? $payeeIban : null;
        }

        $docDate = $documentDate !== null ? CarbonImmutable::instance($documentDate)->toDateString() : null;
        [$paymentTargetDays, $skontoPercent, $skontoDays] = $this->paymentTerms($reader, $docDate);

        return new ScannedInvoice(
            partnerName: $partnerName !== null && trim($partnerName) !== '' ? trim($partnerName) : null,
            partnerUid: $this->vatIdFrom($taxRegistrations),
            partnerIban: $iban,
            paymentTargetDays: $paymentTargetDays,
            skontoPercent: $skontoPercent,
            skontoDays: $skontoDays,
            docNumber: $documentNo,
            docDate: $docDate,
            net: $taxBasisTotal,
            vatRate: $vatRate,
            gross: $grandTotal,
            reverseCharge: $reverseCharge,
            subject: $documentName !== null && trim($documentName) !== '' && strtolower(trim($documentName)) !== 'rechnung' ? trim($documentName) : null,
        );
    }

    /**
     * Zahlungsziel aus dem Fälligkeitsdatum, Skonto aus dem Freitext der
     * Zahlungsbedingungen (die Muster teilt sich die Stufe mit dem
     * Text-Parser).
     *
     * @return array{0: int|null, 1: float|null, 2: int|null}
     */
    private function paymentTerms(ZugferdDocumentReader $reader, ?string $invoiceDate): array
    {
        if (! $reader->firstDocumentPaymentTerms()) {
            return [null, null, null];
        }

        $description = $mandateId = null;
        $dueDate = null;
        $reader->getDocumentPaymentTerm($description, $dueDate, $mandateId);

        $paymentTargetDays = null;

        if ($dueDate !== null && $invoiceDate !== null) {
            $days = CarbonImmutable::parse($invoiceDate)->diffInDays(CarbonImmutable::instance($dueDate), false);
            $paymentTargetDays = $days >= 0 && $days <= 365 ? (int) $days : null;
        }

        $skonto = ['percent' => null, 'days' => null];

        if (is_string($description) && $description !== '') {
            $skonto = TextInvoiceParser::skonto($description);
            $paymentTargetDays ??= TextInvoiceParser::paymentTargetDays($description, $invoiceDate);
        }

        return [$paymentTargetDays, $skonto['percent'], $skonto['days']];
    }

    /**
     * @param  array<int|string, mixed>|null  $taxRegistrations
     */
    private function vatIdFrom(?array $taxRegistrations): ?string
    {
        foreach ($taxRegistrations ?? [] as $key => $value) {
            // Schlüssel VA = Umsatzsteuer-ID; zur Sicherheit auch Werte
            // im UID-Format akzeptieren, falls der Schlüssel fehlt.
            if ($key === 'VA' && is_string($value) && $value !== '') {
                return strtoupper((string) preg_replace('/\s+/', '', $value));
            }

            if (is_string($value) && preg_match('/^(ATU\d{8}|DE\d{9})$/i', (string) preg_replace('/\s+/', '', $value)) === 1) {
                return strtoupper((string) preg_replace('/\s+/', '', $value));
            }
        }

        return null;
    }
}

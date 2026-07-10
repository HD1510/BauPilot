<?php

namespace App\Support\InvoiceScan;

/**
 * Ergebnis der KI-Auslese einer Eingangsrechnung: Lieferant, Konditionen
 * und Rechnungskopf. Alles optional — was das Dokument nicht hergibt,
 * bleibt null und wird im Formular von Hand ergänzt.
 */
final readonly class ScannedInvoice
{
    public function __construct(
        public ?string $supplierName = null,
        public ?string $supplierUid = null,
        public ?string $supplierIban = null,
        public ?int $paymentTargetDays = null,
        public ?float $skontoPercent = null,
        public ?int $skontoDays = null,
        public ?string $supplierInvoiceNo = null,
        public ?string $invoiceDate = null,
        public ?float $net = null,
        public ?float $vatRate = null,
        public ?float $gross = null,
        public bool $reverseCharge = false,
        public ?string $subject = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $string = fn (string $key): ?string => is_string($data[$key] ?? null) && trim((string) $data[$key]) !== ''
            ? trim((string) $data[$key])
            : null;
        $number = fn (string $key): ?float => is_numeric($data[$key] ?? null) ? (float) $data[$key] : null;
        $int = fn (string $key): ?int => is_numeric($data[$key] ?? null) ? (int) $data[$key] : null;

        $date = $string('invoice_date');

        return new self(
            supplierName: $string('supplier_name'),
            supplierUid: $string('supplier_uid'),
            supplierIban: $string('supplier_iban'),
            paymentTargetDays: $int('payment_target_days'),
            skontoPercent: $number('skonto_percent'),
            skontoDays: $int('skonto_days'),
            supplierInvoiceNo: $string('supplier_invoice_no'),
            invoiceDate: $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null,
            net: $number('net'),
            vatRate: $number('vat_rate'),
            gross: $number('gross'),
            reverseCharge: (bool) ($data['reverse_charge'] ?? false),
            subject: $string('subject'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'supplier_name' => $this->supplierName,
            'supplier_uid' => $this->supplierUid,
            'supplier_iban' => $this->supplierIban,
            'payment_target_days' => $this->paymentTargetDays,
            'skonto_percent' => $this->skontoPercent,
            'skonto_days' => $this->skontoDays,
            'supplier_invoice_no' => $this->supplierInvoiceNo,
            'invoice_date' => $this->invoiceDate,
            'net' => $this->net,
            'vat_rate' => $this->vatRate,
            'gross' => $this->gross,
            'reverse_charge' => $this->reverseCharge,
            'subject' => $this->subject,
        ];
    }
}

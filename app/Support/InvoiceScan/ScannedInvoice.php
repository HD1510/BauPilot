<?php

namespace App\Support\InvoiceScan;

/**
 * Ergebnis der Beleg-Auslese: Geschäftspartner (Lieferant bei
 * Eingangsrechnungen, Kunde bei Ausgangsrechnungen und Angeboten),
 * Konditionen und Belegkopf. Alles optional — was das Dokument nicht
 * hergibt, bleibt null und wird im Formular von Hand ergänzt.
 */
final readonly class ScannedInvoice
{
    public function __construct(
        public ?string $partnerName = null,
        public ?string $partnerUid = null,
        public ?string $partnerIban = null,
        public ?int $paymentTargetDays = null,
        public ?float $skontoPercent = null,
        public ?int $skontoDays = null,
        public ?string $docNumber = null,
        public ?string $docDate = null,
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

        $date = $string('doc_date');

        return new self(
            partnerName: $string('partner_name'),
            partnerUid: $string('partner_uid'),
            partnerIban: $string('partner_iban'),
            paymentTargetDays: $int('payment_target_days'),
            skontoPercent: $number('skonto_percent'),
            skontoDays: $int('skonto_days'),
            docNumber: $string('doc_number'),
            docDate: $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : null,
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
            'partner_name' => $this->partnerName,
            'partner_uid' => $this->partnerUid,
            'partner_iban' => $this->partnerIban,
            'payment_target_days' => $this->paymentTargetDays,
            'skonto_percent' => $this->skontoPercent,
            'skonto_days' => $this->skontoDays,
            'doc_number' => $this->docNumber,
            'doc_date' => $this->docDate,
            'net' => $this->net,
            'vat_rate' => $this->vatRate,
            'gross' => $this->gross,
            'reverse_charge' => $this->reverseCharge,
            'subject' => $this->subject,
        ];
    }
}

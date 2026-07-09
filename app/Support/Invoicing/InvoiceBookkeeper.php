<?php

namespace App\Support\Invoicing;

use App\Enums\InvoiceDocType;
use App\Enums\RetentionKind;
use App\Models\OutgoingInvoice;
use App\Models\Payment;
use App\Models\Retention;
use App\Support\Money\MoneyHelper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Saldowirksame Buchungen auf Ausgangsrechnungen — jede in einer
 * Transaktion, der Zahlstatus wird danach neu abgeleitet (v1.1).
 */
class InvoiceBookkeeper
{
    public function __construct(private InvoiceLedger $ledger) {}

    public function bookPayment(OutgoingInvoice $invoice, CarbonImmutable $paidOn, float|string $amount): Payment
    {
        return DB::transaction(function () use ($invoice, $paidOn, $amount): Payment {
            $payment = $invoice->payments()->create([
                'paid_on' => $paidOn,
                'amount' => MoneyHelper::round($amount),
            ]);

            $this->ledger->refreshStatus($invoice);

            return $payment;
        });
    }

    /**
     * Freigabe eines Einbehalts: Zahlung mit retention_id und received_at
     * am Einbehalt — in derselben Transaktion, damit die OpenItemsQuery den
     * Rücklass nie doppelt abzieht (v1.1).
     */
    public function releaseRetention(Retention $retention, CarbonImmutable $paidOn): Payment
    {
        $invoice = $retention->retainable;

        if (! $invoice instanceof OutgoingInvoice) {
            throw new InvalidArgumentException('Nur Einbehalte auf Ausgangsrechnungen können als Zahlungseingang freigegeben werden.');
        }

        if ($retention->isReceived()) {
            throw new InvalidArgumentException('Dieser Einbehalt ist bereits eingegangen.');
        }

        return DB::transaction(function () use ($retention, $invoice, $paidOn): Payment {
            $payment = $invoice->payments()->create([
                'paid_on' => $paidOn,
                'amount' => $retention->amount,
                'retention_id' => $retention->id,
            ]);

            $retention->received_at = now();
            $retention->save();

            $this->ledger->refreshStatus($invoice);

            return $payment;
        });
    }

    public function addRetention(
        OutgoingInvoice $invoice,
        RetentionKind $kind,
        float|string $amount,
        CarbonImmutable $dueOn,
        float|string|null $percent = null,
        ?string $note = null,
    ): Retention {
        return DB::transaction(function () use ($invoice, $kind, $amount, $dueOn, $percent, $note): Retention {
            /** @var Retention $retention */
            $retention = $invoice->retentions()->create([
                'kind' => $kind,
                'amount' => MoneyHelper::round($amount),
                'percent' => $percent === null ? null : MoneyHelper::round($percent),
                'due_on' => $dueOn,
                'note' => $note,
            ]);

            $this->ledger->refreshStatus($invoice);

            return $retention;
        });
    }

    /**
     * Gutschrift oder Storno auf eine Originalrechnung: negativ gespeichert,
     * über original_invoice_id verknüpft (v1.1). $amounts sind die positiven
     * Beträge laut Beleg; das Vorzeichen setzt diese Methode.
     *
     * @param  array{net: string, vat: string, gross: string}  $amounts
     */
    public function createAdjustment(
        OutgoingInvoice $original,
        InvoiceDocType $docType,
        string $number,
        CarbonImmutable $invoiceDate,
        array $amounts,
        float|string $vatRate,
        ?string $notes = null,
    ): OutgoingInvoice {
        if (! $docType->isNegative()) {
            throw new InvalidArgumentException('Nur Gutschrift oder Storno können auf eine Rechnung gebucht werden.');
        }

        if ($original->original_invoice_id !== null) {
            throw new InvalidArgumentException('Gutschrift/Storno kann nicht auf eine Gutschrift oder ein Storno gebucht werden.');
        }

        return DB::transaction(function () use ($original, $docType, $number, $invoiceDate, $amounts, $vatRate, $notes): OutgoingInvoice {
            $negated = MoneyHelper::negate($amounts);

            $adjustment = OutgoingInvoice::create([
                'doc_type' => $docType,
                'number' => $number,
                'invoice_date' => $invoiceDate,
                'due_on' => $invoiceDate,
                'customer_id' => $original->customer_id,
                'project_id' => $original->project_id,
                'original_invoice_id' => $original->id,
                'net' => $negated['net'],
                'vat_rate' => MoneyHelper::round($vatRate),
                'vat' => $negated['vat'],
                'gross' => $negated['gross'],
                'zero_rate_reason' => $original->zero_rate_reason,
                'notes' => $notes,
            ]);

            $this->ledger->refreshStatus($original);

            return $adjustment;
        });
    }
}

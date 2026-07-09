<?php

namespace App\Support\Invoicing;

use App\Enums\OutgoingPaymentStatus;
use App\Models\OutgoingInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Saldo und Zahlstatus je Rechnungs-Thread (Originalrechnung plus ihre
 * Gutschriften/Storni, Architekturblatt Abschnitt 5 und v1.1):
 *
 *   jetzt fällig = gross (Thread-Summe, Gutschrift/Storno negativ)
 *                − Zahlungen
 *                − Einbehalte, deren due_on in der Zukunft liegt und die
 *                  noch nicht eingegangen sind
 *
 * Der Zahlstatus wird bei jeder saldowirksamen Buchung neu abgeleitet,
 * nie von Hand gesetzt.
 */
class InvoiceLedger
{
    private const EPSILON = 0.005;

    /**
     * @return array{gross_effective: float, paid: float, retained_open: float, due_now: float, status: OutgoingPaymentStatus}
     */
    public function summarize(OutgoingInvoice $invoice): array
    {
        $invoice->loadMissing(['adjustments', 'payments', 'retentions', 'adjustments.payments', 'adjustments.retentions']);

        $today = CarbonImmutable::parse(Date::today());

        /** @var Collection<int, OutgoingInvoice> $thread */
        $thread = collect([$invoice, ...$invoice->adjustments->all()]);

        $grossEffective = $thread->sum(fn (OutgoingInvoice $doc): float => (float) $doc->gross);
        $paid = $thread
            ->flatMap(fn (OutgoingInvoice $doc) => $doc->payments)
            ->sum(fn ($payment): float => (float) $payment->amount);

        $retentions = $thread->flatMap(fn (OutgoingInvoice $doc) => $doc->retentions);
        $retainedOpen = $retentions
            ->filter(fn ($retention): bool => $retention->reducesDueNow($today))
            ->sum(fn ($retention): float => (float) $retention->amount);

        $dueNow = round($grossEffective - $paid - $retainedOpen, 2);

        return [
            'gross_effective' => round($grossEffective, 2),
            'paid' => round($paid, 2),
            'retained_open' => round($retainedOpen, 2),
            'due_now' => $dueNow,
            'status' => $this->deriveStatus($invoice, $grossEffective, $paid),
        ];
    }

    /**
     * Leitet den Status neu ab und speichert ihn — für die Wurzel des
     * Threads. Wird eine Gutschrift/ein Storno übergeben, ist die
     * Originalrechnung betroffen.
     */
    public function refreshStatus(OutgoingInvoice $invoice): OutgoingInvoice
    {
        $root = $invoice->original_invoice_id !== null
            ? $invoice->originalInvoice()->firstOrFail()
            : $invoice;

        $root->unsetRelation('adjustments');
        $root->unsetRelation('payments');
        $root->unsetRelation('retentions');

        $summary = $this->summarize($root);

        $root->payment_status = $summary['status'];
        $root->save();

        return $root;
    }

    private function deriveStatus(OutgoingInvoice $invoice, float $grossEffective, float $paid): OutgoingPaymentStatus
    {
        // Vollständig ausgeglichen durch Gutschrift/Storno: nicht "bezahlt",
        // sondern storniert (v1.1).
        if (abs($grossEffective) < self::EPSILON && $invoice->adjustments->isNotEmpty()) {
            return OutgoingPaymentStatus::Cancelled;
        }

        if ($grossEffective > self::EPSILON && $paid >= $grossEffective - self::EPSILON) {
            return OutgoingPaymentStatus::Paid;
        }

        if ($paid > self::EPSILON) {
            return OutgoingPaymentStatus::Partial;
        }

        return OutgoingPaymentStatus::Open;
    }
}

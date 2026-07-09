<?php

namespace App\Support\Invoicing;

use App\Enums\OutgoingPaymentStatus;
use App\Models\OutgoingInvoice;
use Illuminate\Support\Collection;

/**
 * Die eine Quelle für offene Posten (Architekturblatt Abschnitt 5):
 * berechnet, nie gespeichert. Verwendet von Liste, Dashboard, Kundenkarte
 * und Fristen.
 */
class OpenItemsQuery
{
    public function __construct(private InvoiceLedger $ledger) {}

    /**
     * Eine Zeile je Originalbeleg (Rechnung/Teil-/Schlussrechnung);
     * Gutschriften und Storni sind in ihren Thread eingerechnet.
     *
     * @return Collection<int, array{invoice: OutgoingInvoice, gross_effective: float, paid: float, retained_open: float, due_now: float, status: OutgoingPaymentStatus}>
     */
    public function rows(bool $includeSettled = false, ?int $customerId = null, ?int $projectId = null): Collection
    {
        return OutgoingInvoice::query()
            ->whereNull('original_invoice_id')
            ->when($customerId !== null, fn ($query) => $query->where('customer_id', $customerId))
            ->when($projectId !== null, fn ($query) => $query->where('project_id', $projectId))
            ->with(['customer:id,name', 'project:id,title', 'adjustments', 'payments', 'retentions', 'adjustments.payments', 'adjustments.retentions'])
            ->orderBy('invoice_date')
            ->orderBy('number')
            ->get()
            ->map(function (OutgoingInvoice $invoice): array {
                $summary = $this->ledger->summarize($invoice);

                return ['invoice' => $invoice, ...$summary];
            })
            ->when(! $includeSettled, fn (Collection $rows) => $rows->reject(
                fn (array $row): bool => in_array($row['status'], [OutgoingPaymentStatus::Paid, OutgoingPaymentStatus::Cancelled], true)
                    && abs($row['due_now']) < 0.005
                    && $row['retained_open'] < 0.005,
            ))
            ->values();
    }

    /**
     * @return array{due_now: float, retained_open: float, count: int}
     */
    public function totals(): array
    {
        $rows = $this->rows();

        return [
            'due_now' => round($rows->sum('due_now'), 2),
            'retained_open' => round($rows->sum('retained_open'), 2),
            'count' => $rows->count(),
        ];
    }
}

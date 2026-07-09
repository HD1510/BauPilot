<?php

namespace App\Support\Deadlines;

use App\Enums\IncomingPaymentStatus;
use App\Enums\OfferStatus;
use App\Enums\OutgoingPaymentStatus;
use App\Models\IncomingInvoice;
use App\Models\Offer;
use App\Models\OutgoingInvoice;
use App\Models\Project;
use App\Models\ProjectAppointment;
use App\Models\Retention;
use App\Models\Vehicle;
use App\Models\VehicleDate;
use App\Support\Invoicing\InvoiceLedger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Sammelt Fristen zur Laufzeit aus den Quelltabellen (Architekturblatt
 * Abschnitt 5) — eine Frist kann nie vom Datenstand abweichen. Quellen:
 * Zahlungsziele, Skonto, Rücklässe, Projekt- und Fahrzeugtermine,
 * Gewährleistung, Wiedervorlagen. (Aufgaben folgen in 1B.)
 */
class DeadlineService
{
    /** Pickerl-Vorwarnung und Standard-Horizont: 8 Wochen. */
    public const HORIZON_DAYS = 56;

    public function __construct(private InvoiceLedger $ledger) {}

    /**
     * Alle Fristen bis zum Horizont, Überfälliges eingeschlossen.
     * $includeFinancials filtert Belege und Beträge für die Rolle Baustelle.
     *
     * @return Collection<int, Deadline>
     */
    public function upcoming(?CarbonImmutable $until = null, bool $includeFinancials = true): Collection
    {
        $today = CarbonImmutable::parse(Date::today());
        $until ??= $today->addDays(self::HORIZON_DAYS);

        $deadlines = collect([
            ...$this->projectAppointments($today, $until),
            ...$this->vehicleDates($today, $until),
            ...$this->warranties($today, $until),
        ]);

        if ($includeFinancials) {
            $deadlines = $deadlines->merge([
                ...$this->outgoingInvoices($until),
                ...$this->incomingInvoices($until),
                ...$this->skonto($today, $until),
                ...$this->retentions($until),
                ...$this->followUps($until),
            ]);
        }

        return $deadlines->sortBy(fn (Deadline $deadline) => $deadline->dueOn)->values();
    }

    /**
     * @return array<int, Deadline>
     */
    private function outgoingInvoices(CarbonImmutable $until): array
    {
        return OutgoingInvoice::query()
            ->whereNull('original_invoice_id')
            ->whereIn('payment_status', [OutgoingPaymentStatus::Open->value, OutgoingPaymentStatus::Partial->value])
            ->where('due_on', '<=', $until)
            ->with(['customer:id,name', 'adjustments', 'payments', 'retentions', 'adjustments.payments', 'adjustments.retentions'])
            ->get()
            // Nur wenn jetzt tatsächlich etwas fällig ist — eine Rechnung,
            // bei der nur noch der Rücklass aussteht, mahnt nicht.
            ->filter(fn (OutgoingInvoice $invoice): bool => $this->ledger->summarize($invoice)['due_now'] > 0.005)
            ->map(fn (OutgoingInvoice $invoice): Deadline => new Deadline(
                DeadlineKind::PaymentDue,
                $invoice->due_on,
                "Rechnung {$invoice->number}",
                $invoice->customer?->name,
                "/outgoing-invoices/{$invoice->id}",
                financial: true,
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<int, Deadline>
     */
    private function incomingInvoices(CarbonImmutable $until): array
    {
        return IncomingInvoice::query()
            ->where('payment_status', '!=', IncomingPaymentStatus::Paid->value)
            ->whereNotNull('payment_due_on')
            ->where('payment_due_on', '<=', $until)
            ->with('supplier:id,name')
            ->get()
            ->map(fn (IncomingInvoice $invoice): Deadline => new Deadline(
                DeadlineKind::IncomingDue,
                $invoice->payment_due_on,
                "Eingangsrechnung {$invoice->supplier_invoice_no}",
                $invoice->supplier?->name,
                "/incoming-invoices/{$invoice->id}/edit",
                financial: true,
            ))
            ->all();
    }

    /**
     * Skonto lohnt nur, solange die Frist noch nicht verstrichen ist.
     *
     * @return array<int, Deadline>
     */
    private function skonto(CarbonImmutable $today, CarbonImmutable $until): array
    {
        return IncomingInvoice::query()
            ->where('payment_status', '!=', IncomingPaymentStatus::Paid->value)
            ->whereNotNull('skonto_until')
            ->whereBetween('skonto_until', [$today, $until])
            ->with('supplier:id,name')
            ->get()
            ->map(fn (IncomingInvoice $invoice): Deadline => new Deadline(
                DeadlineKind::Skonto,
                $invoice->skonto_until,
                "Skonto {$invoice->supplier_invoice_no}",
                $invoice->supplier?->name,
                "/incoming-invoices/{$invoice->id}/edit",
                financial: true,
            ))
            ->all();
    }

    /**
     * @return array<int, Deadline>
     */
    private function retentions(CarbonImmutable $until): array
    {
        return Retention::query()
            ->whereNull('received_at')
            ->where('due_on', '<=', $until)
            ->where('retainable_type', 'outgoing_invoice')
            ->with('retainable')
            ->get()
            ->map(function (Retention $retention): Deadline {
                $invoice = $retention->retainable;
                $number = $invoice instanceof OutgoingInvoice ? $invoice->number : '?';
                $invoiceId = $invoice instanceof OutgoingInvoice ? $invoice->id : 0;

                return new Deadline(
                    DeadlineKind::Retention,
                    $retention->due_on,
                    "{$retention->kind->label()} zu Rechnung {$number}",
                    null,
                    "/outgoing-invoices/{$invoiceId}",
                    financial: true,
                );
            })
            ->all();
    }

    /**
     * @return array<int, Deadline>
     */
    private function followUps(CarbonImmutable $until): array
    {
        return Offer::query()
            ->whereNotNull('follow_up_on')
            ->where('follow_up_on', '<=', $until)
            ->whereNotIn('status', [OfferStatus::Accepted->value, OfferStatus::Rejected->value])
            ->with('customer:id,name')
            ->get()
            ->map(fn (Offer $offer): Deadline => new Deadline(
                DeadlineKind::FollowUp,
                $offer->follow_up_on,
                'Wiedervorlage Angebot '.($offer->offer_number ?? "#{$offer->id}"),
                $offer->customer?->name,
                "/offers/{$offer->id}/edit",
                financial: true,
            ))
            ->all();
    }

    /**
     * @return array<int, Deadline>
     */
    private function projectAppointments(CarbonImmutable $today, CarbonImmutable $until): array
    {
        return ProjectAppointment::query()
            ->whereBetween('on_date', [$today, $until])
            ->with('project:id,title')
            ->get()
            ->map(fn (ProjectAppointment $appointment): Deadline => new Deadline(
                DeadlineKind::Appointment,
                $appointment->on_date,
                $appointment->label,
                $appointment->project?->title,
                "/projects/{$appointment->project_id}",
                financial: false,
            ))
            ->all();
    }

    /**
     * Pickerl, Vignette und Zusatztermine — Vorwarnung über den Horizont.
     *
     * @return array<int, Deadline>
     */
    private function vehicleDates(CarbonImmutable $today, CarbonImmutable $until): array
    {
        $vehicles = Vehicle::query()->where('active', true)->get();

        $deadlines = [];

        foreach ($vehicles as $vehicle) {
            if ($vehicle->inspection_due_on !== null && $vehicle->inspection_due_on->lessThanOrEqualTo($until)) {
                $deadlines[] = new Deadline(
                    DeadlineKind::Vehicle,
                    $vehicle->inspection_due_on,
                    "Pickerl {$vehicle->plate}",
                    trim(($vehicle->brand ?? '').' '.($vehicle->model ?? '')) ?: null,
                    "/vehicles/{$vehicle->id}/edit",
                    financial: false,
                );
            }

            if ($vehicle->vignette_until !== null && $vehicle->vignette_until->between($today, $until)) {
                $deadlines[] = new Deadline(
                    DeadlineKind::Vehicle,
                    $vehicle->vignette_until,
                    "Vignette {$vehicle->plate}",
                    null,
                    "/vehicles/{$vehicle->id}/edit",
                    financial: false,
                );
            }
        }

        $extraDates = VehicleDate::query()
            ->where('due_on', '<=', $until)
            ->with('vehicle:id,plate')
            ->get()
            ->map(fn (VehicleDate $date): Deadline => new Deadline(
                DeadlineKind::Vehicle,
                $date->due_on,
                "{$date->label} {$date->vehicle?->plate}",
                null,
                "/vehicles/{$date->vehicle_id}/edit",
                financial: false,
            ));

        return [...$deadlines, ...$extraDates->all()];
    }

    /**
     * @return array<int, Deadline>
     */
    private function warranties(CarbonImmutable $today, CarbonImmutable $until): array
    {
        return Project::query()
            ->whereNotNull('warranty_until')
            ->whereBetween('warranty_until', [$today, $until])
            ->get()
            ->map(fn (Project $project): Deadline => new Deadline(
                DeadlineKind::Warranty,
                $project->warranty_until,
                "Gewährleistungsende {$project->title}",
                null,
                "/projects/{$project->id}",
                financial: false,
            ))
            ->all();
    }
}

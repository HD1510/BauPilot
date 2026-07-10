<?php

namespace App\Support\Projects;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Support\Money\MoneyHelper;

/**
 * Projektzahlen mit Deckungsbeitrag (Architekturblatt M8) — wie die
 * offenen Posten werden sie nie gespeichert, sondern aus den Belegen
 * gerechnet; es gibt genau eine Quelle dafür.
 *
 *   Erlöse (netto)   = Summe Ausgangsrechnungen des Projekts
 *                      (Gutschrift/Storno stehen negativ und heben sich
 *                      von selbst auf — Konvention aus Abschnitt 5)
 *   Fremdkosten      = Summe Eingangsrechnungen (netto) des Projekts
 *   Lohnkosten       = Stunden × kalkulatorischer Satz; der Satz des
 *                      Mitarbeiters übersteuert den Firmenwert
 *   Deckungsbeitrag  = Erlöse − Fremdkosten − Lohnkosten
 */
class ProjectFigures
{
    /**
     * @return array{
     *     revenue_net: float,
     *     external_costs_net: float,
     *     hours: float,
     *     labor_cost: float,
     *     unrated_hours: float,
     *     contribution: float,
     *     margin_percent: float|null,
     * }
     */
    public function forProject(Project $project): array
    {
        $revenue = (float) $project->outgoingInvoices()->sum('net');
        $externalCosts = (float) $project->incomingInvoices()->sum('net');

        $companyRate = $project->company->calc_hourly_rate;

        $hours = 0.0;
        $laborCost = 0.0;
        $unratedHours = 0.0;

        TimeEntry::query()
            ->where('project_id', $project->id)
            ->with('employee:id,calc_hourly_rate')
            ->get()
            ->each(function (TimeEntry $entry) use ($companyRate, &$hours, &$laborCost, &$unratedHours): void {
                $entryHours = (float) $entry->hours;
                $hours += $entryHours;

                $rate = $entry->employee->calc_hourly_rate ?? $companyRate;

                if ($rate === null) {
                    // Ohne Satz keine stillschweigende Null-Bewertung:
                    // die Stunden werden ausgewiesen, nicht verrechnet.
                    $unratedHours += $entryHours;

                    return;
                }

                $laborCost += $entryHours * (float) $rate;
            });

        $laborCost = (float) MoneyHelper::round($laborCost);
        $contribution = (float) MoneyHelper::round($revenue - $externalCosts - $laborCost);

        return [
            'revenue_net' => $revenue,
            'external_costs_net' => $externalCosts,
            'hours' => round($hours, 2),
            'labor_cost' => $laborCost,
            'unrated_hours' => round($unratedHours, 2),
            'contribution' => $contribution,
            'margin_percent' => $revenue > 0.0 ? round($contribution / $revenue * 100, 1) : null,
        ];
    }
}

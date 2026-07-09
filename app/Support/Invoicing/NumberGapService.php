<?php

namespace App\Support\Invoicing;

use App\Models\OutgoingInvoice;
use App\Support\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

/**
 * Nummern-Lückenprüfung (Architekturblatt Abschnitt 5): extrahiert den
 * numerischen Teil der Ausgangsrechnungsnummern des laufenden
 * Geschäftsjahres und meldet Sprünge als weichen Hinweis. Duplikate
 * verhindert bereits der Unique-Constraint.
 */
class NumberGapService
{
    public function __construct(private CompanyContext $context) {}

    /**
     * @return array{fiscal_year_start: string, checked: int, gaps: list<int>}
     */
    public function checkCurrentFiscalYear(): array
    {
        $company = $this->context->requireCompany();
        $today = CarbonImmutable::parse(Date::today());

        $fiscalYearStart = $today->setMonth($company->fiscal_year_start_month)->startOfMonth();

        if ($fiscalYearStart->greaterThan($today)) {
            $fiscalYearStart = $fiscalYearStart->subYear();
        }

        $numbers = OutgoingInvoice::query()
            ->where('invoice_date', '>=', $fiscalYearStart)
            ->pluck('number')
            ->map(function (string $number): ?int {
                // Längste Ziffernfolge als numerischer Teil
                preg_match_all('/\d+/', $number, $matches);
                $digits = collect($matches[0])->sortByDesc(fn (string $part) => strlen($part))->first();

                return $digits === null ? null : (int) $digits;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $gaps = [];

        for ($i = 1; $i < $numbers->count(); $i++) {
            $previous = $numbers[$i - 1];
            $current = $numbers[$i];

            for ($missing = $previous + 1; $missing < $current && count($gaps) < 20; $missing++) {
                $gaps[] = $missing;
            }
        }

        return [
            'fiscal_year_start' => $fiscalYearStart->toDateString(),
            'checked' => $numbers->count(),
            'gaps' => $gaps,
        ];
    }
}

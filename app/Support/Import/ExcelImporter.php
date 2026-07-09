<?php

namespace App\Support\Import;

use App\Enums\IncomingPaymentStatus;
use App\Enums\ZeroRateReason;
use App\Models\CostType;
use App\Models\Customer;
use App\Models\ImportFinding;
use App\Models\ImportRun;
use App\Models\IncomingInvoice;
use App\Models\OutgoingInvoice;
use App\Models\Supplier;
use App\Support\Duplicates\DuplicateFinder;
use App\Support\Invoicing\InvoiceLedger;
use App\Support\Money\MoneyHelper;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Zweiphasiger Excel-Import (Architekturblatt Abschnitt 8): Der Dry-Run
 * liest die Datei, erzeugt Statistik und Prüfbericht und schreibt nichts
 * Fachliches; der Commit schreibt transaktional je Bereich, nachdem die
 * Findings entschieden sind. source_ref (Blatt:Zeile) macht erneute Läufe
 * derselben Datei idempotent.
 */
class ExcelImporter
{
    public function __construct(
        private DuplicateFinder $duplicates,
        private InvoiceLedger $ledger,
    ) {}

    /**
     * Phase 1: Dry-Run — Statistik und Findings, keine fachlichen Daten.
     */
    public function dryRun(ImportRun $run): void
    {
        $parsed = $this->parse($run);

        $outgoing = collect($parsed['outgoing']);
        $incoming = collect($parsed['incoming']);

        $run->findings()->delete();

        $this->collectDuplicateFindings($run, Customer::class, $outgoing->pluck('customer')->all(), 'Kunde');
        $this->collectDuplicateFindings($run, Supplier::class, $incoming->pluck('supplier')->all(), 'Lieferant');

        foreach ([...$parsed['outgoing'], ...$parsed['incoming']] as $row) {
            if ($row['date_estimated']) {
                $run->findings()->create([
                    'type' => 'estimate',
                    'message' => "{$row['source_ref']}: Kein Datum in der Datei — angenommen wird die Monatsmitte ({$row['date']}).",
                    'payload' => ['source_ref' => $row['source_ref'], 'estimated_date' => $row['date']],
                ]);
            }

            if ($row['date_implausible']) {
                $run->findings()->create([
                    'type' => 'warning',
                    'message' => "{$row['source_ref']}: Unplausibles Datum {$row['date']} — bitte prüfen (vgl. Rechnung 250184 in der Spezifikation).",
                    'payload' => ['source_ref' => $row['source_ref'], 'date' => $row['date']],
                ]);
            }
        }

        $run->update([
            'stats' => [
                'outgoing' => [
                    'count' => $outgoing->count(),
                    'net' => round($outgoing->sum('net'), 2),
                    'gross' => round($outgoing->sum('gross'), 2),
                    'open_count' => $outgoing->whereNull('paid_on')->count(),
                    'open_gross' => round($outgoing->whereNull('paid_on')->sum('gross'), 2),
                    'paragraph19_net' => round($outgoing->where('paragraph19', true)->sum('net'), 2),
                ],
                'incoming' => [
                    'count' => $incoming->count(),
                    'net' => round($incoming->sum('net'), 2),
                    'gross' => round($incoming->sum('gross'), 2),
                    'paragraph19_net' => round($incoming->where('paragraph19', true)->sum('net'), 2),
                ],
            ],
        ]);
    }

    /**
     * Phase 2: Commit — transaktional je Bereich, idempotent über source_ref.
     *
     * @return array{customers: int, suppliers: int, outgoing: int, incoming: int, payments: int, skipped: int}
     */
    public function commit(ImportRun $run): array
    {
        if ($run->isCommitted()) {
            throw new RuntimeException('Dieser Import-Lauf wurde bereits übernommen.');
        }

        $undecided = $run->findings()->where('type', 'duplicate')->whereNull('decision')->count();

        if ($undecided > 0) {
            throw new RuntimeException("Es sind noch {$undecided} Dubletten-Findings zu entscheiden.");
        }

        $parsed = $this->parse($run);
        $decisions = $run->findings()->where('type', 'duplicate')->get()
            ->keyBy(fn (ImportFinding $finding) => $finding->payload['model'].':'.$finding->payload['name']);

        $result = ['customers' => 0, 'suppliers' => 0, 'outgoing' => 0, 'incoming' => 0, 'payments' => 0, 'skipped' => 0];

        DB::transaction(function () use ($run, $parsed, $decisions, &$result): void {
            $customers = $this->resolveParties(Customer::class, collect($parsed['outgoing'])->pluck('customer'), $decisions, 'customer', $result['customers']);
            $suppliers = $this->resolveParties(Supplier::class, collect($parsed['incoming'])->pluck('supplier'), $decisions, 'supplier', $result['suppliers']);

            foreach ($parsed['outgoing'] as $row) {
                if (OutgoingInvoice::query()->where('source_ref', $row['source_ref'])->exists()) {
                    $result['skipped']++;

                    continue;
                }

                $customer = $customers[$row['customer']];
                $amounts = MoneyHelper::fromNet($row['net'], $row['vat_rate']);
                $date = CarbonImmutable::parse($row['date']);

                $invoice = OutgoingInvoice::create([
                    'doc_type' => 'invoice',
                    'number' => $row['number'],
                    'invoice_date' => $date,
                    'due_on' => $date->addDays($customer->payment_target_days),
                    'customer_id' => $customer->id,
                    'net' => $amounts['net'],
                    'vat_rate' => MoneyHelper::round($row['vat_rate']),
                    'vat' => $amounts['vat'],
                    'gross' => $amounts['gross'],
                    'zero_rate_reason' => $row['paragraph19'] ? ZeroRateReason::ReverseCharge19_1a : null,
                    'source_ref' => $row['source_ref'],
                ]);
                $result['outgoing']++;

                if ($row['paid_on'] !== null) {
                    $invoice->payments()->create([
                        'paid_on' => $row['paid_on'],
                        'amount' => MoneyHelper::round($row['paid_amount'] ?? $amounts['gross']),
                        'source_ref' => $row['source_ref'].':zahlung',
                    ]);
                    $this->ledger->refreshStatus($invoice);
                    $result['payments']++;
                }
            }

            foreach ($parsed['incoming'] as $row) {
                if (IncomingInvoice::query()->where('source_ref', $row['source_ref'])->exists()) {
                    $result['skipped']++;

                    continue;
                }

                $supplier = $suppliers[$row['supplier']];
                $amounts = MoneyHelper::fromNet($row['net'], $row['vat_rate']);
                $date = CarbonImmutable::parse($row['date']);

                $costType = CostType::query()->firstOrCreate(
                    ['name' => $row['cost_type'] ?: 'Sonstiges'],
                    ['sort_order' => 999],
                );

                IncomingInvoice::create([
                    'supplier_id' => $supplier->id,
                    'supplier_invoice_no' => $row['number'],
                    'invoice_date' => $date,
                    'date_estimated' => $row['date_estimated'],
                    'net' => $amounts['net'],
                    'vat_rate' => MoneyHelper::round($row['vat_rate']),
                    'vat' => $amounts['vat'],
                    'gross' => $amounts['gross'],
                    'reverse_charge' => $row['paragraph19'],
                    'cost_type_id' => $costType->id,
                    'payment_due_on' => $date->addDays($supplier->payment_target_days),
                    'payment_status' => $row['paid_on'] !== null ? IncomingPaymentStatus::Paid : IncomingPaymentStatus::Open,
                    'paid_on' => $row['paid_on'],
                    'paid_amount' => $row['paid_on'] !== null ? $amounts['gross'] : null,
                    'source_ref' => $row['source_ref'],
                ]);
                $result['incoming']++;
            }

            $run->update([
                'status' => 'committed',
                'stats' => [...$run->stats, 'committed' => $result],
            ]);
        });

        return $result;
    }

    /**
     * Liest die Datei mit dem (kalibrierbaren) Mapping.
     *
     * @return array{outgoing: list<array<string, mixed>>, incoming: list<array<string, mixed>>}
     */
    public function parse(ImportRun $run): array
    {
        $localPath = Storage::disk('documents')->path($run->path);
        $spreadsheet = IOFactory::load($localPath);

        $outgoing = [];
        $incoming = [];

        $outgoingSheet = $spreadsheet->getSheetByName(ImportMapping::SHEET_OUTGOING);
        $incomingSheet = $spreadsheet->getSheetByName(ImportMapping::SHEET_INCOMING);

        if ($outgoingSheet !== null) {
            foreach ($this->dataRows($outgoingSheet) as $rowIndex => $cells) {
                $number = trim((string) ($cells[ImportMapping::OUT_NUMBER] ?? ''));

                if ($number === '') {
                    continue;
                }

                $paragraph19 = ImportMapping::isParagraph19($cells[ImportMapping::OUT_PARAGRAPH19] ?? null);
                [$date, $estimated, $implausible] = $this->resolveDate($cells[ImportMapping::OUT_DATE] ?? null);
                $net = $this->toFloat($cells[ImportMapping::OUT_NET] ?? 0);
                $vatRate = $paragraph19 ? 0.0 : ($this->toFloat($cells[ImportMapping::OUT_VAT_RATE] ?? 20) ?: 20.0);
                $paidOn = $this->toDate($cells[ImportMapping::OUT_PAID_ON] ?? null)?->toDateString();

                $outgoing[] = [
                    'source_ref' => ImportMapping::SHEET_OUTGOING.':'.$rowIndex,
                    'number' => $number,
                    'date' => $date,
                    'date_estimated' => $estimated,
                    'date_implausible' => $implausible,
                    'customer' => trim((string) ($cells[ImportMapping::OUT_CUSTOMER] ?? '')) ?: ImportMapping::DIVERSE,
                    'net' => $net,
                    'vat_rate' => $vatRate,
                    'gross' => (float) MoneyHelper::fromNet($net, $vatRate)['gross'],
                    'paragraph19' => $paragraph19,
                    'paid_on' => $paidOn,
                    'paid_amount' => $this->toFloat($cells[ImportMapping::OUT_PAID_AMOUNT] ?? 0) ?: null,
                ];
            }
        }

        if ($incomingSheet !== null) {
            foreach ($this->dataRows($incomingSheet) as $rowIndex => $cells) {
                $supplier = trim((string) ($cells[ImportMapping::IN_SUPPLIER] ?? ''));

                if ($supplier === '') {
                    continue;
                }

                $paragraph19 = ImportMapping::isParagraph19($cells[ImportMapping::IN_PARAGRAPH19] ?? null);
                [$date, $estimated, $implausible] = $this->resolveDate($cells[ImportMapping::IN_DATE] ?? null);
                $net = $this->toFloat($cells[ImportMapping::IN_NET] ?? 0);
                $vatRate = $paragraph19 ? 0.0 : ($this->toFloat($cells[ImportMapping::IN_VAT_RATE] ?? 20) ?: 20.0);

                $incoming[] = [
                    'source_ref' => ImportMapping::SHEET_INCOMING.':'.$rowIndex,
                    'number' => trim((string) ($cells[ImportMapping::IN_NUMBER] ?? '')) ?: null,
                    'date' => $date,
                    'date_estimated' => $estimated,
                    'date_implausible' => $implausible,
                    'supplier' => $supplier,
                    'net' => $net,
                    'vat_rate' => $vatRate,
                    'gross' => (float) MoneyHelper::fromNet($net, $vatRate)['gross'],
                    'paragraph19' => $paragraph19,
                    'cost_type' => trim((string) ($cells[ImportMapping::IN_COST_TYPE] ?? '')),
                    'paid_on' => $this->toDate($cells[ImportMapping::IN_PAID_ON] ?? null)?->toDateString(),
                ];
            }
        }

        return ['outgoing' => $outgoing, 'incoming' => $incoming];
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    private function dataRows(Worksheet $sheet): iterable
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = min(12, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));

        for ($row = ImportMapping::FIRST_DATA_ROW; $row <= $highestRow; $row++) {
            $cells = [];

            for ($column = 1; $column <= $highestColumn; $column++) {
                $cells[$column] = $sheet->getCell([$column, $row])->getValue();
            }

            yield $row => $cells;
        }
    }

    /**
     * @return array{0: string, 1: bool, 2: bool} [datum, geschätzt, unplausibel]
     */
    private function resolveDate(mixed $value): array
    {
        $date = $this->toDate($value);

        if ($date === null) {
            // Geschätztes Datum: Monatsmitte des aktuellen Monats
            // (mit der echten Datei: Monatsmitte des Monatsblocks).
            return [now()->startOfMonth()->addDays(14)->toDateString(), true, false];
        }

        $implausible = $date->year < 2000 || $date->greaterThan(now()->addYear());

        return [$date->toDateString(), false, $implausible];
    }

    private function toDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value));
        }

        foreach (['d.m.Y', 'Y-m-d', 'd.m.y'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, trim((string) $value));
            } catch (InvalidFormatException) {
                continue;
            }
        }

        return null;
    }

    private function toFloat(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Österreichische Schreibweise: 1.234,56
        $normalized = str_replace(['.', ','], ['', '.'], trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    /**
     * Dubletten-Findings für Kunden bzw. Lieferanten (Architekturblatt 8):
     * nur ähnliche, nicht exakt vorhandene Namen brauchen eine Entscheidung.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array<int, mixed>  $names
     */
    private function collectDuplicateFindings(ImportRun $run, string $modelClass, array $names, string $label): void
    {
        foreach (collect($names)->unique()->filter() as $name) {
            if ($modelClass::query()->where('name', $name)->exists()) {
                continue; // exakter Treffer wird beim Commit wiederverwendet
            }

            $matches = $this->duplicates->findSimilar($modelClass, $name);

            if ($matches->isEmpty()) {
                continue;
            }

            $run->findings()->create([
                'type' => 'duplicate',
                'message' => "{$label} „{$name}“ ähnelt: ".$matches->pluck('name')->join(', '),
                'payload' => [
                    'model' => $modelClass === Customer::class ? 'customer' : 'supplier',
                    'name' => $name,
                    'matches' => $matches->all(),
                ],
            ]);
        }
    }

    /**
     * Kunden/Lieferanten je Name auflösen: exakter Treffer → wiederverwenden,
     * Dubletten-Entscheidung „use_existing" → bestehenden verwenden, sonst
     * neu anlegen (mit source_ref).
     *
     * @param  class-string<Customer|Supplier>  $modelClass
     * @param  Collection<int, string>  $names
     * @param  Collection<string, ImportFinding>  $decisions
     * @return array<string, Customer|Supplier>
     */
    private function resolveParties(string $modelClass, Collection $names, Collection $decisions, string $modelKey, int &$created): array
    {
        $resolved = [];

        foreach ($names->unique()->filter() as $name) {
            $existing = $modelClass::query()->where('name', $name)->first();

            if ($existing !== null) {
                $resolved[$name] = $existing;

                continue;
            }

            $finding = $decisions->get("{$modelKey}:{$name}");

            if ($finding !== null && $finding->decision === 'use_existing') {
                $matchId = $finding->payload['matches'][0]['id'];
                $resolved[$name] = $modelClass::query()->whereKey($matchId)->firstOrFail();

                continue;
            }

            $model = $modelClass::create([
                'name' => $name,
                'source_ref' => 'import:'.$name,
            ]);
            // DB-Defaults (z. B. Zahlungsziel) in den Speicher holen
            $model->refresh();
            $resolved[$name] = $model;
            $created++;
        }

        return $resolved;
    }
}

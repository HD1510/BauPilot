<?php

use App\Enums\CompanyRole;
use App\Enums\OutgoingPaymentStatus;
use App\Enums\ZeroRateReason;
use App\Models\CostType;
use App\Models\Customer;
use App\Models\ImportRun;
use App\Models\IncomingInvoice;
use App\Models\OutgoingInvoice;
use App\Models\Supplier;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    Storage::fake('documents');
});

afterEach(function () {
    app(CompanyContext::class)->clear();
});

/**
 * Fixture nach dem Beispiel-Mapping: drei Ausgangs-, zwei Eingangs-
 * rechnungen, eine §19-Rechnung, ein fehlendes Datum, eine Dublette.
 */
function fixtureExcel(): UploadedFile
{
    $spreadsheet = new Spreadsheet;

    $outgoing = $spreadsheet->getActiveSheet();
    $outgoing->setTitle('Ausgangsrechnungen');
    $outgoing->fromArray([
        ['Nummer', 'Datum', 'Kunde', 'Netto', 'USt %', '§19', 'bezahlt am', 'Zahlbetrag'],
        ['250181', '01.06.2026', 'Huber Wohnbau', 10000, 20, '', '15.06.2026', 12000],
        ['250182', '05.06.2026', 'Müller Bau GmbH', 5000, '', 'x', '', ''],
        ['250184', '', 'Huber Wohnbau', 2000, 20, '', '', ''],
    ], null, 'A1');

    $incoming = $spreadsheet->createSheet();
    $incoming->setTitle('Eingangsrechnungen');
    $incoming->fromArray([
        ['Lieferant', 'RgNr', 'Datum', 'Netto', 'USt %', '§19', 'Kostenart', 'bezahlt am'],
        ['Baustoffe Nord', 'L-1', '10.06.2026', 1000, 20, '', 'Material', '20.06.2026'],
        ['Sub Bau GmbH', 'S-9', '12.06.2026', 8000, '', 'x', 'Fremdleistung', ''],
    ], null, 'A1');

    $path = tempnam(sys_get_temp_dir(), 'baupilot-import').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'uebersicht_test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

test('dry-run: statistik stimmt, findings für dublette, schätzung und §19-summen', function () {
    [, $company] = actingMember(CompanyRole::Admin);

    // Ähnlicher Kunde existiert schon → Dubletten-Finding
    app(CompanyContext::class)->runFor($company, fn () => Customer::create(['name' => 'Mueller-Bau GmbH']));

    $this->post('/imports', ['file' => fixtureExcel()])->assertSessionHasNoErrors();

    $run = ImportRun::withoutGlobalScopes()->firstOrFail();

    expect($run->status)->toBe('dry_run')
        ->and($run->stats['outgoing']['count'])->toBe(3)
        ->and($run->stats['outgoing']['open_count'])->toBe(2)
        ->and((float) $run->stats['outgoing']['paragraph19_net'])->toBe(5000.0)
        ->and($run->stats['incoming']['count'])->toBe(2)
        ->and((float) $run->stats['incoming']['paragraph19_net'])->toBe(8000.0);

    $types = $run->findings()->withoutGlobalScopes()->pluck('type');
    expect($types)->toContain('duplicate')->toContain('estimate');

    // Dry-Run schreibt nichts Fachliches
    expect(OutgoingInvoice::withoutGlobalScopes()->count())->toBe(0)
        ->and(Customer::withoutGlobalScopes()->count())->toBe(1);
});

test('commit verlangt entschiedene dubletten, übernimmt dann transaktional und idempotent', function () {
    [, $company] = actingMember(CompanyRole::Admin);
    app(CompanyContext::class)->runFor($company, fn () => Customer::create(['name' => 'Mueller-Bau GmbH']));

    $this->post('/imports', ['file' => fixtureExcel()]);
    $run = ImportRun::withoutGlobalScopes()->firstOrFail();

    // Ohne Entscheidung: kein Commit
    $this->post("/imports/{$run->id}/commit")->assertSessionHasErrors('commit');
    expect($run->refresh()->status)->toBe('dry_run');

    // Dublette entscheiden: bestehenden Kunden verwenden
    $finding = $run->findings()->withoutGlobalScopes()->where('type', 'duplicate')->firstOrFail();
    $this->patch("/imports/{$run->id}/findings/{$finding->id}", ['decision' => 'use_existing'])
        ->assertSessionHasNoErrors();

    // Commit
    $this->post("/imports/{$run->id}/commit")->assertSessionHasNoErrors();
    expect($run->refresh()->status)->toBe('committed');

    app(CompanyContext::class)->set($company);

    // Kunden: Huber neu, Müller → bestehender Mueller-Bau (keine Dublette angelegt)
    expect(Customer::query()->count())->toBe(2)
        ->and(Customer::query()->where('name', 'Müller Bau GmbH')->exists())->toBeFalse();

    // Ausgangsrechnungen: 3 mit source_ref; 250181 bezahlt; 250182 = §19
    expect(OutgoingInvoice::query()->count())->toBe(3);

    $paid = OutgoingInvoice::query()->where('number', '250181')->firstOrFail();
    expect($paid->payment_status)->toBe(OutgoingPaymentStatus::Paid)
        ->and($paid->source_ref)->toBe('Ausgangsrechnungen:2')
        ->and($paid->due_on->toDateString())->toBe('2026-06-15'); // 14 Tage Kundenziel

    $paragraph19 = OutgoingInvoice::query()->where('number', '250182')->firstOrFail();
    expect($paragraph19->zero_rate_reason)->toBe(ZeroRateReason::ReverseCharge19_1a)
        ->and((float) $paragraph19->vat)->toBe(0.0)
        ->and((float) $paragraph19->gross)->toBe(5000.0)
        ->and($paragraph19->customer?->name)->toBe('Mueller-Bau GmbH');

    // Geschätztes Datum übernommen, Rechnung angelegt
    $estimated = OutgoingInvoice::query()->where('number', '250184')->firstOrFail();
    expect($estimated->invoice_date->day)->toBe(15);

    // Eingangsrechnungen: Kostenarten angelegt, §19 als reverse_charge
    expect(IncomingInvoice::query()->count())->toBe(2)
        ->and(CostType::query()->pluck('name')->sort()->values()->all())->toBe(['Fremdleistung', 'Material'])
        ->and(IncomingInvoice::query()->where('supplier_invoice_no', 'S-9')->firstOrFail()->reverse_charge)->toBeTrue()
        ->and(Supplier::query()->count())->toBe(2);

    // Zweiter Lauf derselben Datei: alles wird übersprungen — nichts doppelt
    app(CompanyContext::class)->clear();
    $this->post('/imports', ['file' => fixtureExcel()]);
    $secondRun = ImportRun::withoutGlobalScopes()->orderByDesc('id')->firstOrFail();

    $duplicateFinding = $secondRun->findings()->withoutGlobalScopes()->where('type', 'duplicate')->first();
    if ($duplicateFinding !== null) {
        $this->patch("/imports/{$secondRun->id}/findings/{$duplicateFinding->id}", ['decision' => 'use_existing']);
    }

    $this->post("/imports/{$secondRun->id}/commit")->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    expect(OutgoingInvoice::query()->count())->toBe(3)
        ->and(IncomingInvoice::query()->count())->toBe(2)
        ->and(Customer::query()->count())->toBe(2)
        ->and($secondRun->refresh()->stats['committed']['skipped'])->toBe(5);
});

test('der import ist admin-sache: büro und baustelle sind ausgeschlossen', function () {
    actingMember(CompanyRole::Office);
    $this->get('/imports')->assertForbidden();
    $this->post('/imports', ['file' => fixtureExcel()])->assertForbidden();

    actingMember(CompanyRole::Site);
    $this->get('/imports')->assertForbidden();
});

test('ein bereits übernommener lauf kann nicht erneut übernommen werden', function () {
    actingMember(CompanyRole::Admin);

    $this->post('/imports', ['file' => fixtureExcel()]);
    $run = ImportRun::withoutGlobalScopes()->firstOrFail();

    $this->post("/imports/{$run->id}/commit")->assertSessionHasNoErrors();
    $this->post("/imports/{$run->id}/commit")->assertSessionHasErrors('commit');
});

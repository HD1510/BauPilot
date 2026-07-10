<?php

use App\Enums\CompanyRole;
use App\Models\CostType;
use App\Models\Document;
use App\Models\IncomingInvoice;
use App\Models\Supplier;
use App\Support\InvoiceScan\InvoiceScanner;
use App\Support\InvoiceScan\ScannedInvoice;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('documents');
});

afterEach(function () {
    app(CompanyContext::class)->clear();
});

function fakeScanner(ScannedInvoice $result): void
{
    test()->mock(InvoiceScanner::class, function ($mock) use ($result) {
        $mock->shouldReceive('enabled')->andReturn(true);
        $mock->shouldReceive('scan')->andReturn($result);
    });
}

test('scan erkennt bestehenden lieferanten und befüllt die konditionen', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $existing = Supplier::factory()->create([
        'company_id' => $company->id,
        'name' => 'Huber Transporte GmbH',
        'payment_target_days' => 21,
    ]);
    app(CompanyContext::class)->clear();

    fakeScanner(new ScannedInvoice(
        supplierName: 'Huber Transporte GmbH',
        supplierUid: 'ATU12345678',
        paymentTargetDays: 21,
        skontoPercent: 3.0,
        skontoDays: 14,
        supplierInvoiceNo: 'RE-2026-0815',
        invoiceDate: '2026-07-01',
        net: 1000.0,
        vatRate: 20.0,
        gross: 1200.0,
        subject: 'Schotterlieferung',
    ));

    $response = $this->post('/incoming-invoices/scan', [
        'file' => UploadedFile::fake()->create('rechnung.pdf', 200, 'application/pdf'),
    ])->assertOk();

    // Exakter Treffer steht mit 100 % vorne — die Auswahl bestätigt ein Mensch.
    expect($response->json('matches.0.id'))->toBe($existing->id)
        ->and($response->json('matches.0.similarity'))->toBe(1)
        ->and($response->json('matches.0.payment_target_days'))->toBe(21)
        ->and($response->json('extraction.supplier_name'))->toBe('Huber Transporte GmbH')
        ->and($response->json('supplier_proposal.notes'))->toBe('UID: ATU12345678')
        ->and($response->json('prefill.amount'))->toBe('1000.00')
        ->and($response->json('prefill.amount_mode'))->toBe('net')
        ->and($response->json('prefill.vat_rate'))->toBe('20.00')
        ->and($response->json('prefill.supplier_invoice_no'))->toBe('RE-2026-0815')
        ->and($response->json('prefill.payment_due_on'))->toBe('2026-07-22')
        ->and($response->json('prefill.skonto_amount'))->toBe('36.00')
        ->and($response->json('prefill.skonto_until'))->toBe('2026-07-15');

    // Die Datei wartet unter dem Token auf das Speichern der Rechnung.
    $token = $response->json('scan_token');
    expect(Storage::disk('documents')->files("testing/{$company->id}/scan/{$token}"))->toHaveCount(1);
});

test('scan ohne treffer: brutto-modus und ähnliche kandidaten bleiben leer', function () {
    actingMember();

    fakeScanner(new ScannedInvoice(
        supplierName: 'Neuer Lieferant e.U.',
        gross: 590.0,
        vatRate: 18.0,
        reverseCharge: true,
    ));

    $response = $this->post('/incoming-invoices/scan', [
        'file' => UploadedFile::fake()->image('foto.jpg'),
    ])->assertOk();

    // Reverse Charge schlägt den erkannten Satz: 0 % (Architekturblatt 5).
    expect($response->json('matches'))->toBe([])
        ->and($response->json('prefill.amount_mode'))->toBe('gross')
        ->and($response->json('prefill.amount'))->toBe('590.00')
        ->and($response->json('prefill.vat_rate'))->toBe('0.00')
        ->and($response->json('prefill.reverse_charge'))->toBeTrue()
        ->and($response->json('supplier_proposal.name'))->toBe('Neuer Lieferant e.U.')
        ->and($response->json('supplier_proposal.payment_target_days'))->toBe(30);
});

test('scan schlägt ähnliche lieferanten über die dubletten-erkennung vor', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $similar = Supplier::factory()->create([
        'company_id' => $company->id,
        'name' => 'Müller Baustoffe GmbH',
    ]);
    app(CompanyContext::class)->clear();

    fakeScanner(new ScannedInvoice(supplierName: 'Mueller Baustoffe'));

    $response = $this->post('/incoming-invoices/scan', [
        'file' => UploadedFile::fake()->create('rechnung.pdf', 50, 'application/pdf'),
    ])->assertOk();

    expect($response->json('matches.0.id'))->toBe($similar->id)
        ->and($response->json('matches.0.similarity'))->toBeGreaterThanOrEqual(0.45);
});

test('ohne api-schlüssel antwortet der scan bei unlesbarem pdf mit klarer meldung', function () {
    config(['services.anthropic.key' => null]);
    actingMember();

    // Ein PDF ohne Textschicht (hier: gar kein echtes PDF) kann nur die
    // KI-Stufe lesen — die Meldung erklärt das, statt still zu scheitern.
    $this->post('/incoming-invoices/scan', [
        'file' => UploadedFile::fake()->create('rechnung.pdf', 50, 'application/pdf'),
    ])->assertStatus(422)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'keinen lesbaren Text'));
});

test('rolle site darf nicht scannen', function () {
    actingMember(CompanyRole::Site);

    $this->post('/incoming-invoices/scan', [
        'file' => UploadedFile::fake()->create('rechnung.pdf', 50, 'application/pdf'),
    ])->assertForbidden();
});

test('neuen lieferanten aus erkannten konditionen anlegen', function () {
    [, $company] = actingMember();

    $response = $this->postJson('/incoming-invoices/scan/supplier', [
        'name' => 'Neuer Lieferant e.U.',
        'payment_target_days' => 21,
        'skonto_percent' => '3.00',
        'skonto_days' => 14,
        'notes' => "UID: ATU99999999\nIBAN: AT611904300234573201",
    ])->assertCreated();

    $supplier = Supplier::withoutGlobalScopes()->firstOrFail();
    expect($response->json('id'))->toBe($supplier->id)
        ->and($supplier->company_id)->toBe($company->id)
        ->and($supplier->payment_target_days)->toBe(21)
        ->and($supplier->skonto_percent)->toBe('3.00')
        ->and($supplier->skonto_days)->toBe(14)
        ->and($supplier->active)->toBeTrue()
        ->and($supplier->normalized_name)->toBe('neuer lieferant e u');
});

test('speichern mit scan_token hängt die datei als beleg an', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);
    $costType = CostType::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $token = (string) Str::uuid();
    Storage::disk('documents')->put(
        "testing/{$company->id}/scan/{$token}/rechnung.pdf",
        '%PDF-1.4 test',
    );

    $this->post('/incoming-invoices', [
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-07-01',
        'amount_mode' => 'net',
        'amount' => '1000.00',
        'vat_rate' => '20.00',
        'cost_type_id' => $costType->id,
        'scan_token' => $token,
    ])->assertSessionHasNoErrors();

    $invoice = IncomingInvoice::withoutGlobalScopes()->firstOrFail();
    $document = Document::withoutGlobalScopes()->firstOrFail();

    expect($document->documentable_id)->toBe($invoice->id)
        ->and($document->original_name)->toBe('rechnung.pdf')
        ->and($document->category->value)->toBe('invoice');

    Storage::disk('documents')->assertExists($document->path);

    // Der Zwischenspeicher ist geräumt.
    expect(Storage::disk('documents')->files("testing/{$company->id}/scan/{$token}"))->toBe([]);
});

test('fremdes oder abgelaufenes scan_token wird still ignoriert', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);
    $costType = CostType::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->post('/incoming-invoices', [
        'supplier_id' => $supplier->id,
        'invoice_date' => '2026-07-01',
        'amount_mode' => 'net',
        'amount' => '500.00',
        'vat_rate' => '20.00',
        'cost_type_id' => $costType->id,
        'scan_token' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();

    expect(IncomingInvoice::withoutGlobalScopes()->count())->toBe(1)
        ->and(Document::withoutGlobalScopes()->count())->toBe(0);
});

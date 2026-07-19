<?php

use App\Models\Customer;
use App\Models\Document;
use App\Models\Offer;
use App\Models\OutgoingInvoice;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Scan-Leiter für eigene Belege (Ausgangsrechnungen, Angebote): der
 * gesuchte Partner ist hier der KUNDE (Empfänger) — alles ohne KI.
 */
beforeEach(function () {
    Storage::fake('documents');
    config(['services.anthropic.key' => null]);
});

afterEach(function () {
    app(CompanyContext::class)->clear();
});

function textPdf(string ...$lines): string
{
    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 11);

    foreach ($lines as $line) {
        $pdf->Cell(0, 8, utf8_decode($line), 0, 1);
    }

    return $pdf->Output('S');
}

test('ausgangsrechnung: kunde wird im text erkannt, formular vorbefüllt', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $customer = Customer::factory()->create([
        'company_id' => $company->id,
        'name' => 'Wohnbau Steiner GmbH',
        'payment_target_days' => 14,
    ]);
    app(CompanyContext::class)->clear();

    $pdf = textPdf(
        'Bau GmbH — Musterstraße 1, 4020 Linz',
        'An: Wohnbau Steiner GmbH',
        'Rechnung Nr: 250199',
        'Rechnungsdatum: 01.07.2026',
        'Nettobetrag: 10.000,00 EUR',
        '20 % USt: 2.000,00 EUR',
        'Gesamtbetrag: 12.000,00 EUR',
        'Zahlbar innerhalb 14 Tagen.',
    );

    $response = $this->post('/outgoing-invoices/scan', [
        'file' => TestingFile::createWithContent('rechnung.pdf', $pdf),
    ])->assertOk();

    expect($response->json('source'))->toBe('text')
        ->and($response->json('extraction.partner_name'))->toBe('Wohnbau Steiner GmbH')
        ->and($response->json('matches.0.id'))->toBe($customer->id)
        ->and($response->json('matches.0.similarity'))->toBe(1)
        ->and($response->json('prefill.number'))->toBe('250199')
        ->and($response->json('prefill.invoice_date'))->toBe('2026-07-01')
        ->and($response->json('prefill.amount'))->toBe('10000.00')
        ->and($response->json('prefill.amount_mode'))->toBe('net')
        ->and($response->json('prefill.due_on'))->toBe('2026-07-15');
});

test('angebot: nummer und angebotssumme werden erkannt', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    Customer::factory()->create(['company_id' => $company->id, 'name' => 'Wohnbau Steiner GmbH']);
    app(CompanyContext::class)->clear();

    $pdf = textPdf(
        'Angebot Nr: AN-2026-12',
        'Datum: 05.07.2026',
        'An: Wohnbau Steiner GmbH',
        'Sanierung Dachgeschoss',
        'Angebotssumme netto: 5.000,00 EUR',
    );

    $response = $this->post('/offers/scan', [
        'file' => TestingFile::createWithContent('angebot.pdf', $pdf),
    ])->assertOk();

    expect($response->json('source'))->toBe('text')
        ->and($response->json('extraction.partner_name'))->toBe('Wohnbau Steiner GmbH')
        ->and($response->json('prefill.offer_number'))->toBe('AN-2026-12')
        ->and($response->json('prefill.offer_amount_net'))->toBe('5000.00');
});

test('neuen kunden aus erkannten daten anlegen (mit uid)', function () {
    [, $company] = actingMember();

    $response = $this->postJson('/partners/customer', [
        'name' => 'Wohnbau Steiner GmbH',
        'payment_target_days' => 21,
        'vat_id' => 'ATU44455566',
    ])->assertCreated();

    $customer = Customer::withoutGlobalScopes()->firstOrFail();
    expect($response->json('id'))->toBe($customer->id)
        ->and($customer->company_id)->toBe($company->id)
        ->and($customer->payment_target_days)->toBe(21)
        ->and($customer->vat_id)->toBe('ATU44455566');
});

test('ausgangsrechnung speichern mit scan_token hängt die datei als beleg an', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $token = (string) Str::uuid();
    Storage::disk('documents')->put("testing/{$company->id}/scan/{$token}/re250199.pdf", '%PDF-1.4 test');

    $this->post('/outgoing-invoices', [
        'doc_type' => 'invoice',
        'number' => '250199',
        'invoice_date' => '2026-07-01',
        'customer_id' => $customer->id,
        'amount_mode' => 'net',
        'amount' => '10000.00',
        'vat_rate' => '20',
        'scan_token' => $token,
    ])->assertSessionHasNoErrors();

    $invoice = OutgoingInvoice::withoutGlobalScopes()->firstOrFail();
    $document = Document::withoutGlobalScopes()->firstOrFail();

    expect($document->documentable_type)->toBe('outgoing_invoice')
        ->and($document->documentable_id)->toBe($invoice->id)
        ->and($document->category->value)->toBe('invoice');
});

test('angebot speichern mit scan_token hängt die datei an, edit zeigt sie', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $token = (string) Str::uuid();
    Storage::disk('documents')->put("testing/{$company->id}/scan/{$token}/angebot.pdf", '%PDF-1.4 test');

    $this->post('/offers', [
        'customer_id' => $customer->id,
        'status' => 'offered',
        'offer_number' => 'AN-2026-12',
        'offer_amount_net' => '5000.00',
        'scan_token' => $token,
    ])->assertSessionHasNoErrors();

    $offer = Offer::withoutGlobalScopes()->firstOrFail();
    $document = Document::withoutGlobalScopes()->firstOrFail();

    expect($document->documentable_type)->toBe('offer')
        ->and($document->documentable_id)->toBe($offer->id)
        ->and($document->category->value)->toBe('offer');

    app(CompanyContext::class)->clear();
    $this->get("/offers/{$offer->id}/edit")
        ->assertInertia(fn ($page) => $page->count('documents', 1));
});

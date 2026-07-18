<?php

use App\Models\Supplier;
use App\Support\InvoiceScan\InvoiceScanner;
use App\Support\InvoiceScan\ScannedInvoice;
use App\Support\Tenancy\CompanyContext;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Illuminate\Http\Testing\File as TestingFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Scan-Leiter ohne KI (Stufen 1 und 2): E-Rechnung und Text-PDF werden
 * ohne ANTHROPIC_API_KEY ausgelesen; die KI bleibt Fallback für Fotos
 * und gescannte PDFs.
 */
beforeEach(function () {
    Storage::fake('documents');
    config(['services.anthropic.key' => null]);
});

afterEach(function () {
    app(CompanyContext::class)->clear();
});

function blankPdf(string ...$lines): string
{
    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 11);

    foreach ($lines as $line) {
        $pdf->Cell(0, 8, utf8_decode($line), 0, 1);
    }

    return $pdf->Output('S');
}

function uploadPdf(string $content, string $name = 'rechnung.pdf'): TestingFile
{
    return TestingFile::createWithContent($name, $content);
}

function zugferdFixture(): string
{
    $builder = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_EN16931)
        ->setDocumentInformation('RE-2026-0815', '380', new DateTime('2026-07-01'), 'EUR')
        ->setDocumentSeller('Huber Transporte GmbH')
        ->addDocumentSellerTaxRegistration('VA', 'ATU12345678')
        ->setDocumentBuyer('Bau GmbH')
        ->addDocumentPaymentMeanToCreditTransfer('AT611904300234573201')
        ->addDocumentTax('S', 'VAT', 1000.0, 200.0, 20.0)
        ->addDocumentPaymentTerm('3 % Skonto bei Zahlung binnen 14 Tagen', new DateTime('2026-07-22'))
        ->setDocumentSummation(1200.0, 1200.0, 1000.0, 0.0, 0.0, 1000.0, 200.0)
        ->addNewPosition('1')
        ->setDocumentPositionProductDetails('Schotterlieferung')
        ->setDocumentPositionNetPrice(1000.0)
        ->setDocumentPositionQuantity(1, 'H87')
        ->addDocumentPositionTax('S', 'VAT', 20.0)
        ->setDocumentPositionLineSummation(1000.0);

    return ZugferdDocumentPdfBuilder::fromPdfString($builder, blankPdf('Rechnung RE-2026-0815'))
        ->generateDocument()
        ->downloadString();
}

test('e-rechnung (zugferd) wird ohne ki exakt ausgelesen', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $existing = Supplier::factory()->create(['company_id' => $company->id, 'name' => 'Huber Transporte GmbH']);
    app(CompanyContext::class)->clear();

    $response = $this->post('/incoming-invoices/scan', [
        'file' => uploadPdf(zugferdFixture()),
    ])->assertOk();

    expect($response->json('source'))->toBe('e_rechnung')
        ->and($response->json('extraction.partner_name'))->toBe('Huber Transporte GmbH')
        ->and($response->json('extraction.partner_uid'))->toBe('ATU12345678')
        ->and($response->json('extraction.partner_iban'))->toBe('AT611904300234573201')
        ->and($response->json('extraction.payment_target_days'))->toBe(21)
        ->and($response->json('extraction.skonto_percent'))->toBe(3)
        ->and($response->json('extraction.skonto_days'))->toBe(14)
        ->and($response->json('matches.0.id'))->toBe($existing->id)
        ->and($response->json('prefill.supplier_invoice_no'))->toBe('RE-2026-0815')
        ->and($response->json('prefill.invoice_date'))->toBe('2026-07-01')
        ->and($response->json('prefill.amount'))->toBe('1000.00')
        ->and($response->json('prefill.amount_mode'))->toBe('net')
        ->and($response->json('prefill.vat_rate'))->toBe('20.00')
        ->and($response->json('prefill.payment_due_on'))->toBe('2026-07-22')
        ->and($response->json('prefill.skonto_amount'))->toBe('36.00')
        ->and($response->json('prefill.skonto_until'))->toBe('2026-07-15');
});

test('text-pdf wird ohne ki über muster gelesen, bestehender lieferant im text erkannt', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $existing = Supplier::factory()->create(['company_id' => $company->id, 'name' => 'Huber Transporte GmbH']);
    app(CompanyContext::class)->clear();

    $pdf = blankPdf(
        'Huber Transporte GmbH',
        'UID: ATU12345678',
        'Rechnung Nr: RE-2026-0815',
        'Rechnungsdatum: 01.07.2026',
        'Nettobetrag: 1.000,00 EUR',
        '20 % USt: 200,00 EUR',
        'Gesamtbetrag: 1.200,00 EUR',
        'Zahlbar innerhalb 21 Tagen. 3 % Skonto bei Zahlung binnen 14 Tagen.',
    );

    $response = $this->post('/incoming-invoices/scan', ['file' => uploadPdf($pdf)])->assertOk();

    // Der Lieferant steht wörtlich im Text — Treffer mit 100 %.
    expect($response->json('source'))->toBe('text')
        ->and($response->json('extraction.partner_name'))->toBe('Huber Transporte GmbH')
        ->and($response->json('matches.0.id'))->toBe($existing->id)
        ->and($response->json('matches.0.similarity'))->toBe(1)
        ->and($response->json('prefill.amount'))->toBe('1000.00')
        ->and($response->json('prefill.vat_rate'))->toBe('20.00')
        ->and($response->json('prefill.skonto_amount'))->toBe('36.00')
        ->and($response->json('extraction.payment_target_days'))->toBe(21);
});

test('gescanntes pdf ohne textschicht: klare meldung statt ki-zwang', function () {
    actingMember();

    $this->post('/incoming-invoices/scan', ['file' => uploadPdf(blankPdf())])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'keinen lesbaren Text'));
});

test('foto ohne ki-schlüssel: klare meldung', function () {
    actingMember();

    $this->post('/incoming-invoices/scan', [
        'file' => UploadedFile::fake()->image('foto.jpg'),
    ])->assertStatus(422)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Für Fotos'));
});

test('dünner text mit aktiver ki: die ki-stufe übernimmt', function () {
    config(['services.anthropic.key' => 'sk-test']);
    actingMember();

    test()->mock(InvoiceScanner::class, function ($mock) {
        $mock->shouldReceive('enabled')->andReturn(true);
        $mock->shouldReceive('scan')->once()->andReturn(new ScannedInvoice(
            partnerName: 'Zimmerei Holzmann e.U.',
            gross: 590.0,
        ));
    });

    // Nur ein Gruß im PDF — kein Betrag, keine Nummer: zu dünn für Stufe 2.
    $response = $this->post('/incoming-invoices/scan', [
        'file' => uploadPdf(blankPdf('Servus aus Linz')),
    ])->assertOk();

    expect($response->json('source'))->toBe('ki')
        ->and($response->json('extraction.partner_name'))->toBe('Zimmerei Holzmann e.U.');
});

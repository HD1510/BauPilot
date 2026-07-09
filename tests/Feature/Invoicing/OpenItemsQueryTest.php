<?php

use App\Enums\InvoiceDocType;
use App\Enums\OutgoingPaymentStatus;
use App\Enums\RetentionKind;
use App\Models\Company;
use App\Models\Customer;
use App\Models\OutgoingInvoice;
use App\Support\Invoicing\InvoiceBookkeeper;
use App\Support\Invoicing\InvoiceLedger;
use App\Support\Invoicing\OpenItemsQuery;
use App\Support\Money\MoneyHelper;
use App\Support\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->company = Company::factory()->create();
    app(CompanyContext::class)->set($this->company);
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
});

afterEach(function () {
    app(CompanyContext::class)->clear();
});

function makeInvoice(array $overrides = []): OutgoingInvoice
{
    $amounts = MoneyHelper::fromNet($overrides['net'] ?? '10000.00', $overrides['vat_rate'] ?? '20');

    return OutgoingInvoice::create([
        'doc_type' => $overrides['doc_type'] ?? InvoiceDocType::Invoice,
        'number' => $overrides['number'] ?? fake()->unique()->numerify('25####'),
        'invoice_date' => now()->toDateString(),
        'due_on' => $overrides['due_on'] ?? now()->addDays(14)->toDateString(),
        'customer_id' => test()->customer->id,
        'project_id' => $overrides['project_id'] ?? null,
        'final_invoice_id' => $overrides['final_invoice_id'] ?? null,
        'net' => $amounts['net'],
        'vat_rate' => MoneyHelper::round($overrides['vat_rate'] ?? '20'),
        'vat' => $amounts['vat'],
        'gross' => $amounts['gross'],
    ]);
}

test('offene rechnung: jetzt fällig ist der volle bruttobetrag', function () {
    $invoice = makeInvoice(['net' => '10000.00']);

    $summary = app(InvoiceLedger::class)->summarize($invoice);

    expect($summary['gross_effective'])->toBe(12000.0)
        ->and($summary['due_now'])->toBe(12000.0)
        ->and($summary['status'])->toBe(OutgoingPaymentStatus::Open);
});

test('teilzahlung: status partial, rest fällig', function () {
    $invoice = makeInvoice(['net' => '10000.00']);

    app(InvoiceBookkeeper::class)->bookPayment($invoice, CarbonImmutable::now(), '5000.00');

    $summary = app(InvoiceLedger::class)->summarize($invoice->fresh());

    expect($summary['paid'])->toBe(5000.0)
        ->and($summary['due_now'])->toBe(7000.0)
        ->and($invoice->fresh()->payment_status)->toBe(OutgoingPaymentStatus::Partial);
});

test('haftrücklass: mindert jetzt fällig, bleibt als einbehalten stehen', function () {
    $invoice = makeInvoice(['net' => '10000.00']); // 12.000 brutto
    $bookkeeper = app(InvoiceBookkeeper::class);

    // 5 % Haftrücklass, fällig in 3 Jahren
    $bookkeeper->addRetention($invoice, RetentionKind::Warranty, '600.00', CarbonImmutable::now()->addYears(3), '5.00');

    $summary = app(InvoiceLedger::class)->summarize($invoice->fresh());
    expect($summary['due_now'])->toBe(11400.0)
        ->and($summary['retained_open'])->toBe(600.0);

    // Kunde zahlt den fälligen Teil → Rechnung bleibt partial, Rücklass offen
    $bookkeeper->bookPayment($invoice->fresh(), CarbonImmutable::now(), '11400.00');

    $summary = app(InvoiceLedger::class)->summarize($invoice->fresh());
    expect($summary['due_now'])->toBe(0.0)
        ->and($summary['retained_open'])->toBe(600.0)
        ->and($invoice->fresh()->payment_status)->toBe(OutgoingPaymentStatus::Partial);
});

test('rücklass-freigabe: zahlung mit retention_id, received_at, status paid — nie doppelt abgezogen', function () {
    $invoice = makeInvoice(['net' => '10000.00']);
    $bookkeeper = app(InvoiceBookkeeper::class);

    $retention = $bookkeeper->addRetention($invoice, RetentionKind::Warranty, '600.00', CarbonImmutable::now()->addYears(3));
    $bookkeeper->bookPayment($invoice->fresh(), CarbonImmutable::now(), '11400.00');

    $payment = $bookkeeper->releaseRetention($retention->fresh(), CarbonImmutable::now());

    expect($payment->retention_id)->toBe($retention->id)
        ->and((float) $payment->amount)->toBe(600.0)
        ->and($retention->fresh()->isReceived())->toBeTrue();

    $summary = app(InvoiceLedger::class)->summarize($invoice->fresh());
    expect($summary['paid'])->toBe(12000.0)
        ->and($summary['retained_open'])->toBe(0.0)
        ->and($summary['due_now'])->toBe(0.0)
        ->and($invoice->fresh()->payment_status)->toBe(OutgoingPaymentStatus::Paid);
});

test('überfälliger nicht eingegangener rücklass ist wieder jetzt fällig', function () {
    $invoice = makeInvoice(['net' => '10000.00']);
    $bookkeeper = app(InvoiceBookkeeper::class);

    // Rücklass war gestern fällig und ist nicht eingegangen
    $bookkeeper->addRetention($invoice, RetentionKind::Coverage, '600.00', CarbonImmutable::now()->subDay());

    $summary = app(InvoiceLedger::class)->summarize($invoice->fresh());

    expect($summary['retained_open'])->toBe(0.0)
        ->and($summary['due_now'])->toBe(12000.0);
});

test('gutschrift geht negativ in dieselbe rechnung ein', function () {
    $invoice = makeInvoice(['net' => '10000.00']);
    $bookkeeper = app(InvoiceBookkeeper::class);

    $creditNote = $bookkeeper->createAdjustment(
        $invoice,
        InvoiceDocType::CreditNote,
        'GS-1',
        CarbonImmutable::now(),
        MoneyHelper::fromNet('1000.00', '20'),
        '20',
    );

    expect((float) $creditNote->gross)->toBe(-1200.0);

    $summary = app(InvoiceLedger::class)->summarize($invoice->fresh());
    expect($summary['gross_effective'])->toBe(10800.0)
        ->and($summary['due_now'])->toBe(10800.0);

    $bookkeeper->bookPayment($invoice->fresh(), CarbonImmutable::now(), '10800.00');
    expect($invoice->fresh()->payment_status)->toBe(OutgoingPaymentStatus::Paid);
});

test('volles storno: saldo null, status cancelled statt paid', function () {
    $invoice = makeInvoice(['net' => '10000.00']);

    app(InvoiceBookkeeper::class)->createAdjustment(
        $invoice,
        InvoiceDocType::Cancellation,
        'ST-1',
        CarbonImmutable::now(),
        MoneyHelper::fromNet('10000.00', '20'),
        '20',
    );

    $invoice->refresh();

    expect($invoice->payment_status)->toBe(OutgoingPaymentStatus::Cancelled);

    $summary = app(InvoiceLedger::class)->summarize($invoice);
    expect($summary['gross_effective'])->toBe(0.0)
        ->and($summary['due_now'])->toBe(0.0);
});

test('gutschrift/storno auf eine gutschrift ist nicht erlaubt', function () {
    $invoice = makeInvoice(['net' => '10000.00']);
    $bookkeeper = app(InvoiceBookkeeper::class);

    $creditNote = $bookkeeper->createAdjustment(
        $invoice, InvoiceDocType::CreditNote, 'GS-2', CarbonImmutable::now(),
        MoneyHelper::fromNet('100.00', '20'), '20',
    );

    $bookkeeper->createAdjustment(
        $creditNote, InvoiceDocType::Cancellation, 'ST-2', CarbonImmutable::now(),
        MoneyHelper::fromNet('100.00', '20'), '20',
    );
})->throws(InvalidArgumentException::class);

test('teil- und schlussrechnung: restbetrags-konvention ohne doppelzählung', function () {
    // Auftrag 30.000 netto: zwei Teilrechnungen à 10.000, Schlussrechnung Rest 10.000
    $partial1 = makeInvoice(['net' => '10000.00', 'doc_type' => InvoiceDocType::Partial, 'number' => 'TR-1']);
    $partial2 = makeInvoice(['net' => '10000.00', 'doc_type' => InvoiceDocType::Partial, 'number' => 'TR-2']);
    $final = makeInvoice(['net' => '10000.00', 'doc_type' => InvoiceDocType::Final, 'number' => 'SR-1']);

    // Schlussrechnung fasst die Teilrechnungen zusammen (final_invoice_id)
    $partial1->update(['final_invoice_id' => $final->id]);
    $partial2->update(['final_invoice_id' => $final->id]);

    $rows = app(OpenItemsQuery::class)->rows();

    // Drei eigenständige Posten, Summe = 36.000 brutto — nichts doppelt
    expect($rows)->toHaveCount(3)
        ->and(round($rows->sum('due_now'), 2))->toBe(36000.0)
        ->and($final->partials()->count())->toBe(2);

    // Teilrechnungen bezahlen → nur die Schlussrechnung bleibt offen
    $bookkeeper = app(InvoiceBookkeeper::class);
    $bookkeeper->bookPayment($partial1, CarbonImmutable::now(), '12000.00');
    $bookkeeper->bookPayment($partial2, CarbonImmutable::now(), '12000.00');

    $rows = app(OpenItemsQuery::class)->rows();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()['invoice']->id)->toBe($final->id)
        ->and($rows->first()['due_now'])->toBe(12000.0);
});

test('offene-posten-liste blendet bezahlte und stornierte aus, includeSettled zeigt sie', function () {
    $paid = makeInvoice(['net' => '1000.00', 'number' => 'A-1']);
    app(InvoiceBookkeeper::class)->bookPayment($paid, CarbonImmutable::now(), '1200.00');

    makeInvoice(['net' => '2000.00', 'number' => 'A-2']);

    $query = app(OpenItemsQuery::class);

    expect($query->rows())->toHaveCount(1)
        ->and($query->rows(includeSettled: true))->toHaveCount(2)
        ->and($query->totals()['due_now'])->toBe(2400.0);
});

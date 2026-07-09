<?php

use App\Enums\CompanyRole;
use App\Enums\OfferStatus;
use App\Enums\OutgoingPaymentStatus;
use App\Models\CostType;
use App\Models\Customer;
use App\Models\IncomingInvoice;
use App\Models\Offer;
use App\Models\OutgoingInvoice;
use App\Models\Project;
use App\Models\Retention;
use App\Models\Supplier;
use App\Support\Tenancy\CompanyContext;

afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('durchspiel: vom angebot bis zur bezahlten schlussrechnung samt rücklass', function () {
    [, $company] = actingMember();
    $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Huber Wohnbau']);

    // 1. Angebot anlegen und annehmen
    $this->post('/offers', [
        'customer_id' => $customer->id,
        'location' => 'Hauptstraße 1, Hollabrunn',
        'status' => 'offered',
        'offer_amount_net' => '30000.00',
    ])->assertSessionHasNoErrors();

    $offer = Offer::query()->firstOrFail();

    // 2. Übernahme ins Projekt
    $this->post("/offers/{$offer->id}/convert")->assertSessionHasNoErrors();

    $offer->refresh();
    $project = Project::query()->firstOrFail();

    expect($offer->status)->toBe(OfferStatus::Accepted)
        ->and($offer->project_id)->toBe($project->id)
        ->and($project->customer_id)->toBe($customer->id);

    // 3. Teilrechnung 12.000 brutto (10.000 netto, 20 %)
    $this->post('/outgoing-invoices', [
        'doc_type' => 'partial',
        'number' => 'TR-100',
        'invoice_date' => now()->toDateString(),
        'customer_id' => $customer->id,
        'project_id' => $project->id,
        'amount_mode' => 'net',
        'amount' => '10000.00',
        'vat_rate' => '20',
    ])->assertSessionHasNoErrors();

    $partial = OutgoingInvoice::query()->where('number', 'TR-100')->firstOrFail();
    expect((float) $partial->gross)->toBe(12000.0)
        // due_on aus dem Kunden-Zahlungsziel vorbefüllt (Standard 14 Tage)
        ->and($partial->due_on->toDateString())->toBe(now()->addDays(14)->toDateString());

    // 4. Schlussrechnung über den Rest (20.000 netto), Teilrechnung zugeordnet
    $this->post('/outgoing-invoices', [
        'doc_type' => 'final',
        'number' => 'SR-100',
        'invoice_date' => now()->toDateString(),
        'customer_id' => $customer->id,
        'project_id' => $project->id,
        'amount_mode' => 'net',
        'amount' => '20000.00',
        'vat_rate' => '20',
        'partial_ids' => [$partial->id],
    ])->assertSessionHasNoErrors();

    $final = OutgoingInvoice::query()->where('number', 'SR-100')->firstOrFail();
    expect($partial->refresh()->final_invoice_id)->toBe($final->id);

    // 5. Haftrücklass 5 % auf die Schlussrechnung (1.200 von 24.000 brutto)
    $this->post("/outgoing-invoices/{$final->id}/retentions", [
        'kind' => 'warranty',
        'amount' => '1200.00',
        'percent' => '5',
        'due_on' => now()->addYears(3)->toDateString(),
    ])->assertSessionHasNoErrors();

    // 6. Teilrechnung voll bezahlen, Schlussrechnung abzüglich Rücklass
    $this->post("/outgoing-invoices/{$partial->id}/payments", [
        'paid_on' => now()->toDateString(),
        'amount' => '12000.00',
    ])->assertSessionHasNoErrors();

    $this->post("/outgoing-invoices/{$final->id}/payments", [
        'paid_on' => now()->toDateString(),
        'amount' => '22800.00',
    ])->assertSessionHasNoErrors();

    expect($partial->refresh()->payment_status)->toBe(OutgoingPaymentStatus::Paid)
        ->and($final->refresh()->payment_status)->toBe(OutgoingPaymentStatus::Partial);

    // 7. Jahre später: Haftrücklass geht ein → Schlussrechnung bezahlt
    $retention = Retention::query()->firstOrFail();

    $this->post("/outgoing-invoices/{$final->id}/retentions/{$retention->id}/release", [
        'paid_on' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    expect($final->refresh()->payment_status)->toBe(OutgoingPaymentStatus::Paid)
        ->and($retention->refresh()->isReceived())->toBeTrue();

    // Offene-Posten-Liste ist leer
    $this->get('/outgoing-invoices')
        ->assertInertia(fn ($page) => $page->count('rows', 0)->where('totals.due_now', 0));
});

test('rechnungsnummer ist je firma eindeutig', function () {
    [, $company] = actingMember();
    $customer = Customer::factory()->create(['company_id' => $company->id]);

    $payload = [
        'doc_type' => 'invoice',
        'number' => '250184',
        'invoice_date' => now()->toDateString(),
        'customer_id' => $customer->id,
        'amount_mode' => 'net',
        'amount' => '100.00',
        'vat_rate' => '20',
    ];

    $this->post('/outgoing-invoices', $payload)->assertSessionHasNoErrors();
    $this->post('/outgoing-invoices', $payload)->assertSessionHasErrors('number');
});

test('steuersatz 0 verlangt einen strukturierten grund (§19)', function () {
    [, $company] = actingMember();
    $customer = Customer::factory()->create(['company_id' => $company->id]);

    $payload = [
        'doc_type' => 'invoice',
        'number' => 'RC-1',
        'invoice_date' => now()->toDateString(),
        'customer_id' => $customer->id,
        'amount_mode' => 'net',
        'amount' => '5000.00',
        'vat_rate' => '0',
    ];

    $this->post('/outgoing-invoices', $payload)->assertSessionHasErrors('zero_rate_reason');

    $this->post('/outgoing-invoices', [...$payload, 'zero_rate_reason' => 'reverse_charge_19_1a'])
        ->assertSessionHasNoErrors();

    $invoice = OutgoingInvoice::query()->where('number', 'RC-1')->firstOrFail();
    expect((float) $invoice->vat)->toBe(0.0)
        ->and((float) $invoice->gross)->toBe(5000.0);
});

test('eingangsrechnung: reverse charge erzwingt satz 0, skonto-zahlung gilt als bezahlt', function () {
    [, $company] = actingMember();
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);
    $costType = CostType::factory()->create(['company_id' => $company->id]);

    $this->post('/incoming-invoices', [
        'supplier_id' => $supplier->id,
        'supplier_invoice_no' => 'L-500',
        'invoice_date' => now()->toDateString(),
        'amount_mode' => 'net',
        'amount' => '10000.00',
        'vat_rate' => '20',
        'reverse_charge' => true,
        'cost_type_id' => $costType->id,
        'skonto_amount' => '300.00',
        'skonto_until' => now()->addDays(14)->toDateString(),
    ])->assertSessionHasNoErrors();

    $invoice = IncomingInvoice::query()->firstOrFail();

    // Reverse charge: Satz 0, USt 0, brutto = netto
    expect((float) $invoice->vat)->toBe(0.0)
        ->and((float) $invoice->gross)->toBe(10000.0)
        // Zahlbar-bis aus dem Lieferanten-Ziel (Standard 30 Tage)
        ->and($invoice->payment_due_on->toDateString())->toBe(now()->addDays(30)->toDateString());

    // Zahlung mit gezogenem Skonto innerhalb der Frist → bezahlt
    $this->post("/incoming-invoices/{$invoice->id}/pay", [
        'paid_on' => now()->addDays(7)->toDateString(),
        'paid_amount' => '9700.00',
    ])->assertSessionHasNoErrors();

    $invoice->refresh();
    expect($invoice->payment_status->value)->toBe('paid')
        ->and((float) $invoice->paid_amount)->toBe(9700.0);
});

test('rolle baustelle erhält keine finanzrouten und keine beträge im projekt', function () {
    [, $company] = actingMember(CompanyRole::Site);
    $project = Project::factory()->create(['company_id' => $company->id]);

    app(CompanyContext::class)->runFor(
        $company,
        fn () => $project->changeOrders()->create(['title' => 'Mehr Beton', 'amount_net' => '5000.00', 'status' => 'requested']),
    );

    // Finanzrouten sind komplett zu
    $this->get('/outgoing-invoices')->assertForbidden();
    $this->get('/incoming-invoices')->assertForbidden();
    $this->get('/offers')->assertForbidden();

    // Projekt sichtbar, aber ohne Beträge und Belege
    $this->get("/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canViewFinancials', false)
            ->where('openItems', null)
            ->where('externalOffers', null)
            ->where('incomingInvoices', null)
            ->where('changeOrders.0.title', 'Mehr Beton')
            ->where('changeOrders.0.amount_net', null),
        );
});

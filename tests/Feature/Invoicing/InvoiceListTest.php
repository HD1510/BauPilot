<?php

use App\Models\CostType;
use App\Models\Customer;
use App\Models\IncomingInvoice;
use App\Models\OutgoingInvoice;
use App\Models\Supplier;
use App\Support\Tenancy\CompanyContext;

/**
 * Rechnungslisten: Suche, Sortierung und Seitenaufteilung — die Summe
 * gilt immer für alle Treffer, nicht nur für die angezeigte Seite.
 */
afterEach(function () {
    app(CompanyContext::class)->clear();
});

/**
 * @return array{0: Supplier, 1: Supplier, 2: CostType}
 */
function invoiceListFixture(int $companyId): array
{
    $huber = Supplier::factory()->create(['company_id' => $companyId, 'name' => 'Huber Baustoffe']);
    $maier = Supplier::factory()->create(['company_id' => $companyId, 'name' => 'Maier Transporte']);
    $costType = CostType::factory()->create(['company_id' => $companyId]);

    return [$huber, $maier, $costType];
}

test('eingangsrechnungen: suche nach lieferant grenzt liste und summe ein', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    [$huber, $maier, $costType] = invoiceListFixture($company->id);
    $base = ['company_id' => $company->id, 'cost_type_id' => $costType->id];
    IncomingInvoice::factory()->create($base + ['supplier_id' => $huber->id, 'gross' => '120.00']);
    IncomingInvoice::factory()->create($base + ['supplier_id' => $huber->id, 'gross' => '80.00']);
    IncomingInvoice::factory()->create($base + ['supplier_id' => $maier->id, 'gross' => '999.00']);
    app(CompanyContext::class)->clear();

    $this->get('/incoming-invoices?q=huber')
        ->assertInertia(fn ($page) => $page
            ->has('invoices', 2)
            ->where('summary.count', 2)
            ->where('summary.gross_sum', fn ($sum) => (float) $sum === 200.0)
            ->where('filters.q', 'huber'));
});

test('eingangsrechnungen: sortierung nach betrag und nach lieferant', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    [$huber, $maier, $costType] = invoiceListFixture($company->id);
    $base = ['company_id' => $company->id, 'cost_type_id' => $costType->id];
    IncomingInvoice::factory()->create($base + ['supplier_id' => $maier->id, 'gross' => '50.00']);
    IncomingInvoice::factory()->create($base + ['supplier_id' => $huber->id, 'gross' => '900.00']);
    app(CompanyContext::class)->clear();

    $this->get('/incoming-invoices?sort=amount_desc')
        ->assertInertia(fn ($page) => $page->where('invoices.0.supplier', 'Huber Baustoffe'));

    $this->get('/incoming-invoices?sort=amount_asc')
        ->assertInertia(fn ($page) => $page->where('invoices.0.supplier', 'Maier Transporte'));

    $this->get('/incoming-invoices?sort=supplier_asc')
        ->assertInertia(fn ($page) => $page
            ->where('invoices.0.supplier', 'Huber Baustoffe')
            ->where('invoices.1.supplier', 'Maier Transporte'));
});

test('eingangsrechnungen: unbekannte sortierung fällt auf den standard zurück', function () {
    actingMember();

    $this->get('/incoming-invoices?sort=irgendwas;drop')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.sort', 'date_desc'));
});

test('eingangsrechnungen: zweite seite, summe bleibt über alle treffer', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    [$huber, , $costType] = invoiceListFixture($company->id);
    IncomingInvoice::factory()->count(52)->create([
        'company_id' => $company->id,
        'supplier_id' => $huber->id,
        'cost_type_id' => $costType->id,
        'gross' => '10.00',
    ]);
    app(CompanyContext::class)->clear();

    $this->get('/incoming-invoices')
        ->assertInertia(fn ($page) => $page
            ->has('invoices', 50)
            ->where('pagination.last_page', 2)
            ->where('summary.count', 52));

    $this->get('/incoming-invoices?page=2')
        ->assertInertia(fn ($page) => $page
            ->has('invoices', 2)
            ->where('pagination.page', 2)
            ->where('summary.gross_sum', fn ($sum) => (float) $sum === 520.0));
});

test('ausgangsrechnungen: suche nach kunde und sortierung nach betrag', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $huber = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Familie Huber']);
    $maier = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Maier GmbH']);
    $base = ['company_id' => $company->id, 'vat_rate' => '20.00'];
    OutgoingInvoice::factory()->create($base + ['customer_id' => $huber->id, 'number' => '250001', 'net' => '100.00', 'vat' => '20.00', 'gross' => '120.00']);
    OutgoingInvoice::factory()->create($base + ['customer_id' => $maier->id, 'number' => '250002', 'net' => '1000.00', 'vat' => '200.00', 'gross' => '1200.00']);
    app(CompanyContext::class)->clear();

    $this->get('/outgoing-invoices?q=maier')
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.number', '250002')
            ->where('summary.count', 1)
            ->where('summary.gross_sum', 1200));

    $this->get('/outgoing-invoices?sort=amount_desc')
        ->assertInertia(fn ($page) => $page
            ->has('rows', 2)
            ->where('rows.0.number', '250002')
            ->where('summary.count', 2));
});

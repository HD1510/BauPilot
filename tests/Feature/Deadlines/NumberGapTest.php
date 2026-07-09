<?php

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Customer;
use App\Models\OutgoingInvoice;
use App\Support\Invoicing\NumberGapService;
use App\Support\Tenancy\CompanyContext;

afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('die lückenprüfung findet fehlende rechnungsnummern im geschäftsjahr', function () {
    $company = Company::factory()->create(['fiscal_year_start_month' => 1]);
    app(CompanyContext::class)->set($company);
    $customer = Customer::factory()->create(['company_id' => $company->id]);

    foreach (['250181', '250182', '250184'] as $number) {
        OutgoingInvoice::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'number' => $number,
            'invoice_date' => now()->toDateString(),
        ]);
    }

    $result = app(NumberGapService::class)->checkCurrentFiscalYear();

    expect($result['gaps'])->toBe([250183])
        ->and($result['checked'])->toBe(3);
});

test('belege vor dem geschäftsjahresbeginn werden nicht geprüft', function () {
    // GJ beginnt im September (Standard) — eine alte Nummer aus dem Vorjahr
    // erzeugt keine künstliche Riesenlücke.
    $company = Company::factory()->create(['fiscal_year_start_month' => 9]);
    app(CompanyContext::class)->set($company);
    $customer = Customer::factory()->create(['company_id' => $company->id]);

    OutgoingInvoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => '240001',
        'invoice_date' => now()->subYears(2)->toDateString(),
    ]);
    OutgoingInvoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'number' => '250100',
        'invoice_date' => now()->toDateString(),
    ]);

    $result = app(NumberGapService::class)->checkCurrentFiscalYear();

    expect($result['checked'])->toBe(1)
        ->and($result['gaps'])->toBe([]);
});

test('dashboard zeigt offene posten und fristen für büro, nicht für baustelle', function () {
    [, $company] = actingMember();
    OutgoingInvoice::factory()->create([
        'company_id' => $company->id,
        'due_on' => now()->subDays(3)->toDateString(),
    ]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('hasCompany', true)
            ->where('canViewFinancials', true)
            ->has('openItems')
            ->has('overdueInvoices', 1),
        );

    // Rolle Baustelle: keine Finanz-Props
    [$siteUser] = actingMember(CompanyRole::Site);

    $this->actingAs($siteUser)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canViewFinancials', false)
            ->missing('openItems')
            ->missing('overdueInvoices'),
        );

    // Fristen-Seite erreichbar
    $this->get('/deadlines')->assertOk();
});

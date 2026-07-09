<?php

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\CompanyContext;

afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('rolle baustelle darf keine finanzdaten sehen', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $company->users()->attach($user->id, ['role' => CompanyRole::Site->value]);

    app(CompanyContext::class)->set($company);

    expect($user->can('view-financials'))->toBeFalse();
});

test('rollen admin und büro dürfen finanzdaten sehen', function (CompanyRole $role) {
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $company->users()->attach($user->id, ['role' => $role->value]);

    app(CompanyContext::class)->set($company);

    expect($user->can('view-financials'))->toBeTrue();
})->with([CompanyRole::Admin, CompanyRole::Office]);

test('ohne aktive firma gibt es keine finanzdaten', function () {
    $user = User::factory()->create();

    app(CompanyContext::class)->clear();

    expect($user->can('view-financials'))->toBeFalse();
});

test('die rolle gilt je firma: admin in a, baustelle in b', function () {
    $companyA = Company::factory()->create(['name' => 'A']);
    $companyB = Company::factory()->create(['name' => 'B']);
    $user = User::factory()->create();
    $companyA->users()->attach($user->id, ['role' => CompanyRole::Admin->value]);
    $companyB->users()->attach($user->id, ['role' => CompanyRole::Site->value]);

    $this->actingAs($user)
        ->withSession(['active_company_id' => $companyA->id])
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('tenancy.canViewFinancials', true));

    $this->actingAs($user)
        ->withSession(['active_company_id' => $companyB->id])
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('tenancy.canViewFinancials', false)
            ->where('tenancy.role', 'site'),
        );
});

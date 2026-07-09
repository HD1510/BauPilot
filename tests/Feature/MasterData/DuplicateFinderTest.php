<?php

use App\Models\Company;
use App\Models\Customer;
use App\Support\Duplicates\DuplicateFinder;
use App\Support\Duplicates\NameNormalizer;
use App\Support\Tenancy\CompanyContext;

afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('normalisierung: kleinschreibung, umlaute, satzzeichen, leerzeichen', function () {
    expect(NameNormalizer::normalize('  Müller-Bau  GmbH & Co. KG '))
        ->toBe('mueller bau gmbh co kg')
        ->and(NameNormalizer::normalize('STRAßENBAU Öfner'))
        ->toBe('strassenbau oefner');
});

test('ähnliche schreibweisen werden als dublette vorgeschlagen', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    Customer::create(['name' => 'Müller Bau GmbH']);

    $matches = app(DuplicateFinder::class)->findSimilar(Customer::class, 'Mueller-Bau GmbH');

    expect($matches)->toHaveCount(1)
        ->and($matches->first()['name'])->toBe('Müller Bau GmbH')
        ->and($matches->first()['similarity'])->toBeGreaterThan(0.5);
});

test('unähnliche namen werden nicht vorgeschlagen', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    Customer::create(['name' => 'Müller Bau GmbH']);

    $matches = app(DuplicateFinder::class)->findSimilar(Customer::class, 'Zimmerei Hofer');

    expect($matches)->toBeEmpty();
});

test('dubletten anderer firmen werden nicht vorgeschlagen', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $context = app(CompanyContext::class);

    $context->runFor($companyB, fn () => Customer::create(['name' => 'Müller Bau GmbH']));

    $context->set($companyA);
    $matches = app(DuplicateFinder::class)->findSimilar(Customer::class, 'Müller Bau GmbH');

    expect($matches)->toBeEmpty();
});

test('ein bestehender datensatz schlägt sich selbst nicht vor (ignoreId)', function () {
    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);

    $customer = Customer::create(['name' => 'Müller Bau GmbH']);

    $matches = app(DuplicateFinder::class)->findSimilar(Customer::class, 'Müller Bau GmbH', $customer->id);

    expect($matches)->toBeEmpty();
});

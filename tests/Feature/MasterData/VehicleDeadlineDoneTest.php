<?php

use App\Enums\CompanyRole;
use App\Models\Vehicle;
use App\Models\VehicleDate;
use App\Support\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;

/**
 * Wiederkehrende Fahrzeug-Fristen (Pickerl, Vignette, eigene Termine)
 * lassen sich als erledigt markieren — der Termin wandert ein Jahr
 * weiter, bei länger Überfälligem bis in die Zukunft.
 */
afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('pickerl erledigt schiebt den termin ein jahr weiter', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $due = CarbonImmutable::today()->addWeeks(2);
    $vehicle = Vehicle::factory()->create([
        'company_id' => $company->id,
        'inspection_due_on' => $due->toDateString(),
    ]);
    app(CompanyContext::class)->clear();

    $this->post("/vehicles/{$vehicle->id}/inspection-done")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($vehicle->refresh()->inspection_due_on?->toDateString())
        ->toBe($due->addYear()->toDateString());
});

test('lange überfälliges pickerl landet wieder in der zukunft', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $vehicle = Vehicle::factory()->create([
        'company_id' => $company->id,
        'inspection_due_on' => CarbonImmutable::today()->subYears(2)->subMonth()->toDateString(),
    ]);
    app(CompanyContext::class)->clear();

    $this->post("/vehicles/{$vehicle->id}/inspection-done")->assertRedirect();

    $next = $vehicle->refresh()->inspection_due_on;

    expect($next?->greaterThan(CarbonImmutable::today()))->toBeTrue()
        // Monat bleibt verankert (§57a): gleiches Monats-/Tagesdatum.
        ->and($next?->format('m-d'))
        ->toBe(CarbonImmutable::today()->subYears(2)->subMonth()->format('m-d'));
});

test('vignette und eigene fahrzeug-termine sind ebenso erledigbar', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $vehicle = Vehicle::factory()->create([
        'company_id' => $company->id,
        'vignette_until' => '2026-11-30',
    ]);
    $date = VehicleDate::factory()->create([
        'company_id' => $company->id,
        'vehicle_id' => $vehicle->id,
        'label' => 'Service',
        'due_on' => '2026-11-15',
    ]);
    app(CompanyContext::class)->clear();

    $this->post("/vehicles/{$vehicle->id}/vignette-done")->assertRedirect();
    $this->post("/vehicles/{$vehicle->id}/dates/{$date->id}/done")->assertRedirect();

    expect($vehicle->refresh()->vignette_until?->toDateString())->toBe('2027-11-30')
        ->and($date->refresh()->due_on->toDateString())->toBe('2027-11-15');
});

test('die frist liefert den erledigt-link mit', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $vehicle = Vehicle::factory()->create([
        'company_id' => $company->id,
        'inspection_due_on' => CarbonImmutable::today()->addWeeks(2)->toDateString(),
    ]);
    app(CompanyContext::class)->clear();

    $this->get('/deadlines')
        ->assertInertia(fn ($page) => $page
            ->where('deadlines', fn ($deadlines) => collect($deadlines)
                ->contains(fn ($row) => ($row['complete_url'] ?? null) === "/vehicles/{$vehicle->id}/inspection-done")));
});

test('rolle baustelle darf keine fristen erledigen', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $vehicle = Vehicle::factory()->create([
        'company_id' => $company->id,
        'inspection_due_on' => '2026-11-01',
    ]);
    app(CompanyContext::class)->clear();

    $this->post("/vehicles/{$vehicle->id}/inspection-done")->assertForbidden();

    expect($vehicle->refresh()->inspection_due_on?->toDateString())->toBe('2026-11-01');
});

test('ohne eingetragenes pickerl gibt es nichts zu erledigen', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $vehicle = Vehicle::factory()->create([
        'company_id' => $company->id,
        'inspection_due_on' => null,
    ]);
    app(CompanyContext::class)->clear();

    $this->post("/vehicles/{$vehicle->id}/inspection-done")->assertNotFound();
});

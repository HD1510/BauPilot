<?php

use App\Enums\CompanyRole;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Vacation;
use App\Support\Tenancy\CompanyContext;

/**
 * Urlaube je Mitarbeiter: erfasst beim Mitarbeiter, automatisch in
 * der Einteilung sichtbar (ausgegraut bzw. als Tageszeile).
 */
afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('urlaub wird eingetragen und wieder entfernt', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->post("/employees/{$employee->id}/vacations", [
        'starts_on' => '2026-11-23',
        'ends_on' => '2026-11-27',
    ])->assertRedirect()->assertSessionHas('success');

    $vacation = Vacation::withoutGlobalScopes()->sole();

    expect($vacation->starts_on->toDateString())->toBe('2026-11-23')
        ->and($vacation->ends_on->toDateString())->toBe('2026-11-27');

    $this->delete("/employees/{$employee->id}/vacations/{$vacation->id}")->assertRedirect();

    expect(Vacation::withoutGlobalScopes()->count())->toBe(0);
});

test('das urlaubsende darf nicht vor dem beginn liegen', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->post("/employees/{$employee->id}/vacations", [
        'starts_on' => '2026-11-27',
        'ends_on' => '2026-11-23',
    ])->assertSessionHasErrors('ends_on');

    expect(Vacation::withoutGlobalScopes()->count())->toBe(0);
});

test('die einteilung liefert urlaube der woche mit', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $employee = Employee::factory()->create(['company_id' => $company->id, 'name' => 'Franz Polier']);
    Vacation::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'starts_on' => '2026-11-23',
        'ends_on' => '2026-11-27',
    ]);
    app(CompanyContext::class)->clear();

    $this->get('/assignments?date=2026-11-25')
        ->assertInertia(fn ($page) => $page
            ->has('vacations', 1)
            ->where('vacations.0.employee_id', $employee->id)
            ->where('vacations.0.name', 'Franz Polier')
            ->where('vacations.0.starts_on', '2026-11-23')
            ->where('vacations.0.ends_on', '2026-11-27'));
});

test('abgelaufene urlaube belasten die einteilung nicht', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    Vacation::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'starts_on' => '2026-10-05',
        'ends_on' => '2026-10-09',
    ]);
    app(CompanyContext::class)->clear();

    $this->get('/assignments?date=2026-11-25')
        ->assertInertia(fn ($page) => $page->has('vacations', 0));
});

test('der wochen-druck zeigt urlauber je tag', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $employee = Employee::factory()->create(['company_id' => $company->id, 'name' => 'Hans Hilfskraft']);
    Vacation::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'starts_on' => '2026-11-25',
        'ends_on' => '2026-11-25',
    ]);
    Assignment::factory()->create(['company_id' => $company->id, 'work_date' => '2026-11-24']);
    app(CompanyContext::class)->clear();

    $this->get('/assignments/print?date=2026-11-25')
        ->assertOk()
        ->assertSee('Urlaub: Hans Hilfskraft');
});

test('rolle baustelle darf keinen urlaub eintragen', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->post("/employees/{$employee->id}/vacations", [
        'starts_on' => '2026-11-23',
        'ends_on' => '2026-11-27',
    ])->assertForbidden();
});

test('fremder urlaub ist unerreichbar', function () {
    actingMember();
    $foreign = \App\Models\Company::factory()->create();
    app(CompanyContext::class)->set($foreign);
    $employee = Employee::factory()->create(['company_id' => $foreign->id]);
    app(CompanyContext::class)->clear();

    $this->post("/employees/{$employee->id}/vacations", [
        'starts_on' => '2026-11-23',
        'ends_on' => '2026-11-27',
    ])->assertNotFound();
});

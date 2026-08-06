<?php

use App\Enums\CompanyRole;
use App\Models\Assignment;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Vehicle;
use App\Support\Tenancy\CompanyContext;

/**
 * Einteilung: Baustellen je Tag mit zugeteilten Mitarbeitern und
 * Fahrzeugen. Doppelbelegungen am selben Tag werden markiert.
 */
afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('einteilung mit mitarbeitern und fahrzeug wird gespeichert', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $employees = Employee::factory()->count(2)->create(['company_id' => $company->id]);
    $vehicle = Vehicle::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->post('/assignments', [
        'work_date' => '2026-08-05',
        'site' => 'Baustelle Goethestraße 20',
        'notes' => 'Treffpunkt 7 Uhr',
        'employee_ids' => $employees->pluck('id')->all(),
        'vehicle_ids' => [$vehicle->id],
    ])->assertRedirect()->assertSessionHas('success');

    $assignment = Assignment::withoutGlobalScopes()->sole();

    expect($assignment->site)->toBe('Baustelle Goethestraße 20')
        ->and($assignment->work_date->toDateString())->toBe('2026-08-05')
        ->and($assignment->employees()->count())->toBe(2)
        ->and($assignment->vehicles()->count())->toBe(1);
});

test('ohne projekt ist die baustelle pflicht', function () {
    actingMember();

    $this->from('/assignments')->post('/assignments', [
        'work_date' => '2026-08-05',
        'site' => '',
        'employee_ids' => [],
        'vehicle_ids' => [],
    ])->assertRedirect('/assignments')->assertSessionHasErrors('site');

    expect(Assignment::withoutGlobalScopes()->count())->toBe(0);
});

test('ein ungültiges projekt wird als validierungsfehler abgefangen', function () {
    actingMember();

    $this->from('/assignments')->post('/assignments', [
        'work_date' => '2026-08-05',
        'project_id' => 'none',
        'site' => 'Baustelle X',
    ])->assertRedirect('/assignments')->assertSessionHasErrors('project_id');

    expect(Assignment::withoutGlobalScopes()->count())->toBe(0);
});

test('im betreff steht die baustelle des projekts, nicht der projekttitel', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create([
        'company_id' => $company->id,
        'title' => 'BV Musterweg',
        'site_address' => 'Goethestraße 20, 2333 Leopoldsdorf',
    ]);
    Assignment::factory()->create([
        'company_id' => $company->id,
        'work_date' => '2026-08-05',
        'project_id' => $project->id,
        'site' => null,
    ]);
    app(CompanyContext::class)->clear();

    $this->get('/assignments?date=2026-08-05')
        ->assertInertia(fn ($page) => $page
            ->where('assignments.0.label', 'Goethestraße 20, 2333 Leopoldsdorf'));
});

test('doppelt eingeteilte mitarbeiter werden am selben tag markiert', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $other = Employee::factory()->create(['company_id' => $company->id]);

    $first = Assignment::factory()->create(['company_id' => $company->id, 'work_date' => '2026-08-05']);
    $second = Assignment::factory()->create(['company_id' => $company->id, 'work_date' => '2026-08-05']);
    $first->employees()->sync([$employee->id, $other->id]);
    $second->employees()->sync([$employee->id]);
    app(CompanyContext::class)->clear();

    $this->get('/assignments?date=2026-08-05')
        ->assertInertia(fn ($page) => $page
            ->component('assignments/index')
            ->where('week.monday', '2026-08-03')
            ->has('assignments', 2)
            ->where('assignments.0.employees', fn ($employees) => collect($employees)
                ->every(fn ($row) => $row['conflict'] === ($row['id'] === $employee->id)))
            ->where('assignments.1.employees.0.conflict', true));
});

test('bearbeiten tauscht mitarbeiter und fahrzeuge aus', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $assignment = Assignment::factory()->create(['company_id' => $company->id]);
    $old = Employee::factory()->create(['company_id' => $company->id]);
    $new = Employee::factory()->create(['company_id' => $company->id]);
    $assignment->employees()->sync([$old->id]);
    app(CompanyContext::class)->clear();

    $this->patch("/assignments/{$assignment->id}", [
        'work_date' => '2026-08-06',
        'site' => 'Neue Baustelle',
        'employee_ids' => [$new->id],
        'vehicle_ids' => [],
    ])->assertRedirect()->assertSessionHas('success');

    expect($assignment->refresh()->site)->toBe('Neue Baustelle')
        ->and($assignment->employees()->pluck('employees.id')->all())->toBe([$new->id]);
});

test('löschen entfernt die einteilung samt zuteilungen', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $assignment = Assignment::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $assignment->employees()->sync([$employee->id]);
    app(CompanyContext::class)->clear();

    $this->delete("/assignments/{$assignment->id}")->assertRedirect();

    expect(Assignment::withoutGlobalScopes()->count())->toBe(0)
        ->and($employee->fresh())->not->toBeNull();
});

test('rolle baustelle sieht die einteilung, darf aber nicht planen', function () {
    [, $company] = actingMember(CompanyRole::Site);

    $this->get('/assignments')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canWrite', false));

    $this->post('/assignments', [
        'work_date' => '2026-08-05',
        'site' => 'Baustelle X',
    ])->assertForbidden();
});

test('fremde mitarbeiter und einteilungen bleiben unerreichbar', function () {
    [, $company] = actingMember();
    $foreign = Company::factory()->create();
    app(CompanyContext::class)->set($foreign);
    $foreignEmployee = Employee::factory()->create(['company_id' => $foreign->id]);
    $foreignAssignment = Assignment::factory()->create(['company_id' => $foreign->id]);
    app(CompanyContext::class)->clear();

    $this->post('/assignments', [
        'work_date' => '2026-08-05',
        'site' => 'Baustelle Y',
        'employee_ids' => [$foreignEmployee->id],
    ])->assertSessionHasErrors('employee_ids.0');

    $this->delete("/assignments/{$foreignAssignment->id}")->assertNotFound();

    expect(Assignment::withoutGlobalScopes()->count())->toBe(1);
});

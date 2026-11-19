<?php

use App\Enums\CompanyRole;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Vehicle;
use App\Support\Tenancy\CompanyContext;

/**
 * „Mein Tag": Die Rolle Baustelle startet mit der eigenen Einteilung,
 * den eigenen Stunden und den eigenen Aufgaben — ohne Finanzzahlen.
 */
afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('baustelle sieht mein tag mit eigener einteilung, kollegen und fahrzeug', function () {
    [$user, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $me = Employee::factory()->create(['company_id' => $company->id, 'name' => 'Max Polier', 'user_id' => $user->id]);
    $colleague = Employee::factory()->create(['company_id' => $company->id, 'name' => 'Anna Helfer']);
    $stranger = Employee::factory()->create(['company_id' => $company->id, 'name' => 'Karl Anders']);
    $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'plate' => 'W-123 AB']);
    $project = Project::factory()->create(['company_id' => $company->id, 'title' => 'BV Musterweg', 'site_address' => 'Musterweg 1']);

    $mine = Assignment::factory()->create([
        'company_id' => $company->id, 'work_date' => today()->toDateString(),
        'project_id' => $project->id, 'site' => null, 'notes' => 'Schlüssel beim Nachbarn',
    ]);
    $mine->employees()->sync([$me->id, $colleague->id]);
    $mine->vehicles()->sync([$vehicle->id]);

    // Fremde Einteilung desselben Tages: gehört nicht zu „meinem" Tag.
    $other = Assignment::factory()->create(['company_id' => $company->id, 'work_date' => today()->toDateString(), 'site' => 'Anderswo']);
    $other->employees()->sync([$stranger->id]);

    // Übermorgen bin ich wieder eingeteilt — das ist „Als Nächstes".
    $later = Assignment::factory()->create(['company_id' => $company->id, 'work_date' => today()->addDays(2)->toDateString(), 'site' => 'Lager']);
    $later->employees()->sync([$me->id]);

    TimeEntry::factory()->create([
        'company_id' => $company->id, 'project_id' => $project->id, 'employee_id' => $me->id,
        'work_date' => today()->toDateString(), 'hours' => '7.5',
    ]);
    app(CompanyContext::class)->clear();

    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->component('my-day')
        ->where('employee.name', 'Max Polier')
        ->has('assignments', 1)
        ->where('assignments.0.label', 'Musterweg 1')
        ->where('assignments.0.project', 'BV Musterweg')
        ->where('assignments.0.notes', 'Schlüssel beim Nachbarn')
        ->where('assignments.0.colleagues', ['Anna Helfer'])
        ->where('assignments.0.vehicles', ['W-123 AB'])
        ->where('assignments.0.hours_recorded', 7.5)
        ->where('next.date', today()->addDays(2)->toDateString())
        ->where('next.assignments.0.label', 'Lager')
        ->where('hours.today', 7.5)
        // Keine Büro-Kennzahlen auf dieser Seite.
        ->missing('openItems')
        ->missing('overdueInvoices'));
});

test('mein tag zeigt nur mir zugeteilte offene aufgaben', function () {
    [$user, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    $other = User::factory()->create();
    Task::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'title' => 'Sockel nachfugen', 'assignee_user_id' => $user->id]);
    Task::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'title' => 'Fremde Aufgabe', 'assignee_user_id' => $other->id]);
    Task::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'title' => 'Schon erledigt', 'assignee_user_id' => $user->id, 'done_at' => now()]);
    app(CompanyContext::class)->clear();

    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->component('my-day')
        ->has('tasks', 1)
        ->where('tasks.0.title', 'Sockel nachfugen'));
});

test('urlaub und fehlende zuordnung werden angezeigt', function () {
    [$user, $company] = actingMember(CompanyRole::Site);

    // Ohne verknüpften Mitarbeiter: Hinweis statt Einteilung.
    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->component('my-day')
        ->where('employee', null)
        ->where('assignments', []));

    app(CompanyContext::class)->set($company);
    $me = Employee::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
    Vacation::factory()->create([
        'company_id' => $company->id, 'employee_id' => $me->id,
        'starts_on' => today()->subDay()->toDateString(), 'ends_on' => today()->addDays(3)->toDateString(),
        'notes' => 'Kur',
    ]);
    app(CompanyContext::class)->clear();

    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('vacation.ends_on', today()->addDays(3)->toDateString())
        ->where('vacation.notes', 'Kur'));
});

test('büro und admin behalten das dashboard', function () {
    actingMember(CompanyRole::Office);

    $this->get('/dashboard')->assertInertia(fn ($page) => $page->component('dashboard'));
});

test('regiebericht übernimmt das projekt aus mein tag', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->get("/site-reports/create?project={$project->id}")
        ->assertInertia(fn ($page) => $page->where('projectId', $project->id));
});

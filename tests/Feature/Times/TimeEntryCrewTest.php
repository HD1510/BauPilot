<?php

use App\Enums\CompanyRole;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Vacation;
use App\Support\Tenancy\CompanyContext;

/**
 * Zeiterfassung für die ganze Partie: mehrere Mitarbeiter in einem Zug,
 * Übernahme aus der Einteilung, Bearbeiten und Wochensumme.
 */
afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('mehrere mitarbeiter in einem zug: je mitarbeiter ein eintrag', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    $employees = Employee::factory()->count(3)->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->post('/time-entries', [
        'project_id' => $project->id,
        'employee_ids' => $employees->pluck('id')->all(),
        'work_date' => '2026-11-18',
        'hours' => '8',
        'activity' => 'Estrich',
    ])->assertSessionHas('success', '8 Stunden für 3 Mitarbeiter erfasst.');

    $entries = TimeEntry::withoutGlobalScopes()->get();

    expect($entries)->toHaveCount(3)
        ->and($entries->pluck('employee_id')->sort()->values()->all())->toBe($employees->pluck('id')->sort()->values()->all())
        ->and($entries->every(fn (TimeEntry $entry) => $entry->activity === 'Estrich'))->toBeTrue();
});

test('ohne mitarbeiter wird nichts erfasst', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->post('/time-entries', [
        'project_id' => $project->id,
        'employee_ids' => [],
        'work_date' => '2026-11-18',
        'hours' => '8',
    ])->assertSessionHasErrors();

    expect(TimeEntry::withoutGlobalScopes()->count())->toBe(0);
});

test('übernahme aus der einteilung: je eingeteiltem ein eintrag, doppelt drücken schadet nicht', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    $worker = Employee::factory()->create(['company_id' => $company->id]);
    $second = Employee::factory()->create(['company_id' => $company->id]);
    $onLeave = Employee::factory()->create(['company_id' => $company->id]);
    Vacation::factory()->create([
        'company_id' => $company->id,
        'employee_id' => $onLeave->id,
        'starts_on' => '2026-11-16',
        'ends_on' => '2026-11-20',
    ]);

    $assignment = Assignment::factory()->create([
        'company_id' => $company->id,
        'work_date' => '2026-11-18',
        'project_id' => $project->id,
        'site' => null,
    ]);
    $assignment->employees()->sync([$worker->id, $second->id, $onLeave->id]);

    // Freitext-Baustelle ohne Projekt: dafür gibt es keine Zeiten.
    $loose = Assignment::factory()->create(['company_id' => $company->id, 'work_date' => '2026-11-18']);
    $loose->employees()->sync([$worker->id]);
    app(CompanyContext::class)->clear();

    $this->post('/time-entries/from-assignments', ['work_date' => '2026-11-18', 'hours' => '8.5'])
        ->assertSessionHas('success', '2 Einträge aus der Einteilung übernommen.');

    $entries = TimeEntry::withoutGlobalScopes()->get();

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('employee_id')->sort()->values()->all())->toBe([$worker->id, $second->id])
        ->and((float) $entries->first()->hours)->toBe(8.5)
        ->and($entries->first()->project_id)->toBe($project->id);

    // Zweiter Klick: alles schon erfasst, nichts doppelt.
    $this->post('/time-entries/from-assignments', ['work_date' => '2026-11-18', 'hours' => '8.5'])
        ->assertSessionHas('error');

    expect(TimeEntry::withoutGlobalScopes()->count())->toBe(2);
});

test('die seite zeigt die einteilung des tages mit überspring-gründen', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id, 'title' => 'BV Musterweg']);
    $done = Employee::factory()->create(['company_id' => $company->id, 'name' => 'Anna Fertig']);
    $open = Employee::factory()->create(['company_id' => $company->id, 'name' => 'Bernd Offen']);
    $assignment = Assignment::factory()->create([
        'company_id' => $company->id,
        'work_date' => '2026-11-18',
        'project_id' => $project->id,
    ]);
    $assignment->employees()->sync([$done->id, $open->id]);
    TimeEntry::factory()->create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'employee_id' => $done->id,
        'work_date' => '2026-11-18',
    ]);
    app(CompanyContext::class)->clear();

    $this->get('/time-entries?date=2026-11-18')
        ->assertInertia(fn ($page) => $page
            ->where('date', '2026-11-18')
            ->has('plan', 1)
            ->where('plan.0.project', 'BV Musterweg')
            ->where('plan.0.employees', fn ($employees) => collect($employees)->pluck('skip', 'name')->all() === [
                'Anna Fertig' => 'schon erfasst',
                'Bernd Offen' => null,
            ]));
});

test('wochenübersicht summiert je mitarbeiter und tag', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id, 'name' => 'Anna Fertig']);
    $base = ['company_id' => $company->id, 'project_id' => $project->id, 'employee_id' => $employee->id];
    TimeEntry::factory()->create($base + ['work_date' => '2026-11-16', 'hours' => '8']);      // Montag
    TimeEntry::factory()->create($base + ['work_date' => '2026-11-16', 'hours' => '1.5']);    // Montag, zweiter Eintrag
    TimeEntry::factory()->create($base + ['work_date' => '2026-11-22', 'hours' => '4']);      // Sonntag
    TimeEntry::factory()->create($base + ['work_date' => '2026-11-23', 'hours' => '9']);      // nächste Woche
    app(CompanyContext::class)->clear();

    $this->get('/time-entries?date=2026-11-18')
        ->assertInertia(fn ($page) => $page
            ->where('week.monday', '2026-11-16')
            ->has('week.rows', 1)
            ->where('week.rows.0.employee', 'Anna Fertig')
            ->where('week.rows.0.hours.0', 9.5)
            ->where('week.rows.0.hours.6', 4)
            ->where('week.rows.0.total', 13.5)
            ->where('week.total', 13.5));
});

test('eigene einträge lassen sich korrigieren, fremde nur vom büro', function () {
    [$user, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $own = TimeEntry::factory()->create([
        'company_id' => $company->id, 'project_id' => $project->id, 'employee_id' => $employee->id,
        'hours' => '8', 'created_by' => $user->id,
    ]);
    $other = User::factory()->create();
    $foreign = TimeEntry::factory()->create([
        'company_id' => $company->id, 'project_id' => $project->id, 'employee_id' => $employee->id,
        'hours' => '8', 'created_by' => $other->id,
    ]);
    app(CompanyContext::class)->clear();

    $this->patch("/time-entries/{$own->id}", ['hours' => '6.25', 'activity' => 'früher Schluss'])
        ->assertSessionHas('success');

    expect((float) $own->refresh()->hours)->toBe(6.25)
        ->and($own->activity)->toBe('früher Schluss');

    $this->patch("/time-entries/{$foreign->id}", ['hours' => '1'])->assertForbidden();

    expect((float) $foreign->refresh()->hours)->toBe(8.0);
});

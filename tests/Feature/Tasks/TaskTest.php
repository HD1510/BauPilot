<?php

use App\Enums\CompanyRole;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Task;
use App\Models\User;
use App\Support\Deadlines\DeadlineService;
use App\Support\Tenancy\CompanyContext;

afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('die baustelle darf aufgaben anlegen, erledigen und wieder öffnen', function () {
    [$user, $company] = actingMember(CompanyRole::Site);
    $project = Project::factory()->create(['company_id' => $company->id]);

    $this->post("/projects/{$project->id}/tasks", [
        'kind' => 'defect',
        'title' => 'Silikonfuge Bad ausbessern',
        'due_on' => now()->addDays(3)->toDateString(),
    ])->assertSessionHasNoErrors();

    $task = Task::withoutGlobalScopes()->firstOrFail();
    expect($task->company_id)->toBe($company->id)
        ->and($task->kind->value)->toBe('defect')
        ->and($task->created_by)->toBe($user->id);

    // Erledigen ist zustands-idempotent: zweimal abhaken ändert nichts.
    $this->post("/tasks/{$task->id}/complete")->assertSessionHasNoErrors();
    $doneAt = $task->refresh()->done_at;
    expect($doneAt)->not->toBeNull()
        ->and($task->done_by)->toBe($user->id);

    $this->travel(1)->minutes();
    $this->post("/tasks/{$task->id}/complete");
    expect($task->refresh()->done_at?->equalTo($doneAt))->toBeTrue();

    $this->post("/tasks/{$task->id}/reopen");
    expect($task->refresh()->done_at)->toBeNull();
});

test('aufgaben fremder firmen sind unerreichbar', function () {
    $foreignTask = Task::factory()->create();

    actingMember();

    $this->post("/tasks/{$foreignTask->id}/complete")->assertNotFound();
    $this->delete("/tasks/{$foreignTask->id}")->assertNotFound();
});

test('offene aufgaben mit fälligkeit erscheinen als frist — erledigte nicht', function () {
    [$user, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);

    $open = Task::factory()->create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'kind' => 'defect',
        'title' => 'Mangel offen',
        'due_on' => now()->addDays(5)->toDateString(),
        'assignee_user_id' => $user->id,
    ]);
    Task::factory()->create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'title' => 'Schon erledigt',
        'due_on' => now()->addDays(5)->toDateString(),
        'done_at' => now(),
    ]);
    Task::factory()->create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'title' => 'Ohne Fälligkeit',
    ]);

    // Auch für die Rolle Baustelle sichtbar (nicht-finanziell).
    $deadlines = app(DeadlineService::class)->upcoming(includeFinancials: false);

    expect($deadlines)->toHaveCount(1)
        ->and($deadlines->first()->title)->toBe('Mangel offen')
        ->and($deadlines->first()->kind->value)->toBe('defect')
        ->and($deadlines->first()->assigneeUserId)->toBe($user->id);

    // Erledigen lässt die Frist von selbst verschwinden.
    $open->markDone($user);
    expect(app(DeadlineService::class)->upcoming(includeFinancials: false))->toHaveCount(0);
});

test('notizen: jedes mitglied legt an, fremde notizen löscht nur admin/büro', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);

    $this->post("/projects/{$project->id}/notes", ['body' => 'Kies geliefert, Zufahrt eng.'])
        ->assertSessionHasNoErrors();

    $note = ProjectNote::withoutGlobalScopes()->firstOrFail();

    // Eigene Notiz: löschen erlaubt.
    $this->delete("/notes/{$note->id}")->assertSessionHasNoErrors();
    expect(ProjectNote::withoutGlobalScopes()->count())->toBe(0);

    // Fremde Notiz: für die Baustelle tabu, fürs Büro ok.
    $office = User::factory()->create();
    $company->users()->attach($office->id, ['role' => CompanyRole::Office->value]);

    app(CompanyContext::class)->clear();
    $foreign = ProjectNote::factory()->create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'created_by' => $office->id,
    ]);

    $this->delete("/notes/{$foreign->id}")->assertForbidden();

    $this->actingAs($office);
    app(CompanyContext::class)->clear();
    $this->delete("/notes/{$foreign->id}")->assertSessionHasNoErrors();
});

test('die aufgaben-seite filtert nach art, erledigt und zuständigkeit', function () {
    [$user, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);

    Task::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'title' => 'Meine Aufgabe', 'assignee_user_id' => $user->id]);
    Task::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'title' => 'Fremde Aufgabe']);
    Task::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'title' => 'Erledigt', 'done_at' => now()]);

    app(CompanyContext::class)->clear();

    $this->get('/tasks?mine=1')
        ->assertInertia(fn ($page) => $page
            ->component('tasks/index')
            ->has('tasks', 1)
            ->where('tasks.0.title', 'Meine Aufgabe'));

    app(CompanyContext::class)->clear();

    $this->get('/tasks?done=1')
        ->assertInertia(fn ($page) => $page->has('tasks', 3));
});

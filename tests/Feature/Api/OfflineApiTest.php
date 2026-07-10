<?php

use App\Enums\CompanyRole;
use App\Jobs\ResizeDocumentImage;
use App\Models\Company;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Task;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * JSON-Endpunkte der Baustellen-Funktionen (Architekturblatt Abschnitt 9):
 * explizite company_id, Idempotenz über client_uuid, zustands-idempotente
 * Erledigung — die Andockstelle für den Offline-Puffer in M9.
 */
afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('api-schreibzugriffe verlangen die ziel-firma explizit im request', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    // Ohne company_id: 422 — die Session zählt hier ausdrücklich nicht.
    $this->postJson('/api/tasks', [
        'project_id' => $project->id,
        'kind' => 'task',
        'title' => 'Ohne Firma',
    ])->assertStatus(422);

    // Fremde Firma: 403.
    $foreign = Company::factory()->create();
    $this->postJson('/api/tasks', [
        'company_id' => $foreign->id,
        'project_id' => $project->id,
        'kind' => 'task',
        'title' => 'Fremde Firma',
    ])->assertForbidden();

    expect(Task::withoutGlobalScopes()->count())->toBe(0);
});

test('die explizite company_id schlägt die session — zweit-tab kann nichts falsch verbuchen', function () {
    // Benutzer ist Mitglied in ZWEI Firmen; die Session steht auf Firma A,
    // der gepufferte Request zielt auf Firma B.
    [$user, $companyA] = actingMember(CompanyRole::Site);
    $companyB = Company::factory()->create();
    $companyB->users()->attach($user->id, ['role' => CompanyRole::Site->value]);

    app(CompanyContext::class)->set($companyB);
    $projectB = Project::factory()->create(['company_id' => $companyB->id]);
    app(CompanyContext::class)->clear();

    // Session auf Firma A stellen (normaler Web-Request).
    $this->get('/dashboard');
    app(CompanyContext::class)->clear();

    $this->postJson('/api/tasks', [
        'company_id' => $companyB->id,
        'project_id' => $projectB->id,
        'kind' => 'task',
        'title' => 'Aus dem Funkloch',
    ])->assertCreated();

    $task = Task::withoutGlobalScopes()->firstOrFail();
    expect($task->company_id)->toBe($companyB->id);
});

test('aufgabe anlegen ist idempotent über client_uuid', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $uuid = (string) Str::uuid();
    $payload = [
        'company_id' => $company->id,
        'project_id' => $project->id,
        'kind' => 'defect',
        'title' => 'Fliese gesprungen',
        'client_uuid' => $uuid,
    ];

    $first = $this->postJson('/api/tasks', $payload)->assertCreated();
    app(CompanyContext::class)->clear();
    $second = $this->postJson('/api/tasks', $payload)->assertOk();

    expect($second->json('id'))->toBe($first->json('id'))
        ->and($second->json('status'))->toBe('exists')
        ->and(Task::withoutGlobalScopes()->count())->toBe(1);
});

test('erledigung über die api ist zustands-idempotent', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $task = Task::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $first = $this->postJson("/api/tasks/{$task->id}/complete", ['company_id' => $company->id])
        ->assertOk();

    $this->travel(1)->minutes();
    app(CompanyContext::class)->clear();

    $second = $this->postJson("/api/tasks/{$task->id}/complete", ['company_id' => $company->id])
        ->assertOk();

    expect($second->json('done_at'))->toBe($first->json('done_at'));
});

test('aufgaben fremder firmen sind über die api unerreichbar', function () {
    [$user, $company] = actingMember(CompanyRole::Site);

    $foreignTask = Task::factory()->create(); // eigene fremde Firma

    app(CompanyContext::class)->clear();

    // Auch mit gültiger eigener company_id: fremde Aufgabe bleibt 404.
    $this->postJson("/api/tasks/{$foreignTask->id}/complete", ['company_id' => $company->id])
        ->assertNotFound();
});

test('notiz anlegen ist idempotent über client_uuid', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $payload = [
        'company_id' => $company->id,
        'project_id' => $project->id,
        'body' => 'Beton kommt Freitag 07:00.',
        'client_uuid' => (string) Str::uuid(),
    ];

    $this->postJson('/api/project-notes', $payload)->assertCreated();
    app(CompanyContext::class)->clear();
    $this->postJson('/api/project-notes', $payload)->assertOk();

    expect(ProjectNote::withoutGlobalScopes()->count())->toBe(1);
});

test('foto-upload: idempotent, kategorie foto, verkleinerung in der queue', function () {
    Storage::fake('documents');
    Queue::fake();

    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $uuid = (string) Str::uuid();

    $upload = fn () => $this->postJson('/api/documents', [
        'company_id' => $company->id,
        'project_id' => $project->id,
        'client_uuid' => $uuid,
        'file' => UploadedFile::fake()->image('baustelle.jpg', 800, 600),
    ]);

    $upload()->assertCreated();
    app(CompanyContext::class)->clear();
    $upload()->assertOk();

    $document = Document::withoutGlobalScopes()->firstOrFail();

    expect(Document::withoutGlobalScopes()->count())->toBe(1)
        ->and($document->category->value)->toBe('photo')
        ->and($document->company_id)->toBe($company->id);

    Queue::assertPushed(ResizeDocumentImage::class, fn (ResizeDocumentImage $job): bool => $job->companyId === $company->id && $job->documentId === $document->id);
});

test('ohne anmeldung ist die api zu', function () {
    $this->postJson('/api/tasks', ['company_id' => 1, 'title' => 'x'])->assertUnauthorized();
});

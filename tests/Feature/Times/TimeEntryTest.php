<?php

use App\Enums\CompanyRole;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Str;

afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('die baustelle erfasst stunden und löscht nur eigene einträge', function () {
    [$user, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->post('/time-entries', [
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'work_date' => now()->toDateString(),
        'hours' => '7.5',
        'activity' => 'Schalung Keller',
    ])->assertSessionHasNoErrors();

    $entry = TimeEntry::withoutGlobalScopes()->firstOrFail();
    expect((float) $entry->hours)->toBe(7.5)
        ->and($entry->created_by)->toBe($user->id);

    // Eigenen Eintrag löschen: erlaubt.
    app(CompanyContext::class)->clear();
    $this->delete("/time-entries/{$entry->id}")->assertSessionHasNoErrors();
    expect(TimeEntry::withoutGlobalScopes()->count())->toBe(0);

    // Fremden Eintrag löschen: für die Baustelle tabu.
    app(CompanyContext::class)->clear();
    $foreign = TimeEntry::factory()->create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'employee_id' => $employee->id,
    ]);
    $foreign->forceFill(['created_by' => User::factory()->create()->id])->save();

    app(CompanyContext::class)->clear();
    $this->delete("/time-entries/{$foreign->id}")->assertForbidden();
});

test('zeiten-api: client_uuid ist pflicht und macht das anlegen idempotent', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $payload = [
        'company_id' => $company->id,
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'work_date' => now()->toDateString(),
        'hours' => 8,
    ];

    // Ohne client_uuid: 422 — Zeiten sind die klassische Funkloch-Erfassung.
    $this->postJson('/api/time-entries', $payload)->assertStatus(422);

    app(CompanyContext::class)->clear();
    $uuid = (string) Str::uuid();
    $first = $this->postJson('/api/time-entries', [...$payload, 'client_uuid' => $uuid])->assertCreated();
    app(CompanyContext::class)->clear();
    $second = $this->postJson('/api/time-entries', [...$payload, 'client_uuid' => $uuid])->assertOk();

    expect($second->json('id'))->toBe($first->json('id'))
        ->and(TimeEntry::withoutGlobalScopes()->count())->toBe(1);
});

test('fremde projekte und mitarbeiter sind für zeiten unerreichbar', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $foreignEmployee = Employee::factory()->create(); // fremde Firma

    $this->postJson('/api/time-entries', [
        'company_id' => $company->id,
        'project_id' => $project->id,
        'employee_id' => $foreignEmployee->id,
        'work_date' => now()->toDateString(),
        'hours' => 8,
        'client_uuid' => (string) Str::uuid(),
    ])->assertNotFound();

    expect(TimeEntry::withoutGlobalScopes()->count())->toBe(0);
});

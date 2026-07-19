<?php

use App\Enums\CompanyRole;
use App\Models\Document;
use App\Models\Employee;
use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Personalakte: Adresse, Geburtsdatum, Ein-/Austritt, SV-Nummer, IBAN
 * und Datei-Anhänge (Arbeitsvertrag, Nachweise) am Mitarbeiter.
 */
beforeEach(function () {
    Storage::fake('documents');
});

afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('personalfelder lassen sich speichern', function () {
    [, $company] = actingMember(CompanyRole::Admin);
    $employee = Employee::factory()->create(['company_id' => $company->id]);

    $this->patch("/employees/{$employee->id}", [
        'name' => 'Max Polier',
        'address' => 'Hauptstraße 1, 7202 Bad Sauerbrunn',
        'birth_date' => '1990-01-01',
        'started_on' => '2020-03-01',
        'ended_on' => '2026-06-30',
        'social_security_number' => '1234 010190',
        'iban' => 'AT611904300234573201',
        'lock_version' => 0,
    ])->assertSessionHasNoErrors();

    $employee->refresh();

    expect($employee->address)->toBe('Hauptstraße 1, 7202 Bad Sauerbrunn')
        ->and($employee->birth_date?->toDateString())->toBe('1990-01-01')
        ->and($employee->started_on?->toDateString())->toBe('2020-03-01')
        ->and($employee->ended_on?->toDateString())->toBe('2026-06-30')
        ->and($employee->social_security_number)->toBe('1234 010190')
        ->and($employee->iban)->toBe('AT611904300234573201');
});

test('austritt vor eintritt wird abgelehnt', function () {
    [, $company] = actingMember(CompanyRole::Admin);
    $employee = Employee::factory()->create(['company_id' => $company->id]);

    $this->patch("/employees/{$employee->id}", [
        'name' => $employee->name,
        'started_on' => '2024-05-01',
        'ended_on' => '2024-04-30',
        'lock_version' => 0,
    ])->assertSessionHasErrors('ended_on');
});

test('arbeitsvertrag lässt sich an den mitarbeiter hängen und herunterladen', function () {
    [, $company] = actingMember(CompanyRole::Admin);
    $employee = Employee::factory()->create(['company_id' => $company->id]);

    $this->post('/documents', [
        'documentable_type' => 'employee',
        'documentable_id' => $employee->id,
        'category' => 'contract',
        'file' => UploadedFile::fake()->create('arbeitsvertrag.pdf', 80, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $document = Document::withoutGlobalScopes()->firstOrFail();

    expect($document->documentable_type)->toBe('employee')
        ->and($document->documentable_id)->toBe($employee->id)
        ->and($document->category->value)->toBe('contract');

    Storage::disk('documents')->assertExists($document->path);
    $this->get("/documents/{$document->id}/download")->assertOk();
});

test('rolle baustelle darf personalakten weder befüllen noch lesen', function () {
    [, $company] = actingMember(CompanyRole::Admin);
    app(CompanyContext::class)->set($company);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    $document = Document::factory()->create([
        'company_id' => $company->id,
        'documentable_type' => 'employee',
        'documentable_id' => $employee->id,
        'category' => 'contract',
    ]);

    // Rolle wechseln: gleicher Mandant, aber Rolle Baustelle.
    $site = User::factory()->create();
    $company->users()->attach($site->id, ['role' => CompanyRole::Site->value]);
    $this->actingAs($site);

    $this->post('/documents', [
        'documentable_type' => 'employee',
        'documentable_id' => $employee->id,
        'category' => 'contract',
        'file' => UploadedFile::fake()->create('vertrag.pdf', 10, 'application/pdf'),
    ])->assertForbidden();

    $this->get("/documents/{$document->id}/download")->assertForbidden();
});

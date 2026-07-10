<?php

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\IncomingInvoice;
use App\Models\OutgoingInvoice;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Support\Projects\ProjectFigures;
use App\Support\Tenancy\CompanyContext;

afterEach(function () {
    app(CompanyContext::class)->clear();
});

/**
 * Abnahmekriterium M8: „Projektzahlen stimmen gegen manuell gerechnete
 * Kontrollprojekte." — Das Kontrollprojekt hier ist von Hand gerechnet:
 *
 *   Erlöse:      10.000 + 5.000 − 500 (Gutschrift)      = 14.500,00
 *   Fremdkosten:  3.000 + 1.200                          =  4.200,00
 *   Lohnkosten:  10 h × 50 (MA-Satz) + 8 h × 42 (Firma)  =    836,00
 *   Deckungsbeitrag: 14.500 − 4.200 − 836                =  9.464,00
 *   Quote: 9.464 / 14.500                                =      65,3 %
 */
test('kontrollprojekt: deckungsbeitrag stimmt gegen die handrechnung', function () {
    $company = Company::factory()->create(['calc_hourly_rate' => '42.00']);
    app(CompanyContext::class)->set($company);

    $project = Project::factory()->create(['company_id' => $company->id]);

    OutgoingInvoice::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'net' => '10000.00']);
    OutgoingInvoice::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'net' => '5000.00']);
    // Gutschrift: negativ gespeichert (Konvention Abschnitt 5)
    OutgoingInvoice::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'doc_type' => 'credit_note', 'net' => '-500.00']);

    IncomingInvoice::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'net' => '3000.00']);
    IncomingInvoice::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'net' => '1200.00']);
    // Eingangsrechnung eines ANDEREN Projekts zählt nicht mit
    IncomingInvoice::factory()->create(['company_id' => $company->id, 'net' => '9999.00']);

    $polier = Employee::factory()->create(['company_id' => $company->id, 'calc_hourly_rate' => '50.00']);
    $hilfskraft = Employee::factory()->create(['company_id' => $company->id, 'calc_hourly_rate' => null]);

    TimeEntry::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'employee_id' => $polier->id, 'hours' => '10.00']);
    TimeEntry::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'employee_id' => $hilfskraft->id, 'hours' => '8.00']);

    $figures = app(ProjectFigures::class)->forProject($project->fresh());

    expect($figures['revenue_net'])->toBe(14500.0)
        ->and($figures['external_costs_net'])->toBe(4200.0)
        ->and($figures['hours'])->toBe(18.0)
        ->and($figures['labor_cost'])->toBe(836.0)
        ->and($figures['unrated_hours'])->toBe(0.0)
        ->and($figures['contribution'])->toBe(9464.0)
        ->and($figures['margin_percent'])->toBe(65.3);
});

test('ohne stundensatz werden stunden ausgewiesen statt mit null bewertet', function () {
    $company = Company::factory()->create(['calc_hourly_rate' => null]);
    app(CompanyContext::class)->set($company);

    $project = Project::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id, 'calc_hourly_rate' => null]);
    TimeEntry::factory()->create(['company_id' => $company->id, 'project_id' => $project->id, 'employee_id' => $employee->id, 'hours' => '6.50']);

    $figures = app(ProjectFigures::class)->forProject($project->fresh());

    expect($figures['hours'])->toBe(6.5)
        ->and($figures['labor_cost'])->toBe(0.0)
        ->and($figures['unrated_hours'])->toBe(6.5)
        ->and($figures['margin_percent'])->toBeNull();
});

test('die rolle baustelle bekommt keine projektzahlen', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->get("/projects/{$project->id}")
        ->assertInertia(fn ($page) => $page
            ->component('projects/show')
            ->where('figures', null));
});

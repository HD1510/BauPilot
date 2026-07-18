<?php

use App\Models\IncomingInvoice;
use App\Models\Project;
use App\Models\ProjectAppointment;
use App\Models\Supplier;
use App\Support\Tenancy\CompanyContext;

/**
 * Dashboard: Termine (Projekttermine) stehen getrennt von den Fristen
 * (Zahlungsziele, Aufgaben, Fahrzeuge, ...).
 */
afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('dashboard trennt termine von fristen', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    ProjectAppointment::factory()->create([
        'company_id' => $company->id,
        'project_id' => $project->id,
        'on_date' => now()->addDays(3)->toDateString(),
        'label' => 'Kranstellung',
    ]);
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);
    IncomingInvoice::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'payment_due_on' => now()->addDays(5)->toDateString(),
    ]);
    app(CompanyContext::class)->clear();

    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('appointments.0.kind', 'appointment')
        ->where('appointments.0.title', 'Kranstellung')
        ->count('appointments', 1)
        // Die Fristenliste enthält keinen Termin mehr.
        ->where('deadlines', fn ($deadlines) => collect($deadlines)->every(
            fn ($deadline) => $deadline['kind'] !== 'appointment',
        )));
});

<?php

use App\Models\IncomingInvoice;
use App\Models\OutgoingInvoice;
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

test('überfällige rechnung steht nur als mahn-hinweis, nicht noch einmal in den fristen', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $overdue = OutgoingInvoice::factory()->create([
        'company_id' => $company->id,
        'due_on' => now()->subDays(10)->toDateString(),
    ]);
    $upcoming = OutgoingInvoice::factory()->create([
        'company_id' => $company->id,
        'due_on' => now()->addDays(10)->toDateString(),
    ]);
    app(CompanyContext::class)->clear();

    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->count('overdueInvoices', 1)
        ->where('overdueInvoices.0.id', $overdue->id)
        // Die noch nicht fällige Rechnung bleibt in den Fristen, die
        // überfällige taucht dort nicht ein zweites Mal auf.
        ->where('deadlines', fn ($deadlines) => collect($deadlines)->pluck('url')->all() === ["/outgoing-invoices/{$upcoming->id}"])
        // Die Kachel zählt weiterhin alles.
        ->where('deadlinesTotal', 2)
        ->where('overdueDeadlines', 1));
});

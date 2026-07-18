<?php

use App\Enums\CompanyRole;
use App\Models\Customer;
use App\Models\IncomingInvoice;
use App\Models\OutgoingInvoice;
use App\Models\Project;
use App\Support\Tenancy\CompanyContext;

/**
 * Rechnungen direkt am Projekt zuordnen bzw. lösen und von dort
 * erfassen (Projekt vorausgewählt).
 */
afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('ein- und ausgangsrechnung dem projekt zuordnen und wieder lösen', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    $incoming = IncomingInvoice::factory()->create(['company_id' => $company->id, 'project_id' => null]);
    $outgoing = OutgoingInvoice::factory()->create(['company_id' => $company->id, 'project_id' => null]);
    app(CompanyContext::class)->clear();

    $this->post("/projects/{$project->id}/invoices", [
        'type' => 'incoming',
        'invoice_id' => $incoming->id,
    ])->assertSessionHasNoErrors();

    app(CompanyContext::class)->clear();
    $this->post("/projects/{$project->id}/invoices", [
        'type' => 'outgoing',
        'invoice_id' => $outgoing->id,
    ])->assertSessionHasNoErrors();

    expect($incoming->refresh()->project_id)->toBe($project->id)
        ->and($outgoing->refresh()->project_id)->toBe($project->id);

    // Und wieder lösen.
    app(CompanyContext::class)->clear();
    $this->delete("/projects/{$project->id}/invoices", [
        'type' => 'incoming',
        'invoice_id' => $incoming->id,
    ])->assertSessionHasNoErrors();

    expect($incoming->refresh()->project_id)->toBeNull()
        ->and($outgoing->refresh()->project_id)->toBe($project->id);
});

test('projektseite listet unzugeordnete rechnungen als vorschlag', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    IncomingInvoice::factory()->create(['company_id' => $company->id, 'project_id' => null]);
    OutgoingInvoice::factory()->create(['company_id' => $company->id, 'project_id' => null]);
    app(CompanyContext::class)->clear();

    $this->get("/projects/{$project->id}")
        ->assertInertia(fn ($page) => $page
            ->count('assignableIncoming', 1)
            ->count('assignableOutgoing', 1)
            ->where('canManageInvoices', true));
});

test('rolle site darf keine rechnungen zuordnen', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    $incoming = IncomingInvoice::factory()->create(['company_id' => $company->id, 'project_id' => null]);
    app(CompanyContext::class)->clear();

    $this->post("/projects/{$project->id}/invoices", [
        'type' => 'incoming',
        'invoice_id' => $incoming->id,
    ])->assertForbidden();

    expect($incoming->refresh()->project_id)->toBeNull();
});

test('erfassungsseiten übernehmen das projekt aus der projektseite', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    Customer::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->get("/incoming-invoices/create?project={$project->id}")
        ->assertInertia(fn ($page) => $page->where('preselectedProjectId', $project->id));

    app(CompanyContext::class)->clear();
    $this->get("/outgoing-invoices/create?project={$project->id}")
        ->assertInertia(fn ($page) => $page->where('preselectedProjectId', $project->id));
});

test('umhängen fremd zugeordneter rechnungen und gutschriften wird abgelehnt', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $projectA = Project::factory()->create(['company_id' => $company->id]);
    $projectB = Project::factory()->create(['company_id' => $company->id]);
    $assigned = IncomingInvoice::factory()->create(['company_id' => $company->id, 'project_id' => $projectB->id]);
    $original = OutgoingInvoice::factory()->create(['company_id' => $company->id, 'project_id' => null]);
    $credit = OutgoingInvoice::factory()->create([
        'company_id' => $company->id,
        'project_id' => null,
        'original_invoice_id' => $original->id,
    ]);
    app(CompanyContext::class)->clear();

    // Bereits Projekt B zugeordnet: erst dort lösen.
    $this->post("/projects/{$projectA->id}/invoices", [
        'type' => 'incoming',
        'invoice_id' => $assigned->id,
    ])->assertSessionHasErrors('invoice_id');

    expect($assigned->refresh()->project_id)->toBe($projectB->id);

    // Gutschriften folgen ihrer Originalrechnung.
    app(CompanyContext::class)->clear();
    $this->post("/projects/{$projectA->id}/invoices", [
        'type' => 'outgoing',
        'invoice_id' => $credit->id,
    ])->assertSessionHasErrors('invoice_id');

    expect($credit->refresh()->project_id)->toBeNull();
});

test('archivierter kunde lässt die projektseite nicht umfallen', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Alt Kunde GmbH']);
    OutgoingInvoice::factory()->create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'project_id' => null,
    ]);
    $customer->delete(); // Soft delete — Kunde archiviert.
    app(CompanyContext::class)->clear();

    $this->get("/projects/{$project->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->count('assignableOutgoing', 1));
});

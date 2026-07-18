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

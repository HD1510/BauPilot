<?php

use App\Enums\CompanyRole;
use App\Models\Document;
use App\Models\OutgoingInvoice;
use App\Models\Project;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('documents');
});

afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('beleg-pdf lässt sich an eine rechnung hängen und wieder herunterladen', function () {
    [, $company] = actingMember();
    $invoice = OutgoingInvoice::factory()->create(['company_id' => $company->id]);

    $this->post('/documents', [
        'documentable_type' => 'outgoing_invoice',
        'documentable_id' => $invoice->id,
        'category' => 'invoice',
        'file' => UploadedFile::fake()->create('rechnung-250184.pdf', 120, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $document = Document::withoutGlobalScopes()->firstOrFail();

    expect($document->documentable_type)->toBe('outgoing_invoice')
        ->and($document->company_id)->toBe($company->id)
        ->and($document->original_name)->toBe('rechnung-250184.pdf');

    Storage::disk('documents')->assertExists($document->path);

    $this->get("/documents/{$document->id}/download")->assertOk();

    // Löschen entfernt Datei und Datensatz
    $this->delete("/documents/{$document->id}");
    Storage::disk('documents')->assertMissing($document->path);
    expect(Document::withoutGlobalScopes()->count())->toBe(0);
});

test('idempotenter upload: dieselbe client_uuid legt keine zweite datei an', function () {
    [, $company] = actingMember();
    $project = Project::factory()->create(['company_id' => $company->id]);
    $uuid = (string) Str::uuid();

    foreach (range(1, 2) as $attempt) {
        $this->post('/documents', [
            'documentable_type' => 'project',
            'documentable_id' => $project->id,
            'category' => 'plan',
            'client_uuid' => $uuid,
            'file' => UploadedFile::fake()->create("plan-{$attempt}.pdf", 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();
    }

    expect(Document::withoutGlobalScopes()->count())->toBe(1);
});

test('rolle baustelle sieht projektdateien, aber keine rechnungsanhänge', function () {
    [, $company] = actingMember(CompanyRole::Site);

    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    $invoice = OutgoingInvoice::factory()->create(['company_id' => $company->id]);

    $projectDoc = Document::factory()->create([
        'company_id' => $company->id,
        'documentable_type' => 'project',
        'documentable_id' => $project->id,
    ]);
    $invoiceDoc = Document::factory()->create([
        'company_id' => $company->id,
        'documentable_type' => 'outgoing_invoice',
        'documentable_id' => $invoice->id,
    ]);

    Storage::disk('documents')->put($projectDoc->path, 'plan');
    Storage::disk('documents')->put($invoiceDoc->path, 'geheim');

    $this->get("/documents/{$projectDoc->id}/download")->assertOk();
    $this->get("/documents/{$invoiceDoc->id}/download")->assertForbidden();
});

test('dokumente fremder firmen sind unerreichbar', function () {
    $foreignDoc = Document::factory()->create(); // eigene fremde Firma

    actingMember();

    $this->get("/documents/{$foreignDoc->id}/download")->assertNotFound();
});

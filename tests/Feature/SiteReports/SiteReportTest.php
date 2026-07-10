<?php

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\SiteReport;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('documents');
});

afterEach(function () {
    app(CompanyContext::class)->clear();
});

/**
 * Eine winzige, aber echte PNG-Datei als Data-URL (1×1 wäre unter der
 * Mindestgröße — deshalb ein generiertes 60×30-PNG).
 */
function signatureDataUrl(): string
{
    $image = imagecreatetruecolor(60, 30);
    imageline($image, 5, 25, 55, 5, imagecolorallocate($image, 15, 23, 42));
    ob_start();
    imagepng($image);
    imagedestroy($image);

    return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
}

test('nummernkreis: fortlaufend je firma, firmen zählen unabhängig', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    foreach (range(1, 3) as $i) {
        $this->postJson('/api/site-reports', [
            'company_id' => $company->id,
            'project_id' => $project->id,
            'report_date' => now()->toDateString(),
            'body_text' => "Bericht {$i}",
            'client_uuid' => (string) Str::uuid(),
        ])->assertCreated();
        app(CompanyContext::class)->clear();
    }

    expect(SiteReport::withoutGlobalScopes()->where('company_id', $company->id)->pluck('number')->sort()->values()->all())
        ->toBe([1, 2, 3]);

    // Zweite Firma beginnt bei 1 — Nummernkreis je Firma.
    $other = Company::factory()->create();
    app(CompanyContext::class)->set($other);
    $otherProject = Project::factory()->create(['company_id' => $other->id]);
    $report = SiteReport::factory()->create(['company_id' => $other->id, 'project_id' => $otherProject->id]);

    expect($report->number)->toBe(1);
});

test('funkloch-request: EIN api-aufruf legt bericht samt stunden und unterschrift an — idempotent', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $payload = [
        'company_id' => $company->id,
        'project_id' => $project->id,
        'report_date' => '2026-07-10',
        'body_text' => 'Kernbohrung und Verrohrung DN100',
        'material_text' => '2 Sack Schnellzement',
        'entries' => [['employee_id' => $employee->id, 'hours' => 6.5]],
        'signature' => signatureDataUrl(),
        'client_uuid' => (string) Str::uuid(),
    ];

    $first = $this->postJson('/api/site-reports', $payload)->assertCreated();
    expect($first->json('report_status'))->toBe('signed');

    app(CompanyContext::class)->clear();

    // Wiederholung aus dem Offline-Puffer: bereits erledigt.
    $second = $this->postJson('/api/site-reports', $payload)->assertOk();
    expect($second->json('status'))->toBe('exists')
        ->and(SiteReport::withoutGlobalScopes()->count())->toBe(1);

    $report = SiteReport::withoutGlobalScopes()->firstOrFail();
    expect($report->isSigned())->toBeTrue()
        ->and($report->signed_at)->not->toBeNull()
        ->and($report->signature_path)->not->toBeNull();

    Storage::disk('documents')->assertExists($report->signature_path);

    app(CompanyContext::class)->set($company);
    expect($report->entries()->withoutGlobalScopes()->count())->toBe(1);
});

test('sperre: unterschriebene berichte sind unveränderlich', function () {
    [$user, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $report = SiteReport::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    // Unterschreiben über den Web-Weg.
    $this->post("/site-reports/{$report->id}/sign", ['signature' => signatureDataUrl()])
        ->assertSessionHasNoErrors();

    $report->refresh();
    $firstSignedAt = $report->signed_at;
    expect($report->isSigned())->toBeTrue();

    // Erneut unterschreiben: zustands-idempotent, nichts ändert sich.
    $this->travel(1)->minutes();
    app(CompanyContext::class)->clear();
    $this->post("/site-reports/{$report->id}/sign", ['signature' => signatureDataUrl()]);
    expect($report->refresh()->signed_at?->equalTo($firstSignedAt))->toBeTrue();

    // Löschen: gesperrt.
    app(CompanyContext::class)->clear();
    $this->delete("/site-reports/{$report->id}")->assertForbidden();
    expect(SiteReport::withoutGlobalScopes()->count())->toBe(1);
});

test('entwürfe darf der verfasser löschen', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->postJson('/api/site-reports', [
        'company_id' => $company->id,
        'project_id' => $project->id,
        'report_date' => now()->toDateString(),
        'client_uuid' => (string) Str::uuid(),
    ])->assertCreated();

    $draft = SiteReport::withoutGlobalScopes()->firstOrFail();

    app(CompanyContext::class)->clear();
    $this->delete("/site-reports/{$draft->id}")->assertSessionHasNoErrors();
    expect(SiteReport::withoutGlobalScopes()->count())->toBe(0);
});

test('kaputte unterschrift wird abgelehnt', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->postJson('/api/site-reports', [
        'company_id' => $company->id,
        'project_id' => $project->id,
        'report_date' => now()->toDateString(),
        'signature' => 'data:image/png;base64,nichtwirklich',
        'client_uuid' => (string) Str::uuid(),
    ])->assertStatus(422);

    // Nichts Halbfertiges übrig — der Bericht entsteht nur ganz oder gar nicht.
    expect(SiteReport::withoutGlobalScopes()->count())->toBe(0);
});

test('regieberichte fremder firmen sind unerreichbar', function () {
    $foreign = SiteReport::factory()->create();

    actingMember();

    $this->get("/site-reports/{$foreign->id}")->assertNotFound();
    $this->post("/site-reports/{$foreign->id}/sign", ['signature' => signatureDataUrl()])->assertNotFound();
});

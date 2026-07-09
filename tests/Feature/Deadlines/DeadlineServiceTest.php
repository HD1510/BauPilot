<?php

use App\Enums\RetentionKind;
use App\Models\Company;
use App\Models\CostType;
use App\Models\IncomingInvoice;
use App\Models\Offer;
use App\Models\OutgoingInvoice;
use App\Models\Project;
use App\Models\ProjectAppointment;
use App\Models\Supplier;
use App\Models\Vehicle;
use App\Support\Deadlines\DeadlineKind;
use App\Support\Deadlines\DeadlineService;
use App\Support\Invoicing\InvoiceBookkeeper;
use App\Support\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->company = Company::factory()->create();
    app(CompanyContext::class)->set($this->company);
});

afterEach(function () {
    app(CompanyContext::class)->clear();
});

function deadlineKinds(bool $financials = true): array
{
    return app(DeadlineService::class)
        ->upcoming(includeFinancials: $financials)
        ->map(fn ($deadline) => $deadline->kind)
        ->all();
}

test('zahlungsziel: offene rechnung erscheint, bezahlte nicht', function () {
    $due = OutgoingInvoice::factory()->create([
        'company_id' => $this->company->id,
        'due_on' => now()->addDays(10)->toDateString(),
    ]);
    $paid = OutgoingInvoice::factory()->create([
        'company_id' => $this->company->id,
        'due_on' => now()->addDays(10)->toDateString(),
    ]);
    app(InvoiceBookkeeper::class)->bookPayment($paid, CarbonImmutable::now(), $paid->gross);

    $deadlines = app(DeadlineService::class)->upcoming();

    expect($deadlines->filter(fn ($deadline) => $deadline->kind === DeadlineKind::PaymentDue))->toHaveCount(1)
        ->and($deadlines->first()->title)->toBe("Rechnung {$due->number}");
});

test('eine rechnung, bei der nur noch der rücklass aussteht, mahnt nicht', function () {
    $invoice = OutgoingInvoice::factory()->create([
        'company_id' => $this->company->id,
        'net' => '10000.00', 'vat_rate' => '20.00', 'vat' => '2000.00', 'gross' => '12000.00',
        'due_on' => now()->subDays(5)->toDateString(), // Zahlungsziel vorbei
    ]);
    $bookkeeper = app(InvoiceBookkeeper::class);
    $bookkeeper->addRetention($invoice, RetentionKind::Warranty, '600.00', CarbonImmutable::now()->addDays(30));
    $bookkeeper->bookPayment($invoice->fresh(), CarbonImmutable::now(), '11400.00');

    $kinds = collect(deadlineKinds());

    // Kein Zahlungsziel mehr — aber der Rücklass selbst steht als Frist drin
    expect($kinds->contains(DeadlineKind::PaymentDue))->toBeFalse()
        ->and($kinds->contains(DeadlineKind::Retention))->toBeTrue();
});

test('skonto: erscheint solange offen und frist nicht verstrichen', function () {
    $supplier = Supplier::factory()->create(['company_id' => $this->company->id]);
    $costType = CostType::factory()->create(['company_id' => $this->company->id]);

    IncomingInvoice::factory()->create([
        'company_id' => $this->company->id,
        'supplier_id' => $supplier->id,
        'cost_type_id' => $costType->id,
        'skonto_amount' => '300.00',
        'skonto_until' => now()->addDays(5)->toDateString(),
    ]);
    IncomingInvoice::factory()->create([
        'company_id' => $this->company->id,
        'supplier_id' => $supplier->id,
        'cost_type_id' => $costType->id,
        'skonto_amount' => '100.00',
        'skonto_until' => now()->subDay()->toDateString(), // verstrichen — sinnlos
    ]);

    $skonto = app(DeadlineService::class)->upcoming()
        ->filter(fn ($deadline) => $deadline->kind === DeadlineKind::Skonto);

    expect($skonto)->toHaveCount(1)
        ->and($skonto->first()->dueOn->toDateString())->toBe(now()->addDays(5)->toDateString());
});

test('rücklass: offen und im horizont erscheint, eingegangen oder fern nicht', function () {
    $invoice = OutgoingInvoice::factory()->create(['company_id' => $this->company->id]);
    $bookkeeper = app(InvoiceBookkeeper::class);

    // Im Horizont (30 Tage)
    $near = $bookkeeper->addRetention($invoice, RetentionKind::Warranty, '500.00', CarbonImmutable::now()->addDays(30));
    // Weit weg (3 Jahre) — außerhalb des Horizonts
    $bookkeeper->addRetention($invoice->fresh(), RetentionKind::Coverage, '400.00', CarbonImmutable::now()->addYears(3));

    $retentions = app(DeadlineService::class)->upcoming()
        ->filter(fn ($deadline) => $deadline->kind === DeadlineKind::Retention);

    expect($retentions)->toHaveCount(1);

    // Eingegangen → verschwindet
    $bookkeeper->releaseRetention($near->fresh(), CarbonImmutable::now());

    expect(app(DeadlineService::class)->upcoming()
        ->filter(fn ($deadline) => $deadline->kind === DeadlineKind::Retention))->toHaveCount(0);
});

test('pickerl-vorwarnung: sechs wochen ja, sechs monate nein', function () {
    Vehicle::factory()->create([
        'company_id' => $this->company->id,
        'plate' => 'HL 1 A',
        'inspection_due_on' => now()->addWeeks(6)->toDateString(),
    ]);
    Vehicle::factory()->create([
        'company_id' => $this->company->id,
        'plate' => 'HL 2 B',
        'inspection_due_on' => now()->addMonths(6)->toDateString(),
    ]);

    $vehicles = app(DeadlineService::class)->upcoming()
        ->filter(fn ($deadline) => $deadline->kind === DeadlineKind::Vehicle);

    expect($vehicles)->toHaveCount(1)
        ->and($vehicles->first()->title)->toBe('Pickerl HL 1 A');
});

test('gewährleistung: projektende im horizont erscheint', function () {
    Project::factory()->create([
        'company_id' => $this->company->id,
        'title' => 'BV Altbau',
        'warranty_until' => now()->addDays(30)->toDateString(),
    ]);

    $warranty = app(DeadlineService::class)->upcoming()
        ->filter(fn ($deadline) => $deadline->kind === DeadlineKind::Warranty);

    expect($warranty)->toHaveCount(1)
        ->and($warranty->first()->title)->toContain('BV Altbau');
});

test('wiedervorlage: offene angebote ja, angenommene nein', function () {
    Offer::factory()->create([
        'company_id' => $this->company->id,
        'status' => 'offered',
        'follow_up_on' => now()->addDays(3)->toDateString(),
    ]);
    Offer::factory()->create([
        'company_id' => $this->company->id,
        'status' => 'accepted',
        'follow_up_on' => now()->addDays(3)->toDateString(),
    ]);

    expect(app(DeadlineService::class)->upcoming()
        ->filter(fn ($deadline) => $deadline->kind === DeadlineKind::FollowUp))->toHaveCount(1);
});

test('projekttermine erscheinen, vergangene nicht', function () {
    $project = Project::factory()->create(['company_id' => $this->company->id]);
    ProjectAppointment::factory()->create([
        'project_id' => $project->id,
        'company_id' => $this->company->id,
        'on_date' => now()->addDays(7)->toDateString(),
        'label' => 'Abnahme',
    ]);
    ProjectAppointment::factory()->create([
        'project_id' => $project->id,
        'company_id' => $this->company->id,
        'on_date' => now()->subDays(7)->toDateString(),
        'label' => 'Baubeginn',
    ]);

    $appointments = app(DeadlineService::class)->upcoming()
        ->filter(fn ($deadline) => $deadline->kind === DeadlineKind::Appointment);

    expect($appointments)->toHaveCount(1)
        ->and($appointments->first()->title)->toBe('Abnahme');
});

test('rollenfilter: ohne finanz-sichtbarkeit nur termine, fahrzeuge, gewährleistung', function () {
    // Eine Quelle je Kategorie
    OutgoingInvoice::factory()->create(['company_id' => $this->company->id, 'due_on' => now()->addDays(5)->toDateString()]);
    Vehicle::factory()->create(['company_id' => $this->company->id, 'inspection_due_on' => now()->addWeeks(2)->toDateString()]);
    $project = Project::factory()->create(['company_id' => $this->company->id, 'warranty_until' => now()->addDays(10)->toDateString()]);
    ProjectAppointment::factory()->create(['project_id' => $project->id, 'company_id' => $this->company->id, 'on_date' => now()->addDays(2)->toDateString()]);

    $kinds = collect(deadlineKinds(financials: false))->unique()->values();

    expect($kinds->contains(DeadlineKind::PaymentDue))->toBeFalse()
        ->and($kinds->contains(DeadlineKind::Vehicle))->toBeTrue()
        ->and($kinds->contains(DeadlineKind::Warranty))->toBeTrue()
        ->and($kinds->contains(DeadlineKind::Appointment))->toBeTrue();
});

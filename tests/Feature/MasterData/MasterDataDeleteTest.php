<?php

use App\Enums\CompanyRole;
use App\Models\CostType;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Employee;
use App\Models\IncomingInvoice;
use App\Models\Material;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\TimeEntry;
use App\Models\Vehicle;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Facades\Storage;

/**
 * Stammdaten sind löschbar — aber nur, wenn nichts daran hängt.
 * Verwendete Datensätze bleiben mit klarer Meldung geschützt
 * (Archivieren ist dann der Weg).
 */
beforeEach(function () {
    Storage::fake('documents');
});

afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('unbenutzte stammdaten lassen sich löschen', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);
    $vehicle = Vehicle::factory()->create(['company_id' => $company->id]);
    $material = Material::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);
    $costType = CostType::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->delete("/materials/{$material->id}")->assertRedirect('/materials');
    $this->delete("/customers/{$customer->id}")->assertRedirect('/customers');
    $this->delete("/vehicles/{$vehicle->id}")->assertRedirect('/vehicles');
    $this->delete("/cost-types/{$costType->id}")->assertRedirect();
    $this->delete("/suppliers/{$supplier->id}")->assertRedirect('/suppliers');

    expect(Customer::withTrashed()->count())->toBe(0)
        ->and(Supplier::withoutGlobalScopes()->count())->toBe(0)
        ->and(Vehicle::withoutGlobalScopes()->count())->toBe(0)
        ->and(Material::withoutGlobalScopes()->count())->toBe(0)
        ->and(CostType::withoutGlobalScopes()->count())->toBe(0);
});

test('auch archivierte kunden lassen sich endgültig löschen', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $customer->delete(); // archiviert (SoftDelete)
    app(CompanyContext::class)->clear();

    $this->delete("/customers/{$customer->id}")->assertRedirect('/customers');

    expect(Customer::withTrashed()->count())->toBe(0);
});

test('verwendete stammdaten sind geschützt und bleiben erhalten', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    Project::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);
    $costType = CostType::factory()->create(['company_id' => $company->id]);
    IncomingInvoice::factory()->create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'cost_type_id' => $costType->id,
    ]);
    app(CompanyContext::class)->clear();

    $this->delete("/customers/{$customer->id}")->assertSessionHas('error');
    $this->delete("/suppliers/{$supplier->id}")->assertSessionHas('error');
    $this->delete("/cost-types/{$costType->id}")->assertSessionHas('error');

    expect(Customer::query()->count())->toBe(1)
        ->and(Supplier::withoutGlobalScopes()->count())->toBe(1)
        ->and(CostType::withoutGlobalScopes()->count())->toBe(1);
});

test('mitarbeiter mit zeiten ist geschützt, ohne wird samt personalakte gelöscht', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $withTimes = Employee::factory()->create(['company_id' => $company->id]);
    TimeEntry::factory()->create(['company_id' => $company->id, 'employee_id' => $withTimes->id]);

    $plain = Employee::factory()->create(['company_id' => $company->id]);
    $document = Document::factory()->create([
        'company_id' => $company->id,
        'documentable_type' => 'employee',
        'documentable_id' => $plain->id,
        'category' => 'contract',
    ]);
    Storage::disk('documents')->put($document->path, 'vertrag');
    app(CompanyContext::class)->clear();

    $this->delete("/employees/{$withTimes->id}")->assertSessionHas('error');
    $this->delete("/employees/{$plain->id}")->assertRedirect('/employees');

    Storage::disk('documents')->assertMissing($document->path);

    expect(Employee::withoutGlobalScopes()->count())->toBe(1)
        ->and(Document::withoutGlobalScopes()->count())->toBe(0);
});

test('rolle baustelle darf keine stammdaten löschen', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->delete("/suppliers/{$supplier->id}")->assertForbidden();

    expect(Supplier::withoutGlobalScopes()->count())->toBe(1);
});

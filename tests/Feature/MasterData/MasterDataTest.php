<?php

use App\Models\CostType;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Employee;
use App\Models\Material;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDate;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Model;

afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('jedes stammdaten-modell ist auf die aktive firma begrenzt', function (string $modelClass) {
    /** @var class-string<Model> $modelClass */
    $record = $modelClass::factory()->create(); // legt eine eigene Firma an

    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);

    expect($modelClass::query()->count())->toBe(0)
        ->and($modelClass::query()->find($record->id))->toBeNull();
})->with([
    [Customer::class],
    [CustomerContact::class],
    [Supplier::class],
    [CostType::class],
    [Employee::class],
    [Vehicle::class],
    [VehicleDate::class],
    [Material::class],
]);

test('kostenarten: anlegen, umbenennen, eindeutigkeit je firma, archivieren', function () {
    actingMember();

    $this->post('/cost-types', ['name' => 'Material', 'sort_order' => 1])->assertSessionHasNoErrors();
    $this->post('/cost-types', ['name' => 'Material', 'sort_order' => 2])->assertSessionHasErrors('name');

    $costType = CostType::withoutGlobalScopes()->where('name', 'Material')->firstOrFail();

    $this->patch("/cost-types/{$costType->id}", [
        'name' => 'Baumaterial',
        'sort_order' => 1,
        'lock_version' => 0,
    ])->assertSessionHasNoErrors();

    expect($costType->refresh()->name)->toBe('Baumaterial');

    $this->patch("/cost-types/{$costType->id}/archive");
    expect($costType->refresh()->active)->toBeFalse();
});

test('mitarbeiter: anlegen und benutzerkonto nur aus der eigenen firma', function () {
    [$user] = actingMember();

    // Benutzer der eigenen Firma darf verknüpft werden
    $this->post('/employees', [
        'name' => 'Hans Maurer',
        'user_id' => $user->id,
    ])->assertSessionHasNoErrors();

    // Ein firmenfremder Benutzer wird abgelehnt
    $stranger = User::factory()->create();

    $this->post('/employees', [
        'name' => 'Fremder Monteur',
        'user_id' => $stranger->id,
    ])->assertSessionHasErrors('user_id');

    expect(Employee::withoutGlobalScopes()->count())->toBe(1);
});

test('fahrzeuge: anlegen, kennzeichen je firma eindeutig, zusatztermine', function () {
    actingMember();

    $this->post('/vehicles', ['plate' => 'W 123 AB'])->assertSessionHasNoErrors();
    $this->post('/vehicles', ['plate' => 'W 123 AB'])->assertSessionHasErrors('plate');

    $vehicle = Vehicle::withoutGlobalScopes()->where('plate', 'W 123 AB')->firstOrFail();

    $this->post("/vehicles/{$vehicle->id}/dates", [
        'label' => 'Service',
        'due_on' => '2026-10-01',
    ])->assertSessionHasNoErrors();

    $date = VehicleDate::withoutGlobalScopes()->firstOrFail();
    expect($date->company_id)->toBe($vehicle->company_id);

    $this->delete("/vehicles/{$vehicle->id}/dates/{$date->id}");
    expect(VehicleDate::withoutGlobalScopes()->count())->toBe(0);
});

test('material: artikel gehört zu einem lieferanten der eigenen firma', function () {
    [, $company] = actingMember();
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);

    $this->post('/materials', [
        'supplier_id' => $supplier->id,
        'name' => 'Ziegel 25er',
        'price_net' => '1.85',
        'package_unit' => 'Stk',
    ])->assertSessionHasNoErrors();

    $foreignSupplier = Supplier::factory()->create(); // fremde Firma

    $this->post('/materials', [
        'supplier_id' => $foreignSupplier->id,
        'name' => 'Fremder Artikel',
    ])->assertSessionHasErrors('supplier_id');

    expect(Material::withoutGlobalScopes()->count())->toBe(1);
});

test('suche greift in allen stammdaten-listen', function () {
    [, $company] = actingMember();

    Supplier::factory()->create(['company_id' => $company->id, 'name' => 'Baustoffe Nord']);
    Supplier::factory()->create(['company_id' => $company->id, 'name' => 'Eisen Süd']);
    Employee::factory()->create(['company_id' => $company->id, 'name' => 'Hans Maurer']);
    Vehicle::factory()->create(['company_id' => $company->id, 'plate' => 'W 555 XY', 'brand' => 'MAN']);

    $this->get('/suppliers?q=Nord')
        ->assertInertia(fn ($page) => $page->count('suppliers', 1));
    $this->get('/employees?q=Maurer')
        ->assertInertia(fn ($page) => $page->count('employees', 1));
    $this->get('/vehicles?q=555')
        ->assertInertia(fn ($page) => $page->count('vehicles', 1));
});

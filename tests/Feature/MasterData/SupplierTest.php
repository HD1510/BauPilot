<?php

use App\Models\CostType;
use App\Models\Supplier;
use App\Support\Tenancy\CompanyContext;

afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('lieferant mit kurzzeichen, skonto und standard-kostenart anlegen', function () {
    [, $company] = actingMember();
    $costType = CostType::factory()->create(['company_id' => $company->id]);

    $this->post('/suppliers', [
        'name' => 'Baustoffe Nord',
        'short_code' => 'BSN',
        'payment_target_days' => 30,
        'default_cost_type_id' => $costType->id,
        'skonto_percent' => '3.00',
        'skonto_days' => 14,
    ])->assertSessionHasNoErrors();

    $supplier = Supplier::withoutGlobalScopes()->where('name', 'Baustoffe Nord')->firstOrFail();

    expect($supplier->company_id)->toBe($company->id)
        ->and($supplier->default_cost_type_id)->toBe($costType->id);
});

test('kurzzeichen ist je firma eindeutig, nicht global', function () {
    // Firma B hat das Kurzzeichen schon
    Supplier::factory()->create(['short_code' => 'BSN']);

    actingMember();

    $this->post('/suppliers', [
        'name' => 'Baustoffe Nord',
        'short_code' => 'BSN',
        'payment_target_days' => 30,
    ])->assertSessionHasNoErrors();

    // In derselben Firma schlägt es fehl
    $this->post('/suppliers', [
        'name' => 'Ganz anderer Name',
        'short_code' => 'BSN',
        'payment_target_days' => 30,
    ])->assertSessionHasErrors('short_code');
});

test('kostenart einer fremden firma wird als standard abgelehnt', function () {
    actingMember();
    $foreignCostType = CostType::factory()->create(); // fremde Firma

    $this->post('/suppliers', [
        'name' => 'Baustoffe Nord',
        'payment_target_days' => 30,
        'default_cost_type_id' => $foreignCostType->id,
    ])->assertSessionHasErrors('default_cost_type_id');
});

test('dubletten-vorschlag greift auch bei lieferanten', function () {
    actingMember();

    $this->post('/suppliers', ['name' => 'Baustoffhandel Gruber', 'payment_target_days' => 30, 'force' => true]);

    $response = $this->post('/suppliers', ['name' => 'BauStoffhandel-Gruber GmbH', 'payment_target_days' => 30]);
    $response->assertSessionHasErrors('name');
    $response->assertSessionHas('duplicates');

    expect(Supplier::withoutGlobalScopes()->count())->toBe(1);
});

test('lieferant lässt sich archivieren und wieder aktivieren', function () {
    [, $company] = actingMember();
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);

    $this->patch("/suppliers/{$supplier->id}/archive");
    expect($supplier->refresh()->active)->toBeFalse();

    $this->get('/suppliers')
        ->assertInertia(fn ($page) => $page->count('suppliers', 0));
    $this->get('/suppliers?archived=1')
        ->assertInertia(fn ($page) => $page->count('suppliers', 1));

    $this->patch("/suppliers/{$supplier->id}/archive");
    expect($supplier->refresh()->active)->toBeTrue();
});

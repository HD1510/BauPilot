<?php

use App\Enums\CompanyRole;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Support\Tenancy\CompanyContext;

afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('büro kann kunden anlegen, suchen und archivieren', function () {
    [, $company] = actingMember();

    // Anlegen
    $this->post('/customers', [
        'name' => 'Huber Wohnbau',
        'payment_target_days' => 14,
    ])->assertSessionHasNoErrors();

    $customer = Customer::withoutGlobalScopes()->where('name', 'Huber Wohnbau')->firstOrFail();
    expect($customer->company_id)->toBe($company->id);

    // Suchen
    $this->get('/customers?q=Huber')
        ->assertInertia(fn ($page) => $page->count('customers', 1));
    $this->get('/customers?q=Gibtsnicht')
        ->assertInertia(fn ($page) => $page->count('customers', 0));

    // Archivieren (Soft-Delete) und Liste
    $this->patch("/customers/{$customer->id}/archive");
    expect($customer->refresh()->trashed())->toBeTrue();

    $this->get('/customers')
        ->assertInertia(fn ($page) => $page->count('customers', 0));
    $this->get('/customers?archived=1')
        ->assertInertia(fn ($page) => $page->count('customers', 1));

    // Wieder aktivieren
    $this->patch("/customers/{$customer->id}/restore");
    expect($customer->refresh()->trashed())->toBeFalse();
});

test('rolle baustelle kann stammdaten lesen aber nicht schreiben', function () {
    [, $company] = actingMember(CompanyRole::Site);

    $customer = Customer::factory()->create(['company_id' => $company->id]);

    $this->get('/customers')->assertOk();
    $this->post('/customers', ['name' => 'Neu', 'payment_target_days' => 14])->assertForbidden();
    $this->patch("/customers/{$customer->id}", ['name' => 'Anders', 'payment_target_days' => 14])->assertForbidden();
    $this->patch("/customers/{$customer->id}/archive")->assertForbidden();
});

test('dubletten-vorschlag greift nachweislich: müller vs mueller', function () {
    actingMember();

    $this->post('/customers', ['name' => 'Müller Bau GmbH', 'payment_target_days' => 14, 'force' => true]);

    // Ähnliche Schreibweise ohne Bestätigung → kein neuer Datensatz, Vorschlag in der Session
    $response = $this->post('/customers', ['name' => 'Mueller-Bau GmbH', 'payment_target_days' => 14]);
    $response->assertSessionHasErrors('name');
    $response->assertSessionHas('duplicates');

    expect(Customer::withoutGlobalScopes()->count())->toBe(1);

    // Mit ausdrücklicher Bestätigung wird angelegt
    $this->post('/customers', ['name' => 'Mueller-Bau GmbH', 'payment_target_days' => 14, 'force' => true])
        ->assertSessionHasNoErrors();

    expect(Customer::withoutGlobalScopes()->count())->toBe(2);
});

test('eindeutig neue namen werden ohne rückfrage angelegt', function () {
    actingMember();

    $this->post('/customers', ['name' => 'Müller Bau GmbH', 'payment_target_days' => 14, 'force' => true]);

    $this->post('/customers', ['name' => 'Zimmerei Hofer', 'payment_target_days' => 14])
        ->assertSessionHasNoErrors();

    expect(Customer::withoutGlobalScopes()->count())->toBe(2);
});

test('ansprechpartner lassen sich hinzufügen und entfernen', function () {
    [, $company] = actingMember();
    $customer = Customer::factory()->create(['company_id' => $company->id]);

    $this->post("/customers/{$customer->id}/contacts", [
        'name' => 'Max Polier',
        'phone' => '0664 1234567',
    ])->assertSessionHasNoErrors();

    $contact = CustomerContact::withoutGlobalScopes()->where('name', 'Max Polier')->firstOrFail();
    expect($contact->company_id)->toBe($company->id);

    $this->delete("/customers/{$customer->id}/contacts/{$contact->id}");
    expect(CustomerContact::withoutGlobalScopes()->count())->toBe(0);
});

test('veraltete lock_version führt beim kunden zum konflikt', function () {
    [, $company] = actingMember();
    $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Original']);

    $customer->update(['name' => 'Zwischenstand']);

    $this->patch("/customers/{$customer->id}", [
        'name' => 'Mein Stand',
        'payment_target_days' => 14,
        'lock_version' => 0,
    ])->assertSessionHasErrors('lock_version');

    expect($customer->refresh()->name)->toBe('Zwischenstand');
});

test('kunden anderer firmen sind auch per direkter id unerreichbar', function () {
    actingMember();
    $foreign = Customer::factory()->create(); // eigene, fremde Firma

    $this->get("/customers/{$foreign->id}/edit")->assertNotFound();
    $this->patch("/customers/{$foreign->id}", ['name' => 'Hack', 'payment_target_days' => 14])->assertNotFound();
});

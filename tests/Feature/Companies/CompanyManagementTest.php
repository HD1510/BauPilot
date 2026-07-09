<?php

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;

test('wer eine firma anlegt wird ihr administrator und sie wird aktiv', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/companies', [
        'name' => 'Neue Bau GmbH',
        'short_code' => 'NBG',
        'color' => '#112233',
        'fiscal_year_start_month' => 9,
        'warranty_years' => 3,
    ])->assertRedirect(route('companies.index'));

    $company = Company::query()->where('short_code', 'NBG')->firstOrFail();

    expect($user->roleIn($company))->toBe(CompanyRole::Admin)
        ->and(session('active_company_id'))->toBe($company->id);
});

test('nur admins können die firma bearbeiten', function () {
    $company = Company::factory()->create();
    $office = User::factory()->create();
    $company->users()->attach($office->id, ['role' => CompanyRole::Office->value]);

    $this->actingAs($office)
        ->patch("/companies/{$company->id}", ['name' => 'Umbenannt'])
        ->assertForbidden();
});

test('ein admin kann die firma bearbeiten und lock_version zählt hoch', function () {
    $company = Company::factory()->create(['name' => 'Alt']);
    $admin = User::factory()->create();
    $company->users()->attach($admin->id, ['role' => CompanyRole::Admin->value]);

    $this->actingAs($admin)->patch("/companies/{$company->id}", [
        'name' => 'Neu',
        'short_code' => $company->short_code,
        'color' => $company->color,
        'fiscal_year_start_month' => 9,
        'warranty_years' => 3,
        'lock_version' => 0,
    ])->assertSessionHasNoErrors();

    $company->refresh();

    expect($company->name)->toBe('Neu')
        ->and($company->lock_version)->toBe(1);
});

test('eine veraltete lock_version führt zum konflikt statt zum überschreiben', function () {
    $company = Company::factory()->create(['name' => 'Original']);
    $admin = User::factory()->create();
    $company->users()->attach($admin->id, ['role' => CompanyRole::Admin->value]);

    // Jemand anderes hat zwischenzeitlich gespeichert:
    $company->update(['name' => 'Zwischenstand']);

    $this->actingAs($admin)->patch("/companies/{$company->id}", [
        'name' => 'Mein Stand',
        'short_code' => $company->short_code,
        'color' => $company->color,
        'fiscal_year_start_month' => 9,
        'warranty_years' => 3,
        'lock_version' => 0,
    ])->assertSessionHasErrors('lock_version');

    expect($company->refresh()->name)->toBe('Zwischenstand');
});

test('admin kann benutzer mit rolle zuordnen und rolle ändern', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create();
    $company->users()->attach($admin->id, ['role' => CompanyRole::Admin->value]);
    $colleague = User::factory()->create(['email' => 'kollege@example.com']);

    $this->actingAs($admin)->post("/companies/{$company->id}/members", [
        'email' => 'kollege@example.com',
        'role' => 'site',
    ])->assertSessionHasNoErrors();

    expect($colleague->roleIn($company))->toBe(CompanyRole::Site);

    $this->actingAs($admin)->patch("/companies/{$company->id}/members/{$colleague->id}", [
        'role' => 'office',
    ])->assertSessionHasNoErrors();

    expect($colleague->refresh()->roleIn($company))->toBe(CompanyRole::Office);
});

test('unbekannte e-mail-adresse liefert einen verständlichen fehler', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create();
    $company->users()->attach($admin->id, ['role' => CompanyRole::Admin->value]);

    $this->actingAs($admin)->post("/companies/{$company->id}/members", [
        'email' => 'gibtsnicht@example.com',
        'role' => 'office',
    ])->assertSessionHasErrors('email');
});

test('der letzte admin kann weder entfernt noch herabgestuft werden', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create();
    $company->users()->attach($admin->id, ['role' => CompanyRole::Admin->value]);

    $this->actingAs($admin)->patch("/companies/{$company->id}/members/{$admin->id}", [
        'role' => 'office',
    ])->assertSessionHasErrors('role');

    $this->actingAs($admin)->delete("/companies/{$company->id}/members/{$admin->id}")
        ->assertSessionHasErrors('role');

    expect($admin->roleIn($company))->toBe(CompanyRole::Admin);
});

test('admin kann die firma archivieren und wieder aktivieren', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->create();
    $company->users()->attach($admin->id, ['role' => CompanyRole::Admin->value]);

    $this->actingAs($admin)->patch("/companies/{$company->id}/archive");
    expect($company->refresh()->isArchived())->toBeTrue();

    $this->actingAs($admin)->patch("/companies/{$company->id}/archive");
    expect($company->refresh()->isArchived())->toBeFalse();
});

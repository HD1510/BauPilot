<?php

use App\Enums\CompanyRole;
use App\Models\Employee;
use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Facades\Hash;

/**
 * Mitarbeiterkonten hängen am Mitarbeiter (Personalakte): Der Admin
 * vergibt Benutzername und Startpasswort, die Anmeldung läuft über den
 * Benutzernamen — ganz ohne E-Mail.
 */
afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('admin legt ein konto mit benutzername am mitarbeiter an', function () {
    [, $company] = actingMember(CompanyRole::Admin);
    $employee = Employee::factory()->create(['company_id' => $company->id, 'name' => 'Max Polier']);

    $this->post("/employees/{$employee->id}/account", [
        'username' => 'Max.Polier',
        'password' => 'baustelle123',
        'role' => 'site',
    ])->assertSessionHasNoErrors();

    $user = User::where('username', 'max.polier')->firstOrFail();

    expect($user->name)->toBe('Max Polier')
        ->and($user->email)->toBeNull()
        ->and(Hash::check('baustelle123', $user->password))->toBeTrue()
        ->and($company->users()->whereKey($user->id)->first()?->pivot?->role)->toBe(CompanyRole::Site)
        ->and($employee->refresh()->user_id)->toBe($user->id);

    // Anmeldung mit Benutzername (Feld heißt technisch weiterhin email).
    auth()->logout();
    $this->post('/login', ['email' => 'Max.Polier', 'password' => 'baustelle123'])
        ->assertRedirect('/dashboard');
});

test('mitarbeiter und konto entstehen in einem schritt', function () {
    actingMember(CompanyRole::Admin);

    $this->post('/employees', [
        'name' => 'Susi Büro',
        'create_account' => true,
        'username' => 'susi.buero',
        'email' => 'susi@example.at',
        'password' => 'baustelle123',
        'role' => 'office',
    ])->assertSessionHasNoErrors();

    $employee = Employee::where('name', 'Susi Büro')->firstOrFail();
    $user = User::where('username', 'susi.buero')->firstOrFail();

    expect($employee->user_id)->toBe($user->id)
        ->and($user->email)->toBe('susi@example.at');

    // Ohne E-Mail geht es genauso — sie ist optional.
    $this->post('/employees', [
        'name' => 'Max Polier',
        'create_account' => true,
        'username' => 'max.polier',
        'password' => 'baustelle123',
        'role' => 'site',
    ])->assertSessionHasNoErrors();

    expect(User::where('username', 'max.polier')->value('email'))->toBeNull();
});

test('vergebener benutzername verhindert auch die mitarbeiter-anlage', function () {
    actingMember(CompanyRole::Admin);
    User::factory()->create(['username' => 'max.polier']);

    $this->post('/employees', [
        'name' => 'Max Polier',
        'create_account' => true,
        'username' => 'max.polier',
        'password' => 'baustelle123',
        'role' => 'site',
    ])->assertSessionHasErrors('username');

    expect(Employee::where('name', 'Max Polier')->exists())->toBeFalse();
});

test('büro darf mitarbeiter anlegen, aber nicht mit konto', function () {
    actingMember(CompanyRole::Office);

    $this->post('/employees', [
        'name' => 'Nur Mitarbeiter',
    ])->assertSessionHasNoErrors();

    $this->post('/employees', [
        'name' => 'Mit Konto',
        'create_account' => true,
        'username' => 'mit.konto',
        'password' => 'baustelle123',
        'role' => 'site',
    ])->assertForbidden();

    expect(Employee::where('name', 'Mit Konto')->exists())->toBeFalse();
});

test('anmeldung mit e-mail-adresse funktioniert weiterhin', function () {
    [$user] = actingMember(CompanyRole::Admin);

    auth()->logout();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');
});

test('vergebener benutzername, kurzes passwort und doppeltes konto werden abgelehnt', function () {
    [, $company] = actingMember(CompanyRole::Admin);
    User::factory()->create(['username' => 'max.polier']);
    $employee = Employee::factory()->create(['company_id' => $company->id]);

    $this->post("/employees/{$employee->id}/account", [
        'username' => 'max.polier',
        'password' => 'baustelle123',
        'role' => 'site',
    ])->assertSessionHasErrors('username');

    $this->post("/employees/{$employee->id}/account", [
        'username' => 'neuer.name',
        'password' => 'kurz',
        'role' => 'site',
    ])->assertSessionHasErrors('password');

    // Hat der Mitarbeiter schon ein Konto, gibt es kein zweites.
    $linked = User::factory()->create();
    $company->users()->attach($linked->id, ['role' => CompanyRole::Site->value]);
    $employee->forceFill(['user_id' => $linked->id])->save();

    $this->post("/employees/{$employee->id}/account", [
        'username' => 'noch.einer',
        'password' => 'baustelle123',
        'role' => 'site',
    ])->assertSessionHasErrors('username');
});

test('nur admins dürfen mitarbeiterkonten anlegen', function () {
    [, $company] = actingMember(CompanyRole::Office);
    $employee = Employee::factory()->create(['company_id' => $company->id]);

    $this->post("/employees/{$employee->id}/account", [
        'username' => 'versuch',
        'password' => 'baustelle123',
        'role' => 'site',
    ])->assertForbidden();

    expect(User::where('username', 'versuch')->exists())->toBeFalse();
});

test('admin setzt ein neues startpasswort', function () {
    [, $company] = actingMember(CompanyRole::Admin);
    $account = User::factory()->create(['username' => 'max.polier', 'email' => null]);
    $company->users()->attach($account->id, ['role' => CompanyRole::Site->value]);
    $employee = Employee::factory()->create(['company_id' => $company->id, 'user_id' => $account->id]);

    $this->patch("/employees/{$employee->id}/account/password", [
        'password' => 'neues-passwort9',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('neues-passwort9', $account->refresh()->password))->toBeTrue();

    auth()->logout();
    $this->post('/login', ['email' => 'max.polier', 'password' => 'neues-passwort9'])
        ->assertRedirect('/dashboard');
});

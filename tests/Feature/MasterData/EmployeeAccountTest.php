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

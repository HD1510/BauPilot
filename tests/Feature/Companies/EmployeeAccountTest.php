<?php

use App\Enums\CompanyRole;
use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Facades\Hash;

/**
 * Mitarbeiterkonten: Der Admin legt Benutzerkonten mit Startpasswort
 * direkt an — ohne Mailversand gilt das Konto sofort als bestätigt.
 */
afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('admin legt ein mitarbeiterkonto an — sofort einsatzbereit', function () {
    [, $company] = actingMember(CompanyRole::Admin);

    $this->post("/companies/{$company->id}/accounts", [
        'name' => 'Max Polier',
        'email' => 'max@example.at',
        'password' => 'baustelle123',
        'role' => 'site',
    ])->assertSessionHasNoErrors();

    $user = User::where('email', 'max@example.at')->firstOrFail();

    expect($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('baustelle123', $user->password))->toBeTrue()
        ->and($company->users()->whereKey($user->id)->first()?->pivot?->role)->toBe(CompanyRole::Site);

    // Und das Konto kann sich sofort anmelden (keine Bestätigungs-Hürde).
    auth()->logout();
    $this->post('/login', ['email' => 'max@example.at', 'password' => 'baustelle123'])
        ->assertRedirect('/dashboard');
});

test('doppelte e-mail und zu kurzes passwort werden abgelehnt', function () {
    [$admin, $company] = actingMember(CompanyRole::Admin);

    $this->post("/companies/{$company->id}/accounts", [
        'name' => 'Doppelt',
        'email' => $admin->email,
        'password' => 'baustelle123',
        'role' => 'site',
    ])->assertSessionHasErrors('email');

    $this->post("/companies/{$company->id}/accounts", [
        'name' => 'Kurz',
        'email' => 'kurz@example.at',
        'password' => 'kurz',
        'role' => 'site',
    ])->assertSessionHasErrors('password');

    expect(User::where('email', 'kurz@example.at')->exists())->toBeFalse();
});

test('nur admins dürfen mitarbeiterkonten anlegen', function () {
    [, $company] = actingMember(CompanyRole::Office);

    $this->post("/companies/{$company->id}/accounts", [
        'name' => 'Versuch',
        'email' => 'versuch@example.at',
        'password' => 'baustelle123',
        'role' => 'site',
    ])->assertForbidden();

    expect(User::where('email', 'versuch@example.at')->exists())->toBeFalse();
});

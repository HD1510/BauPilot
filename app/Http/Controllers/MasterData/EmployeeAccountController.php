<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\CompanyRole;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Benutzerkonto direkt am Mitarbeiter (Personalakte): Der Admin vergibt
 * Benutzername und Startpasswort und gibt beides persönlich weiter —
 * ohne E-Mail-Pflicht, die Anmeldung läuft über den Benutzernamen.
 */
class EmployeeAccountController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $company = app(CompanyContext::class)->requireCompany();

        Gate::authorize('manageMembers', $company);

        if ($employee->user_id !== null) {
            throw ValidationException::withMessages([
                'username' => 'Dieser Mitarbeiter hat bereits ein Benutzerkonto.',
            ]);
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/i'],
            'password' => ['required', 'string', PasswordRule::min(8)],
            'role' => ['required', Rule::enum(CompanyRole::class)],
        ], [
            'username.regex' => 'Der Benutzername darf nur Buchstaben, Ziffern, Punkt, Bindestrich und Unterstrich enthalten.',
        ], ['username' => 'Benutzername', 'password' => 'Passwort', 'role' => 'Rolle']);

        $username = Str::lower($validated['username']);

        if (User::query()->where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'username' => 'Dieser Benutzername ist bereits vergeben.',
            ]);
        }

        $user = User::create([
            'name' => $employee->name,
            'username' => $username,
            'email' => null,
            'password' => $validated['password'],
        ]);

        // Vom Admin angelegt — gilt sofort als bestätigt.
        $user->forceFill(['email_verified_at' => now()])->save();

        $company->users()->attach($user->id, ['role' => $validated['role']]);
        $employee->update(['user_id' => $user->id]);

        return back()->with('success', "Konto „{$username}“ wurde angelegt — Zugangsdaten bitte persönlich weitergeben.");
    }

    /**
     * Neues Startpasswort: Konten ohne E-Mail können den normalen
     * „Passwort vergessen"-Weg nicht nutzen — der Admin setzt neu.
     */
    public function updatePassword(Request $request, Employee $employee): RedirectResponse
    {
        $company = app(CompanyContext::class)->requireCompany();

        Gate::authorize('manageMembers', $company);

        $user = $employee->user;

        if ($user === null) {
            throw ValidationException::withMessages([
                'password' => 'Dieser Mitarbeiter hat kein Benutzerkonto.',
            ]);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', PasswordRule::min(8)],
        ], [], ['password' => 'Passwort']);

        $user->forceFill(['password' => $validated['password']])->save();

        return back()->with('success', 'Neues Startpasswort gesetzt — bitte persönlich weitergeben.');
    }
}

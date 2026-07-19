<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\CompanyRole;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\Employees\EmployeeAccountCreator;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Benutzerkonto direkt am Mitarbeiter (Personalakte): Der Admin vergibt
 * Benutzername und Startpasswort und gibt beides persönlich weiter —
 * ohne E-Mail-Pflicht, die Anmeldung läuft über den Benutzernamen.
 */
class EmployeeAccountController extends Controller
{
    public function store(Request $request, Employee $employee, EmployeeAccountCreator $accounts): RedirectResponse
    {
        $company = app(CompanyContext::class)->requireCompany();

        Gate::authorize('manageMembers', $company);

        if ($employee->user_id !== null) {
            throw ValidationException::withMessages([
                'username' => 'Dieser Mitarbeiter hat bereits ein Benutzerkonto.',
            ]);
        }

        $validated = $request->validate(
            EmployeeAccountCreator::rules(),
            EmployeeAccountCreator::messages(),
            EmployeeAccountCreator::attributes(),
        );

        $accounts->ensureUsernameFree($validated['username']);

        $user = $accounts->create(
            $employee,
            $company,
            $validated['username'],
            $validated['email'] ?? null,
            $validated['password'],
            CompanyRole::from($validated['role']),
        );

        return back()->with('success', "Konto „{$user->username}“ wurde angelegt — Zugangsdaten bitte persönlich weitergeben.");
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

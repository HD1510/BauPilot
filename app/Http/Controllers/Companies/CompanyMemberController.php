<?php

namespace App\Http\Controllers\Companies;

use App\Enums\CompanyRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class CompanyMemberController extends Controller
{
    public function store(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('manageMembers', $company);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(CompanyRole::class)],
        ], [], ['email' => 'E-Mail-Adresse', 'role' => 'Rolle']);

        $user = User::where('email', $validated['email'])->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'Es gibt kein Benutzerkonto mit dieser E-Mail-Adresse. Der Benutzer muss sich zuerst registrieren.',
            ]);
        }

        if ($company->users()->whereKey($user->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Dieser Benutzer ist der Firma bereits zugeordnet.',
            ]);
        }

        $company->users()->attach($user->id, ['role' => $validated['role']]);

        return back()->with('success', "{$user->name} wurde als „".CompanyRole::from($validated['role'])->label().'“ hinzugefügt.');
    }

    /**
     * Mitarbeiterkonto direkt anlegen: Der Admin vergibt Name, E-Mail
     * und Startpasswort und gibt beides an die Person weiter. Ohne
     * Mailversand gibt es keine Bestätigungsmail — das vom Admin
     * angelegte Konto gilt sofort als bestätigt; das Passwort kann die
     * Person in den Einstellungen ändern.
     */
    public function storeAccount(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('manageMembers', $company);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', PasswordRule::min(8)],
            'role' => ['required', Rule::enum(CompanyRole::class)],
        ], [], ['name' => 'Name', 'email' => 'E-Mail-Adresse', 'password' => 'Passwort', 'role' => 'Rolle']);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $company->users()->attach($user->id, ['role' => $validated['role']]);

        return back()->with('success', "Konto für {$user->name} wurde angelegt — Zugangsdaten bitte persönlich weitergeben.");
    }

    public function update(Request $request, Company $company, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $company);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(CompanyRole::class)],
        ], [], ['role' => 'Rolle']);

        $this->ensureNotLastAdmin($company, $user);

        $company->users()->updateExistingPivot($user->id, ['role' => $validated['role']]);

        return back()->with('success', 'Rolle aktualisiert.');
    }

    public function destroy(Request $request, Company $company, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $company);

        $this->ensureNotLastAdmin($company, $user);

        $company->users()->detach($user->id);

        return back()->with('success', "{$user->name} wurde aus der Firma entfernt.");
    }

    /**
     * Eine Firma darf nie ohne Administrator zurückbleiben.
     */
    private function ensureNotLastAdmin(Company $company, User $user): void
    {
        $isAdmin = $company->users()
            ->whereKey($user->id)
            ->wherePivot('role', CompanyRole::Admin->value)
            ->exists();

        if (! $isAdmin) {
            return;
        }

        $otherAdmins = $company->users()
            ->whereKeyNot($user->id)
            ->wherePivot('role', CompanyRole::Admin->value)
            ->count();

        if ($otherAdmins === 0) {
            throw ValidationException::withMessages([
                'role' => 'Der letzte Administrator einer Firma kann nicht entfernt oder herabgestuft werden.',
            ]);
        }
    }
}

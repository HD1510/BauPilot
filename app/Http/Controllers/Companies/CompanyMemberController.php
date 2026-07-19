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

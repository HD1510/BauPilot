<?php

namespace App\Support\Employees;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Benutzerkonto zum Mitarbeiter: Benutzername (Pflicht), E-Mail
 * (optional) und Startpasswort — genutzt beim Anlegen des Mitarbeiters
 * und nachträglich im Zugang-Bereich der Personalakte.
 */
class EmployeeAccountCreator
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/i'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', PasswordRule::min(8)],
            'role' => ['required', Rule::enum(CompanyRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'username.regex' => 'Der Benutzername darf nur Buchstaben, Ziffern, Punkt, Bindestrich und Unterstrich enthalten.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'username' => 'Benutzername',
            'email' => 'E-Mail-Adresse',
            'password' => 'Passwort',
            'role' => 'Rolle',
        ];
    }

    public function ensureUsernameFree(string $username): void
    {
        if (User::query()->where('username', Str::lower($username))->exists()) {
            throw ValidationException::withMessages([
                'username' => 'Dieser Benutzername ist bereits vergeben.',
            ]);
        }
    }

    public function create(
        Employee $employee,
        Company $company,
        string $username,
        ?string $email,
        string $password,
        CompanyRole $role,
    ): User {
        $user = User::create([
            'name' => $employee->name,
            'username' => Str::lower($username),
            'email' => $email,
            'password' => $password,
        ]);

        // Vom Admin angelegt — gilt sofort als bestätigt.
        $user->forceFill(['email_verified_at' => now()])->save();

        $company->users()->attach($user->id, ['role' => $role->value]);
        $employee->update(['user_id' => $user->id]);

        return $user;
    }
}

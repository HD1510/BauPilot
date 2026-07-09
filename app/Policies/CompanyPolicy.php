<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;

/**
 * Rechte je Firma aus dem company_user-Pivot (Architekturblatt Abschnitt 3):
 * sehen darf jedes Mitglied, verwalten nur die Rolle admin — serverseitig,
 * unabhängig davon, was die Oberfläche anzeigt.
 */
class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        return $user->roleIn($company) !== null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Company $company): bool
    {
        return $user->roleIn($company) === CompanyRole::Admin;
    }

    public function archive(User $user, Company $company): bool
    {
        return $this->update($user, $company);
    }

    public function manageMembers(User $user, Company $company): bool
    {
        return $user->roleIn($company) === CompanyRole::Admin;
    }

    public function switchTo(User $user, Company $company): bool
    {
        return $user->roleIn($company) !== null;
    }
}

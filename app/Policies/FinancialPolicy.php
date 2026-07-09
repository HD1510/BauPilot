<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Belege und Beträge (Architekturblatt Abschnitt 3): Die Rolle Baustelle
 * sieht keine Rechnungen, offenen Posten, Fremdangebote oder Beträge —
 * Finanzdaten verlassen den Server für sie gar nicht erst.
 */
abstract class FinancialPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canViewFinancials($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->canViewFinancials($user);
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->canWrite($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->canWrite($user);
    }

    protected function canViewFinancials(User $user): bool
    {
        $role = $user->currentRole();

        return $role !== null && $role->canViewFinancials();
    }

    protected function canWrite(User $user): bool
    {
        return in_array($user->currentRole(), [CompanyRole::Admin, CompanyRole::Office], true);
    }
}

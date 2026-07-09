<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Models\ImportRun;
use App\Models\User;

/**
 * Der Excel-Import verändert den Datenbestand großflächig — nur admin.
 */
class ImportRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentRole() === CompanyRole::Admin;
    }

    public function view(User $user, ImportRun $run): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ImportRun $run): bool
    {
        return $this->viewAny($user);
    }
}

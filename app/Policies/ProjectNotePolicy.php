<?php

namespace App\Policies;

use App\Models\ProjectNote;
use App\Models\User;

/**
 * Notizen sind Baustellen-Funktionen: jedes Firmenmitglied darf lesen
 * und anlegen; löschen dürfen Verfasser und admin/büro.
 */
class ProjectNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentRole() !== null;
    }

    public function create(User $user): bool
    {
        return $user->currentRole() !== null;
    }

    public function delete(User $user, ProjectNote $note): bool
    {
        if ($note->created_by === $user->id) {
            return true;
        }

        return $user->currentRole()?->canViewFinancials() ?? false;
    }
}

<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Aufgaben und Mängel sind Baustellen-Funktionen (Architekturblatt 3):
 * jedes Firmenmitglied — ausdrücklich auch die Rolle Baustelle — darf
 * anlegen, erledigen und bearbeiten. Die Mandantentrennung übernimmt
 * der Global Scope.
 */
class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentRole() !== null;
    }

    public function view(User $user, Task $task): bool
    {
        return $user->currentRole() !== null;
    }

    public function create(User $user): bool
    {
        return $user->currentRole() !== null;
    }

    public function update(User $user, Task $task): bool
    {
        return $user->currentRole() !== null;
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->currentRole() !== null;
    }
}

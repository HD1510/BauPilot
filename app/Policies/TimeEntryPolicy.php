<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Models\TimeEntry;
use App\Models\User;

/**
 * Zeiterfassung ist Baustellen-Funktion (Architekturblatt 3): jedes
 * Firmenmitglied erfasst Stunden. Korrigieren/Löschen dürfen Verfasser
 * und Büro — die BEWERTETEN Zahlen (Lohnkosten, Deckungsbeitrag) sind
 * davon getrennt und bleiben hinter dem Finanz-Gate.
 */
class TimeEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentRole() !== null;
    }

    public function create(User $user): bool
    {
        return $user->currentRole() !== null;
    }

    public function delete(User $user, TimeEntry $entry): bool
    {
        if ($entry->created_by === $user->id) {
            return true;
        }

        return in_array($user->currentRole(), [CompanyRole::Admin, CompanyRole::Office], true);
    }
}

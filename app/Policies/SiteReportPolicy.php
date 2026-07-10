<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Models\SiteReport;
use App\Models\User;

/**
 * Regieberichte sind Baustellen-Funktion: jedes Firmenmitglied erfasst,
 * liest und unterschreibt. Nach der Unterschrift ist der Bericht ein
 * Beleg — gesperrt für alle (Architekturblatt 4.1: Belege werden nie
 * gelöscht).
 */
class SiteReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentRole() !== null;
    }

    public function view(User $user, SiteReport $report): bool
    {
        return $user->currentRole() !== null;
    }

    public function create(User $user): bool
    {
        return $user->currentRole() !== null;
    }

    public function update(User $user, SiteReport $report): bool
    {
        return ! $report->isSigned() && $user->currentRole() !== null;
    }

    public function sign(User $user, SiteReport $report): bool
    {
        return $user->currentRole() !== null;
    }

    public function delete(User $user, SiteReport $report): bool
    {
        if ($report->isSigned()) {
            return false;
        }

        if ($report->created_by === $user->id) {
            return true;
        }

        return in_array($user->currentRole(), [CompanyRole::Admin, CompanyRole::Office], true);
    }
}

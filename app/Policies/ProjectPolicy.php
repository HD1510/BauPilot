<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Projekte sieht jedes Firmenmitglied — die Baustelle braucht Termine,
 * Pläne und Fotos. Beträge im Projekt filtert der Controller über das
 * view-financials-Gate heraus.
 */
class ProjectPolicy extends MasterDataPolicy
{
    /**
     * Fotos und Dateien ans Projekt hängen darf JEDES Mitglied —
     * genau dafür ist die Kamera-Funktion da (M7).
     */
    public function attach(User $user, Model $model): bool
    {
        return $user->currentRole() !== null;
    }
}

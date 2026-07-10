<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Gemeinsame Rechte für Stammdaten: lesen darf jedes Mitglied der aktiven
 * Firma, anlegen/ändern/archivieren nur admin und büro. Die Mandanten-
 * trennung selbst übernimmt der Global Scope — ein fremder Datensatz ist
 * hier gar nie sichtbar (404 statt 403).
 */
abstract class MasterDataPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentRole() !== null;
    }

    public function view(User $user, Model $model): bool
    {
        return $user->currentRole() !== null;
    }

    public function create(User $user): bool
    {
        return $this->canWrite($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->canWrite($user);
    }

    public function archive(User $user, Model $model): bool
    {
        return $this->canWrite($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->canWrite($user);
    }

    /**
     * Datei anhängen (M7): standardmäßig wie schreiben — Projekte
     * öffnen das für alle Mitglieder (Fotos von der Baustelle).
     */
    public function attach(User $user, Model $model): bool
    {
        return $this->canWrite($user);
    }

    protected function canWrite(User $user): bool
    {
        return in_array($user->currentRole(), [CompanyRole::Admin, CompanyRole::Office], true);
    }
}

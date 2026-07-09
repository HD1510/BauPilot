<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Optimistische Sperre nach Architekturblatt Abschnitt 3: lock_version
 * zählt bei jedem Update hoch. Formulare senden die gelesene Version mit;
 * der Abgleich passiert beim Speichern (Konfliktantwort statt Überschreiben).
 */
trait HasLockVersion
{
    public static function bootHasLockVersion(): void
    {
        static::updating(function (Model $model): void {
            $model->setAttribute('lock_version', (int) $model->getOriginal('lock_version') + 1);
        });
    }

    public function isStale(int $submittedLockVersion): bool
    {
        return $submittedLockVersion !== (int) $this->getOriginal('lock_version');
    }
}

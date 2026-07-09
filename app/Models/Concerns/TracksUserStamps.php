<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Befüllt created_by und updated_by aus dem angemeldeten Benutzer;
 * in Jobs und Konsolenbefehlen bleiben die Spalten unberührt bzw. null.
 */
trait TracksUserStamps
{
    public static function bootTracksUserStamps(): void
    {
        static::creating(function (Model $model): void {
            if (Auth::check()) {
                $model->getAttribute('created_by') === null && $model->setAttribute('created_by', Auth::id());
                $model->getAttribute('updated_by') === null && $model->setAttribute('updated_by', Auth::id());
            }
        });

        static::updating(function (Model $model): void {
            if (Auth::check()) {
                $model->setAttribute('updated_by', Auth::id());
            }
        });
    }
}

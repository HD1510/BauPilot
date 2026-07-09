<?php

namespace App\Models\Concerns;

use App\Support\Duplicates\NameNormalizer;
use Illuminate\Database\Eloquent\Model;

/**
 * Hält normalized_name synchron zum Namen — Grundlage für die
 * Trigramm-Dublettensuche.
 */
trait HasNormalizedName
{
    public static function bootHasNormalizedName(): void
    {
        static::saving(function (Model $model): void {
            $model->setAttribute('normalized_name', NameNormalizer::normalize((string) $model->getAttribute('name')));
        });
    }
}

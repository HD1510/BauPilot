<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use Database\Factories\CalculationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baukalkulation: Räume mit Gewerke-Mengen und Preisen je Kalkulation —
 * daraus entsteht eine Angebotssumme.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property int|null $project_id
 * @property numeric-string $waste_percent
 * @property numeric-string $wall_tile_height
 * @property numeric-string $price_parquet
 * @property numeric-string $price_floor_tiles
 * @property numeric-string $price_wall_tiles
 * @property numeric-string $price_silicone
 * @property numeric-string $price_skirting
 * @property numeric-string $price_painting
 * @property string|null $notes
 * @property int $lock_version
 */
#[Fillable([
    'name',
    'project_id',
    'waste_percent',
    'wall_tile_height',
    'price_parquet',
    'price_floor_tiles',
    'price_wall_tiles',
    'price_silicone',
    'price_skirting',
    'price_painting',
    'notes',
])]
class Calculation extends Model
{
    /** @use HasFactory<CalculationFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'waste_percent' => 'decimal:2',
            'wall_tile_height' => 'decimal:2',
            'price_parquet' => 'decimal:2',
            'price_floor_tiles' => 'decimal:2',
            'price_wall_tiles' => 'decimal:2',
            'price_silicone' => 'decimal:2',
            'price_skirting' => 'decimal:2',
            'price_painting' => 'decimal:2',
            'lock_version' => 'integer',
        ];
    }

    /** @return HasMany<CalculationRoom, $this> */
    public function rooms(): HasMany
    {
        return $this->hasMany(CalculationRoom::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

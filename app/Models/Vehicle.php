<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use Carbon\CarbonImmutable;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fuhrpark mit Pickerl- und Vignetten-Terminen (Architekturblatt 4.3).
 *
 * @property int $id
 * @property int $company_id
 * @property string $plate
 * @property string|null $brand
 * @property string|null $model
 * @property CarbonImmutable|null $inspection_due_on
 * @property CarbonImmutable|null $vignette_until
 * @property string|null $fuel_card
 * @property bool $active
 * @property string|null $notes
 * @property int $lock_version
 */
#[Fillable(['plate', 'brand', 'model', 'inspection_due_on', 'vignette_until', 'fuel_card', 'active', 'notes'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'inspection_due_on' => 'date',
            'vignette_until' => 'date',
            'active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /** @return HasMany<VehicleDate, $this> */
    public function dates(): HasMany
    {
        return $this->hasMany(VehicleDate::class);
    }
}

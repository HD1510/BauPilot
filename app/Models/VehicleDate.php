<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use Carbon\CarbonImmutable;
use Database\Factories\VehicleDateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Weiterer Fahrzeugtermin (z. B. Service, Eichung).
 *
 * @property int $id
 * @property int $company_id
 * @property int $vehicle_id
 * @property string $label
 * @property CarbonImmutable $due_on
 * @property int $lock_version
 */
#[Fillable(['label', 'due_on'])]
class VehicleDate extends Model
{
    /** @use HasFactory<VehicleDateFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\HasNormalizedName;
use App\Models\Concerns\TracksUserStamps;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lieferantenstamm (Architekturblatt 4.3).
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $normalized_name
 * @property string|null $short_code
 * @property int $payment_target_days
 * @property int|null $default_cost_type_id
 * @property numeric-string|null $skonto_percent
 * @property int|null $skonto_days
 * @property bool $active
 * @property string|null $notes
 * @property int $lock_version
 */
#[Fillable(['name', 'short_code', 'payment_target_days', 'default_cost_type_id', 'skonto_percent', 'skonto_days', 'active', 'notes', 'source_ref'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, HasNormalizedName, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'payment_target_days' => 'integer',
            'skonto_percent' => 'decimal:2',
            'skonto_days' => 'integer',
            'active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<CostType, $this> */
    public function defaultCostType(): BelongsTo
    {
        return $this->belongsTo(CostType::class, 'default_cost_type_id');
    }

    /** @return HasMany<Material, $this> */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}

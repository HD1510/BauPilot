<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use Database\Factories\CostTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Kostenart je Firma (Architekturblatt 4.3). Dient in M1 zugleich als
 * Referenzmodell für die Mandantentrennung; die Oberfläche folgt in M2.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property int $sort_order
 * @property bool $active
 * @property int $lock_version
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'sort_order', 'active'])]
class CostType extends Model
{
    /** @use HasFactory<CostTypeFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }
}

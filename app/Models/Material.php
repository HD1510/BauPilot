<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Artikel-Preisliste je Lieferant (Architekturblatt 4.3).
 *
 * @property int $id
 * @property int $company_id
 * @property int $supplier_id
 * @property string|null $article_no
 * @property string $name
 * @property numeric-string|null $price_net
 * @property string|null $package_unit
 * @property bool $active
 * @property string|null $notes
 * @property int $lock_version
 */
#[Fillable(['supplier_id', 'article_no', 'name', 'price_net', 'package_unit', 'active', 'notes'])]
class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'price_net' => 'decimal:2',
            'active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}

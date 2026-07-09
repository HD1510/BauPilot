<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\HasNormalizedName;
use App\Models\Concerns\TracksUserStamps;
use Carbon\CarbonImmutable;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Kundenstamm (Architekturblatt 4.3). Archivieren = Soft-Delete.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $normalized_name
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $vat_id
 * @property int $payment_target_days
 * @property string|null $external_ref
 * @property string|null $notes
 * @property CarbonImmutable|null $deleted_at
 * @property int $lock_version
 */
#[Fillable(['name', 'address', 'phone', 'email', 'vat_id', 'payment_target_days', 'external_ref', 'notes', 'source_ref'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, HasNormalizedName, SoftDeletes, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'payment_target_days' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    /** @return HasMany<CustomerContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }
}

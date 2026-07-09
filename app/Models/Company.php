<?php

namespace App\Models;

use App\Enums\CompanyRole;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use Carbon\CarbonImmutable;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $short_code
 * @property string $color
 * @property string|null $legal_form
 * @property string|null $address
 * @property string|null $vat_id
 * @property string|null $logo_path
 * @property int $fiscal_year_start_month
 * @property numeric-string|null $calc_hourly_rate
 * @property int $warranty_years
 * @property CarbonImmutable|null $archived_at
 * @property int $lock_version
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CompanyUser|null $pivot
 */
#[Fillable([
    'name', 'short_code', 'color', 'legal_form', 'address', 'vat_id',
    'fiscal_year_start_month', 'calc_hourly_rate', 'warranty_years',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'fiscal_year_start_month' => 'integer',
            'calc_hourly_rate' => 'decimal:2',
            'warranty_years' => 'integer',
            'archived_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsToMany<User, $this, CompanyUser> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(CompanyUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function roleOf(User $user): ?CompanyRole
    {
        return $this->users()->whereKey($user->id)->first()?->pivot?->role;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function archive(): void
    {
        $this->archived_at = now();
        $this->save();
    }

    public function unarchive(): void
    {
        $this->archived_at = null;
        $this->save();
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mitarbeiter — auch ohne Benutzerkonto (Architekturblatt 4.3).
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property numeric-string|null $overtime_rate
 * @property numeric-string|null $calc_hourly_rate
 * @property int|null $user_id
 * @property bool $active
 * @property string|null $notes
 * @property int $lock_version
 */
#[Fillable(['name', 'overtime_rate', 'calc_hourly_rate', 'user_id', 'active', 'notes'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'overtime_rate' => 'decimal:2',
            'calc_hourly_rate' => 'decimal:2',
            'active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return HasMany<OvertimeEntry, $this> */
    public function overtimeEntries(): HasMany
    {
        return $this->hasMany(OvertimeEntry::class);
    }

    /** @return HasMany<OvertimePayout, $this> */
    public function overtimePayouts(): HasMany
    {
        return $this->hasMany(OvertimePayout::class);
    }
}

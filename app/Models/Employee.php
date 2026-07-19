<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use App\Models\Contracts\HasDocuments;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Mitarbeiter — auch ohne Benutzerkonto (Architekturblatt 4.3).
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $address
 * @property Carbon|null $birth_date
 * @property Carbon|null $started_on
 * @property Carbon|null $ended_on
 * @property string|null $social_security_number
 * @property string|null $iban
 * @property numeric-string|null $overtime_rate
 * @property numeric-string|null $calc_hourly_rate
 * @property int|null $user_id
 * @property bool $active
 * @property string|null $notes
 * @property int $lock_version
 */
#[Fillable([
    'name',
    'address',
    'birth_date',
    'started_on',
    'ended_on',
    'social_security_number',
    'iban',
    'overtime_rate',
    'calc_hourly_rate',
    'user_id',
    'active',
    'notes',
])]
class Employee extends Model implements HasDocuments
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'started_on' => 'date',
            'ended_on' => 'date',
            'overtime_rate' => 'decimal:2',
            'calc_hourly_rate' => 'decimal:2',
            'active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
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

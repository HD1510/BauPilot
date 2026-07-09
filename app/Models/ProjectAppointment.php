<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectAppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Projekttermin (Architekturblatt 4.3).
 *
 * @property int $id
 * @property int $company_id
 * @property int $project_id
 * @property CarbonImmutable $on_date
 * @property string $label
 * @property int $lock_version
 */
#[Fillable(['on_date', 'label'])]
class ProjectAppointment extends Model
{
    /** @use HasFactory<ProjectAppointmentFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'on_date' => 'date',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

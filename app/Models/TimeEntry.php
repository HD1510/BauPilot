<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksUserStamps;
use Carbon\CarbonImmutable;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Projektstunden (Architekturblatt 4.2, M8): Schnellerfassung von der
 * Baustelle, offline-fähig über client_uuid. Bewertet werden sie in den
 * Projektzahlen mit dem kalkulatorischen Stundensatz (ProjectFigures).
 *
 * @property int $id
 * @property int $company_id
 * @property int $project_id
 * @property int $employee_id
 * @property CarbonImmutable $work_date
 * @property numeric-string $hours
 * @property string|null $activity
 * @property string|null $client_uuid
 * @property int|null $created_by
 */
#[Fillable(['project_id', 'employee_id', 'work_date', 'hours', 'activity', 'client_uuid'])]
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use BelongsToCompany, HasFactory, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'hours' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

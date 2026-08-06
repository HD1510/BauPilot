<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksUserStamps;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Einteilung: Wer ist an welchem Tag auf welcher Baustelle, mit
 * welchem Fahrzeug. Die Baustelle ist ein Projekt oder Freitext.
 *
 * @property int $id
 * @property int $company_id
 * @property Carbon $work_date
 * @property int|null $project_id
 * @property string|null $site
 * @property string|null $notes
 * @property int $color
 * @property int $position
 */
#[Fillable(['work_date', 'project_id', 'site', 'notes', 'color', 'position'])]
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use BelongsToCompany, HasFactory, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'color' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsToMany<Employee, $this> */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class);
    }

    /** @return BelongsToMany<Vehicle, $this> */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class);
    }

    /**
     * Anzeigename: immer die Baustelle — der Freitext, sonst die
     * Baustellenadresse des Projekts, erst zuletzt der Projekttitel.
     */
    public function label(): string
    {
        return $this->site
            ?? $this->project->site_address
            ?? (string) $this->project?->title;
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksUserStamps;
use Database\Factories\OvertimeEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Überstunden je Mitarbeiter und Monat (Architekturblatt 4.2, 1A):
 * ein Eintrag je Monat, negative Stunden bilden Abbau ab. Der Saldo
 * wird nie gespeichert, sondern immer gerechnet (OvertimeBalance).
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property int $year
 * @property int $month
 * @property numeric-string $hours
 * @property string|null $note
 */
#[Fillable(['employee_id', 'year', 'month', 'hours', 'note'])]
class OvertimeEntry extends Model
{
    /** @use HasFactory<OvertimeEntryFactory> */
    use BelongsToCompany, HasFactory, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'hours' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

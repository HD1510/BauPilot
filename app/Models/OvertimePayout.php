<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksUserStamps;
use Carbon\CarbonImmutable;
use Database\Factories\OvertimePayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Überstunden-Auszahlung (Architekturblatt 4.2, 1A): zieht Stunden vom
 * Saldo ab und hält den ausgezahlten Betrag fest.
 *
 * @property int $id
 * @property int $company_id
 * @property int $employee_id
 * @property CarbonImmutable $paid_on
 * @property numeric-string $hours
 * @property numeric-string $amount
 */
#[Fillable(['employee_id', 'paid_on', 'hours', 'amount'])]
class OvertimePayout extends Model
{
    /** @use HasFactory<OvertimePayoutFactory> */
    use BelongsToCompany, HasFactory, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'hours' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

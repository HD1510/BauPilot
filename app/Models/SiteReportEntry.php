<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksUserStamps;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stunden-Zeile eines Regieberichts (Architekturblatt 4.2).
 *
 * @property int $id
 * @property int $company_id
 * @property int $site_report_id
 * @property int $employee_id
 * @property numeric-string $hours
 */
#[Fillable(['employee_id', 'hours'])]
class SiteReportEntry extends Model
{
    use BelongsToCompany, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'hours' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<SiteReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(SiteReport::class, 'site_report_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

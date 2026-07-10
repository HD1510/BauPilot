<?php

namespace App\Models;

use App\Enums\SiteReportStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksUserStamps;
use App\Models\Contracts\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\SiteReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Regiebericht (Architekturblatt 4.2, M9): fortlaufende Nummer je Firma,
 * nach der Unterschrift gesperrt — ein unterschriebener Bericht ist ein
 * Beleg und wird nie mehr verändert oder gelöscht.
 *
 * @property int $id
 * @property int $company_id
 * @property int $project_id
 * @property int $number
 * @property CarbonImmutable $report_date
 * @property SiteReportStatus $status
 * @property string|null $body_text
 * @property string|null $material_text
 * @property string|null $signature_path
 * @property CarbonImmutable|null $signed_at
 * @property string|null $client_uuid
 * @property int|null $created_by
 */
#[Fillable(['project_id', 'report_date', 'body_text', 'material_text', 'client_uuid'])]
class SiteReport extends Model implements HasDocuments
{
    /** @use HasFactory<SiteReportFactory> */
    use BelongsToCompany, HasFactory, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'report_date' => 'date',
            'status' => SiteReportStatus::class,
            'signed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<SiteReportEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(SiteReportEntry::class);
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function isSigned(): bool
    {
        return $this->status === SiteReportStatus::Signed;
    }
}

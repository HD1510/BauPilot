<?php

namespace App\Models;

use App\Enums\ExternalOfferStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use App\Models\Contracts\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\ExternalOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Fremdangebot eines Lieferanten zu einem Projekt (Architekturblatt 4.3).
 *
 * @property int $id
 * @property int $company_id
 * @property int $project_id
 * @property int $supplier_id
 * @property string $title
 * @property numeric-string|null $amount_net
 * @property CarbonImmutable|null $received_on
 * @property CarbonImmutable|null $valid_until
 * @property ExternalOfferStatus $status
 * @property string|null $notes
 * @property int $lock_version
 */
#[Fillable(['supplier_id', 'title', 'amount_net', 'received_on', 'valid_until', 'status', 'notes'])]
class ExternalOffer extends Model implements HasDocuments
{
    /** @use HasFactory<ExternalOfferFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'amount_net' => 'decimal:2',
            'received_on' => 'date',
            'valid_until' => 'date',
            'status' => ExternalOfferStatus::class,
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}

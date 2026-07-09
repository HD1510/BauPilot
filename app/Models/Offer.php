<?php

namespace App\Models;

use App\Enums\OfferStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use App\Models\Contracts\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Angebot an einen Kunden (Architekturblatt 4.3).
 *
 * @property int $id
 * @property int $company_id
 * @property int $customer_id
 * @property string|null $location
 * @property string|null $description
 * @property OfferStatus $status
 * @property CarbonImmutable|null $viewing_on
 * @property CarbonImmutable|null $follow_up_on
 * @property string|null $offer_number
 * @property numeric-string|null $offer_amount_net
 * @property int|null $project_id
 * @property string|null $notes
 * @property int $lock_version
 */
#[Fillable([
    'customer_id', 'location', 'description', 'status', 'viewing_on',
    'follow_up_on', 'offer_number', 'offer_amount_net', 'notes',
])]
class Offer extends Model implements HasDocuments
{
    /** @use HasFactory<OfferFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'viewing_on' => 'date',
            'follow_up_on' => 'date',
            'offer_amount_net' => 'decimal:2',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}

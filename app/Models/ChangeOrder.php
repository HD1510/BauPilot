<?php

namespace App\Models;

use App\Enums\ChangeOrderStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use App\Models\Contracts\HasDocuments;
use Database\Factories\ChangeOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Nachtrag zu einem Projekt (Architekturblatt 4.3).
 *
 * @property int $id
 * @property int $company_id
 * @property int $project_id
 * @property string $title
 * @property string|null $description
 * @property numeric-string|null $amount_net
 * @property ChangeOrderStatus $status
 * @property int|null $outgoing_invoice_id
 * @property int $lock_version
 */
#[Fillable(['title', 'description', 'amount_net', 'status', 'outgoing_invoice_id'])]
class ChangeOrder extends Model implements HasDocuments
{
    /** @use HasFactory<ChangeOrderFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'amount_net' => 'decimal:2',
            'status' => ChangeOrderStatus::class,
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<OutgoingInvoice, $this> */
    public function outgoingInvoice(): BelongsTo
    {
        return $this->belongsTo(OutgoingInvoice::class);
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}

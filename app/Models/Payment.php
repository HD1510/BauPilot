<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zahlungseingang; mit gesetzter retention_id die Freigabe genau dieses
 * Einbehalts (Architekturblatt v1.1).
 *
 * @property int $id
 * @property int $company_id
 * @property int $outgoing_invoice_id
 * @property CarbonImmutable $paid_on
 * @property numeric-string $amount
 * @property int|null $retention_id
 * @property int $lock_version
 */
#[Fillable(['paid_on', 'amount', 'retention_id'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'decimal:2',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<OutgoingInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(OutgoingInvoice::class, 'outgoing_invoice_id');
    }

    /** @return BelongsTo<Retention, $this> */
    public function retention(): BelongsTo
    {
        return $this->belongsTo(Retention::class);
    }
}

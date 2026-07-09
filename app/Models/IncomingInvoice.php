<?php

namespace App\Models;

use App\Enums\IncomingPaymentStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use App\Models\Contracts\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\IncomingInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Eingangsrechnung (Architekturblatt 4.3, v1.1: paid_amount und partial).
 *
 * @property int $id
 * @property int $company_id
 * @property int $supplier_id
 * @property string|null $supplier_invoice_no
 * @property CarbonImmutable $invoice_date
 * @property bool $date_estimated
 * @property numeric-string $net
 * @property numeric-string $vat_rate
 * @property numeric-string $vat
 * @property numeric-string $gross
 * @property bool $reverse_charge
 * @property int $cost_type_id
 * @property int|null $project_id
 * @property string|null $payment_method
 * @property CarbonImmutable|null $payment_due_on
 * @property numeric-string|null $skonto_amount
 * @property CarbonImmutable|null $skonto_until
 * @property IncomingPaymentStatus $payment_status
 * @property CarbonImmutable|null $paid_on
 * @property numeric-string|null $paid_amount
 * @property bool $checked
 * @property string|null $subject
 * @property string|null $notes
 * @property int $lock_version
 */
#[Fillable([
    'supplier_id', 'supplier_invoice_no', 'invoice_date', 'date_estimated',
    'net', 'vat_rate', 'vat', 'gross', 'reverse_charge', 'cost_type_id',
    'project_id', 'payment_method', 'payment_due_on', 'skonto_amount',
    'skonto_until', 'subject', 'notes',
])]
class IncomingInvoice extends Model implements HasDocuments
{
    /** @use HasFactory<IncomingInvoiceFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'date_estimated' => 'boolean',
            'net' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat' => 'decimal:2',
            'gross' => 'decimal:2',
            'reverse_charge' => 'boolean',
            'payment_due_on' => 'date',
            'skonto_amount' => 'decimal:2',
            'skonto_until' => 'date',
            'payment_status' => IncomingPaymentStatus::class,
            'paid_on' => 'date',
            'paid_amount' => 'decimal:2',
            'checked' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<CostType, $this> */
    public function costType(): BelongsTo
    {
        return $this->belongsTo(CostType::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return MorphMany<Retention, $this> */
    public function retentions(): MorphMany
    {
        return $this->morphMany(Retention::class, 'retainable');
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}

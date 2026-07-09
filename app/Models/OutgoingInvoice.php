<?php

namespace App\Models;

use App\Enums\InvoiceDocType;
use App\Enums\OutgoingPaymentStatus;
use App\Enums\ZeroRateReason;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use App\Models\Contracts\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\OutgoingInvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Ausgangsrechnung (Architekturblatt 4.3, Konventionen v1.1):
 * Schlussrechnungen tragen nur den Restbetrag; Gutschrift/Storno werden
 * negativ gespeichert und hängen über original_invoice_id an ihrer
 * Originalrechnung.
 *
 * @property int $id
 * @property int $company_id
 * @property InvoiceDocType $doc_type
 * @property string $number
 * @property CarbonImmutable $invoice_date
 * @property CarbonImmutable $due_on
 * @property int $customer_id
 * @property int|null $project_id
 * @property int|null $original_invoice_id
 * @property int|null $final_invoice_id
 * @property numeric-string $net
 * @property numeric-string $vat_rate
 * @property numeric-string $vat
 * @property numeric-string $gross
 * @property ZeroRateReason|null $zero_rate_reason
 * @property OutgoingPaymentStatus $payment_status
 * @property string|null $notes
 * @property int $lock_version
 */
#[Fillable([
    'doc_type', 'number', 'invoice_date', 'due_on', 'customer_id', 'project_id',
    'original_invoice_id', 'final_invoice_id', 'net', 'vat_rate', 'vat', 'gross',
    'zero_rate_reason', 'notes', 'source_ref',
])]
class OutgoingInvoice extends Model implements HasDocuments
{
    /** @use HasFactory<OutgoingInvoiceFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'doc_type' => InvoiceDocType::class,
            'invoice_date' => 'date',
            'due_on' => 'date',
            'net' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat' => 'decimal:2',
            'gross' => 'decimal:2',
            'zero_rate_reason' => ZeroRateReason::class,
            'payment_status' => OutgoingPaymentStatus::class,
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

    /** @return BelongsTo<OutgoingInvoice, $this> */
    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_invoice_id');
    }

    /**
     * Gutschriften und Storni auf diese Rechnung.
     *
     * @return HasMany<OutgoingInvoice, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(self::class, 'original_invoice_id');
    }

    /**
     * Teilrechnungen, die diese Schlussrechnung zusammenfasst.
     *
     * @return HasMany<OutgoingInvoice, $this>
     */
    public function partials(): HasMany
    {
        return $this->hasMany(self::class, 'final_invoice_id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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

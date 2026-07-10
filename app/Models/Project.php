<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use App\Models\Contracts\HasDocuments;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Projekt — die Drehscheibe (Architekturblatt 4.3).
 *
 * @property int $id
 * @property int $company_id
 * @property int $customer_id
 * @property string $title
 * @property string|null $site_address
 * @property string|null $description
 * @property int|null $responsible_user_id
 * @property CarbonImmutable|null $commissioned_on
 * @property CarbonImmutable|null $started_on
 * @property CarbonImmutable|null $planned_finish_on
 * @property CarbonImmutable|null $finished_on
 * @property ProjectStatus $status
 * @property CarbonImmutable|null $warranty_until
 * @property string|null $notes
 * @property int $lock_version
 */
#[Fillable([
    'customer_id', 'title', 'site_address', 'description', 'responsible_user_id',
    'commissioned_on', 'started_on', 'planned_finish_on', 'finished_on',
    'status', 'warranty_until', 'notes',
])]
class Project extends Model implements HasDocuments
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'commissioned_on' => 'date',
            'started_on' => 'date',
            'planned_finish_on' => 'date',
            'finished_on' => 'date',
            'warranty_until' => 'date',
            'status' => ProjectStatus::class,
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return HasMany<ProjectAppointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(ProjectAppointment::class);
    }

    /** @return HasMany<ChangeOrder, $this> */
    public function changeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class);
    }

    /** @return HasMany<ExternalOffer, $this> */
    public function externalOffers(): HasMany
    {
        return $this->hasMany(ExternalOffer::class);
    }

    /** @return HasMany<OutgoingInvoice, $this> */
    public function outgoingInvoices(): HasMany
    {
        return $this->hasMany(OutgoingInvoice::class);
    }

    /** @return HasMany<IncomingInvoice, $this> */
    public function incomingInvoices(): HasMany
    {
        return $this->hasMany(IncomingInvoice::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return HasMany<ProjectNote, $this> */
    public function projectNotes(): HasMany
    {
        return $this->hasMany(ProjectNote::class);
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}

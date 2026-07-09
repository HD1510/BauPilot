<?php

namespace App\Models;

use App\Enums\RetentionKind;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use Carbon\CarbonImmutable;
use Database\Factories\RetentionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Einbehalt (Haft-/Deckungsrücklass), polymorph an Aus- und
 * Eingangsrechnungen (Architekturblatt v1.1).
 *
 * @property int $id
 * @property int $company_id
 * @property string $retainable_type
 * @property int $retainable_id
 * @property RetentionKind $kind
 * @property numeric-string|null $percent
 * @property numeric-string $amount
 * @property CarbonImmutable $due_on
 * @property string|null $note
 * @property CarbonImmutable|null $received_at
 * @property int $lock_version
 */
#[Fillable(['kind', 'percent', 'amount', 'due_on', 'note'])]
class Retention extends Model
{
    /** @use HasFactory<RetentionFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'kind' => RetentionKind::class,
            'percent' => 'decimal:2',
            'amount' => 'decimal:2',
            'due_on' => 'date',
            'received_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function retainable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isReceived(): bool
    {
        return $this->received_at !== null;
    }

    /**
     * Offen = noch nicht eingegangen; nur offene Einbehalte mit due_on in
     * der Zukunft mindern den jetzt fälligen Betrag (Architekturblatt 5).
     */
    public function reducesDueNow(CarbonImmutable $today): bool
    {
        return ! $this->isReceived() && $this->due_on->greaterThan($today);
    }
}

<?php

namespace App\Models;

use App\Enums\TaskKind;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aufgabe oder Mangel am Projekt (Architekturblatt 4.2, M7). Die
 * Erledigung ist zustands-idempotent: done_at wiederholt zu setzen
 * ändert nichts (Abschnitt 9).
 *
 * @property int $id
 * @property int $company_id
 * @property int $project_id
 * @property TaskKind $kind
 * @property string $title
 * @property string|null $description
 * @property CarbonImmutable|null $due_on
 * @property int|null $assignee_user_id
 * @property CarbonImmutable|null $done_at
 * @property int|null $done_by
 * @property string|null $client_uuid
 * @property int $lock_version
 */
#[Fillable(['project_id', 'kind', 'title', 'description', 'due_on', 'assignee_user_id', 'client_uuid'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'kind' => TaskKind::class,
            'due_on' => 'date',
            'done_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }

    /**
     * Zustands-idempotent: Wiederholtes Erledigen ändert nichts.
     */
    public function markDone(User $user): void
    {
        if ($this->isDone()) {
            return;
        }

        $this->forceFill(['done_at' => now(), 'done_by' => $user->id])->save();
    }

    public function reopen(): void
    {
        if (! $this->isDone()) {
            return;
        }

        $this->forceFill(['done_at' => null, 'done_by' => null])->save();
    }
}

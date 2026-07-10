<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksUserStamps;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kurze Projekt-Notiz von der Baustelle (Architekturblatt 4.2/9, v1.1).
 * client_uuid macht das Anlegen über den JSON-Endpunkt idempotent.
 *
 * @property int $id
 * @property int $company_id
 * @property int $project_id
 * @property string $body
 * @property string|null $client_uuid
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['project_id', 'body', 'client_uuid'])]
class ProjectNote extends Model
{
    /** @use HasFactory<ProjectNoteFactory> */
    use BelongsToCompany, HasFactory, TracksUserStamps;

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

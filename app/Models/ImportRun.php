<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ImportRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Import-Lauf (Architekturblatt Abschnitt 8): Phase 1 (dry_run)
 * schreibt nichts Fachliches, Phase 2 (committed) transaktional je Bereich.
 *
 * @property int $id
 * @property int $company_id
 * @property string $source_filename
 * @property string $path
 * @property string $status
 * @property array<string, mixed> $stats
 * @property int|null $created_by
 */
#[Fillable(['source_filename', 'path', 'status', 'stats'])]
class ImportRun extends Model
{
    /** @use HasFactory<ImportRunFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'stats' => 'array',
        ];
    }

    /** @return HasMany<ImportFinding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(ImportFinding::class);
    }

    public function isCommitted(): bool
    {
        return $this->status === 'committed';
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prüfbericht-Eintrag eines Import-Laufs: Dubletten, geschätzte
 * Datumsangaben, unplausible Termine — alles, was Bestätigung braucht.
 *
 * @property int $id
 * @property int $company_id
 * @property int $import_run_id
 * @property string $type
 * @property string $message
 * @property array<string, mixed> $payload
 * @property string|null $decision
 */
#[Fillable(['type', 'message', 'payload', 'decision'])]
class ImportFinding extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<ImportRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }

    public function needsDecision(): bool
    {
        return $this->type === 'duplicate' && $this->decision === null;
    }
}

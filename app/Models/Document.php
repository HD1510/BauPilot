<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\TracksUserStamps;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Datei-Anhang, polymorph (Architekturblatt 4.3/6). client_uuid macht den
 * Upload idempotent (v1.1, Vorbereitung Offline-Puffer).
 *
 * @property int $id
 * @property int $company_id
 * @property string $documentable_type
 * @property int $documentable_id
 * @property DocumentCategory $category
 * @property string $original_name
 * @property string $path
 * @property int $size
 * @property string $mime
 * @property string|null $client_uuid
 */
#[Fillable(['category', 'original_name', 'path', 'size', 'mime', 'client_uuid'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToCompany, HasFactory, TracksUserStamps;

    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'size' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}

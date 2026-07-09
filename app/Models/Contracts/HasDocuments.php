<?php

namespace App\Models\Contracts;

use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Modelle, an denen Datei-Anhänge hängen (Architekturblatt 4.3, documents).
 */
interface HasDocuments
{
    /** @return MorphMany<Document, covariant \Illuminate\Database\Eloquent\Model> */
    public function documents(): MorphMany;
}

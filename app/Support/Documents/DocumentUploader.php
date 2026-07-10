<?php

namespace App\Support\Documents;

use App\Enums\DocumentCategory;
use App\Jobs\ResizeDocumentImage;
use App\Models\Contracts\HasDocuments;
use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gemeinsamer Upload-Weg für Web und JSON-API (Architekturblatt 6/9):
 * Schlüsselschema {env}/{company_id}/{model}/{id}/{uuid}-{name},
 * Idempotenz über client_uuid, Bildverkleinerung per Queue-Job.
 */
class DocumentUploader
{
    /**
     * Bereits hochgeladen? (Idempotenz über client_uuid, v1.1)
     */
    public function findExisting(?string $clientUuid): ?Document
    {
        if ($clientUuid === null || $clientUuid === '') {
            return null;
        }

        return Document::query()->where('client_uuid', $clientUuid)->first();
    }

    public function upload(
        Model&HasDocuments $parent,
        string $morphAlias,
        UploadedFile $file,
        DocumentCategory $category,
        ?string $clientUuid = null,
    ): Document {
        $path = sprintf(
            '%s/%d/%s/%d/%s-%s',
            app()->environment(),
            $parent->getAttribute('company_id'),
            $morphAlias,
            $parent->getKey(),
            Str::uuid(),
            Str::limit(preg_replace('/[^\w.\-]+/', '_', $file->getClientOriginalName()) ?? 'datei', 100, ''),
        );

        Storage::disk('documents')->putFileAs(dirname($path), $file, basename($path));

        /** @var Document $document */
        $document = $parent->documents()->create([
            'category' => $category,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'client_uuid' => $clientUuid,
        ]);

        // Fotos per Queue auf 2560px verkleinern (Abschnitt 6).
        ResizeDocumentImage::dispatch($document->company_id, $document->id);

        return $document;
    }
}

<?php

namespace App\Support\InvoiceScan;

use App\Enums\DocumentCategory;
use App\Models\Contracts\HasDocuments;
use App\Support\Documents\DocumentUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Hängt die beim Scan beiseitegelegte Datei nach dem Speichern als
 * Beleg an. Der Pfad enthält die Firmen-ID des Belegs — fremde oder
 * abgelaufene Tokens laufen still ins Leere.
 */
class ScannedFileAttacher
{
    public function __construct(private DocumentUploader $uploader) {}

    public function attach(Model&HasDocuments $parent, string $morphAlias, ?string $token, DocumentCategory $category): void
    {
        if (! is_string($token) || $token === '') {
            return;
        }

        $directory = sprintf('%s/%d/scan/%s', app()->environment(), $parent->getAttribute('company_id'), $token);
        $files = Storage::disk('documents')->files($directory);

        if ($files === []) {
            return;
        }

        $this->uploader->upload(
            $parent,
            $morphAlias,
            new UploadedFile(
                Storage::disk('documents')->path($files[0]),
                basename($files[0]),
                Storage::disk('documents')->mimeType($files[0]) ?: 'application/octet-stream',
                null,
                true,
            ),
            $category,
        );

        Storage::disk('documents')->deleteDirectory($directory);
    }
}

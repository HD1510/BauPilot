<?php

namespace App\Http\Controllers\Documents;

use App\Enums\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Models\Contracts\HasDocuments;
use App\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Datei-Anhänge (Architekturblatt Abschnitt 6): privat gespeichert,
 * Schlüsselschema {env}/{company_id}/{model}/{id}/{uuid}-{name}, Download
 * nur nach Policy-Prüfung. Uploads laufen in 1A durch den Server.
 */
class DocumentController extends Controller
{
    private const ALLOWED_PARENTS = ['offer', 'project', 'change_order', 'external_offer', 'outgoing_invoice', 'incoming_invoice'];

    private const MAX_FILE_KB = 25 * 1024; // 25 MB (Architekturblatt 6)

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'documentable_type' => ['required', Rule::in(self::ALLOWED_PARENTS)],
            'documentable_id' => ['required', 'integer'],
            'category' => ['required', Rule::enum(DocumentCategory::class)],
            'file' => ['required', 'file', 'max:'.self::MAX_FILE_KB],
            'client_uuid' => ['nullable', 'uuid'],
        ], [], ['file' => 'Datei', 'category' => 'Kategorie']);

        $parent = $this->resolveParent($validated['documentable_type'], (int) $validated['documentable_id']);

        Gate::authorize('update', $parent);

        // Idempotenz über client_uuid (v1.1): Wiederholung ist erledigt.
        if (! empty($validated['client_uuid'])) {
            $existing = Document::query()->where('client_uuid', $validated['client_uuid'])->first();

            if ($existing !== null) {
                return back()->with('success', 'Datei ist bereits hochgeladen.');
            }
        }

        $file = $request->file('file');
        $path = sprintf(
            '%s/%d/%s/%d/%s-%s',
            app()->environment(),
            $parent->getAttribute('company_id'),
            $validated['documentable_type'],
            $parent->getKey(),
            Str::uuid(),
            Str::limit(preg_replace('/[^\w.\-]+/', '_', $file->getClientOriginalName()) ?? 'datei', 100, ''),
        );

        Storage::disk('documents')->putFileAs(dirname($path), $file, basename($path));

        $parent->documents()->create([
            'category' => $validated['category'],
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'mime' => $file->getMimeType() ?? 'application/octet-stream',
            'client_uuid' => $validated['client_uuid'] ?? null,
        ]);

        return back()->with('success', "Datei „{$file->getClientOriginalName()}“ hochgeladen.");
    }

    public function download(Document $document): Response
    {
        Gate::authorize('view', $document);

        $disk = Storage::disk('documents');

        // In Produktion (S3): kurzlebige signierte URL (Architekturblatt 6).
        if (config('filesystems.disks.documents.driver') === 's3') {
            return redirect()->away($disk->temporaryUrl($document->path, now()->addMinutes(15), [
                'ResponseContentDisposition' => 'attachment; filename="'.addslashes($document->original_name).'"',
            ]));
        }

        return $disk->download($document->path, $document->original_name);
    }

    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        Storage::disk('documents')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Datei gelöscht.');
    }

    private function resolveParent(string $type, int $id): Model&HasDocuments
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = Relation::getMorphedModel($type) ?? abort(404);

        // Global Scope begrenzt auf die aktive Firma — fremde IDs sind 404.
        $parent = $modelClass::query()->whereKey($id)->firstOrFail();

        abort_unless($parent instanceof HasDocuments, 404);

        return $parent;
    }
}

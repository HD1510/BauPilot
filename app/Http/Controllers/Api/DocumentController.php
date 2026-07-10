<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\Documents\DocumentUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Foto-Upload von der Baustelle (Architekturblatt Abschnitte 6 und 9):
 * idempotent über client_uuid, Ziel ist immer ein Projekt — das ist der
 * Weg, über den „Fotos vom Handy in Sekunden am richtigen Projekt
 * landen" (M7). Verkleinert wird per Queue-Job.
 */
class DocumentController extends Controller
{
    private const MAX_FILE_KB = 25 * 1024; // 25 MB (Architekturblatt 6)

    public function store(Request $request, DocumentUploader $uploader): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'category' => ['nullable', Rule::enum(DocumentCategory::class)],
            'file' => ['required', 'file', 'max:'.self::MAX_FILE_KB],
            'client_uuid' => ['required', 'uuid'],
        ], [], ['file' => 'Datei']);

        $existing = $uploader->findExisting($validated['client_uuid']);

        if ($existing !== null) {
            return response()->json(['id' => $existing->id, 'status' => 'exists'], 200);
        }

        $project = Project::query()->whereKey((int) $validated['project_id'])->firstOrFail();

        Gate::authorize('attach', $project);

        $document = $uploader->upload(
            $project,
            'project',
            $request->file('file'),
            isset($validated['category']) ? DocumentCategory::from($validated['category']) : DocumentCategory::Photo,
            $validated['client_uuid'],
        );

        return response()->json(['id' => $document->id, 'status' => 'created'], 201);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Notizen-Endpunkt (Architekturblatt Abschnitt 9): anlegend, daher
 * idempotent über (company_id, client_uuid).
 */
class ProjectNoteController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ProjectNote::class);

        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:5000'],
            'client_uuid' => ['nullable', 'uuid'],
        ]);

        if (! empty($validated['client_uuid'])) {
            $existing = ProjectNote::query()->where('client_uuid', $validated['client_uuid'])->first();

            if ($existing !== null) {
                return response()->json(['id' => $existing->id, 'status' => 'exists'], 200);
            }
        }

        $project = Project::query()->whereKey((int) $validated['project_id'])->firstOrFail();

        $note = ProjectNote::create([
            'project_id' => $project->id,
            'body' => $validated['body'],
            'client_uuid' => $validated['client_uuid'] ?? null,
        ]);

        return response()->json(['id' => $note->id, 'status' => 'created'], 201);
    }
}

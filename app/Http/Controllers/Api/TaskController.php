<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskKind;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * JSON-Endpunkte für Aufgaben (Architekturblatt Abschnitt 9): Anlegen
 * idempotent über (company_id, client_uuid), Erledigung zustands-
 * idempotent. Den Firmenkontext setzt SetCompanyFromRequest aus der
 * expliziten company_id des Requests.
 */
class TaskController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Task::class);

        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'kind' => ['required', Rule::enum(TaskKind::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_on' => ['nullable', 'date'],
            'assignee_user_id' => ['nullable', 'integer'],
            'client_uuid' => ['nullable', 'uuid'],
        ]);

        // Idempotenz: dieselbe client_uuid ist bereits erledigt.
        if (! empty($validated['client_uuid'])) {
            $existing = Task::query()->where('client_uuid', $validated['client_uuid'])->first();

            if ($existing !== null) {
                return response()->json(['id' => $existing->id, 'status' => 'exists'], 200);
            }
        }

        // Global Scope: fremde Projekte sind hier gar nie sichtbar.
        $project = Project::query()->whereKey((int) $validated['project_id'])->firstOrFail();

        $task = Task::create([
            'project_id' => $project->id,
            'kind' => $validated['kind'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'due_on' => $validated['due_on'] ?? null,
            'assignee_user_id' => $validated['assignee_user_id'] ?? null,
            'client_uuid' => $validated['client_uuid'] ?? null,
        ]);

        return response()->json(['id' => $task->id, 'status' => 'created'], 201);
    }

    /**
     * Zustands-idempotent (Abschnitt 9): done_at wiederholt zu setzen
     * ändert nichts — die Wiederholung aus dem Offline-Puffer ist ok.
     */
    public function complete(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('update', $task);

        $task->markDone($request->user());

        return response()->json([
            'id' => $task->id,
            'status' => 'done',
            'done_at' => $task->done_at?->toIso8601String(),
        ]);
    }
}

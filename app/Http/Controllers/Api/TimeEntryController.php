<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Zeiten-Endpunkt (Architekturblatt Abschnitt 9): anlegend, daher
 * verpflichtend idempotent über (company_id, client_uuid) — Zeiten sind
 * die klassische Funkloch-Erfassung.
 */
class TimeEntryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', TimeEntry::class);

        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'employee_id' => ['required', 'integer'],
            'work_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'activity' => ['nullable', 'string', 'max:255'],
            'client_uuid' => ['required', 'uuid'],
        ]);

        $existing = TimeEntry::query()->where('client_uuid', $validated['client_uuid'])->first();

        if ($existing !== null) {
            return response()->json(['id' => $existing->id, 'status' => 'exists'], 200);
        }

        Project::query()->whereKey((int) $validated['project_id'])->firstOrFail();
        Employee::query()->whereKey((int) $validated['employee_id'])->firstOrFail();

        $entry = TimeEntry::create($validated);

        return response()->json(['id' => $entry->id, 'status' => 'created'], 201);
    }
}

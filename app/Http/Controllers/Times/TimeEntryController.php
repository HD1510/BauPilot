<?php

namespace App\Http\Controllers\Times;

use App\Enums\CompanyRole;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Zeiterfassung je Projekt (M8): Schnellerfassung — Datum vorbelegt,
 * Mitarbeiter, Projekt, Stunden, fertig. Bewertete Zahlen (Lohnkosten)
 * tauchen hier bewusst NICHT auf; die stehen hinter dem Finanz-Gate in
 * den Projektzahlen.
 */
class TimeEntryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TimeEntry::class);

        /** @var User $user */
        $user = $request->user();
        $canModerate = in_array($user->currentRole(), [CompanyRole::Admin, CompanyRole::Office], true);

        $entries = TimeEntry::query()
            ->with(['project:id,title', 'employee:id,name'])
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->map(fn (TimeEntry $entry): array => [
                'id' => $entry->id,
                'work_date' => $entry->work_date->toDateString(),
                'hours' => (float) $entry->hours,
                'activity' => $entry->activity,
                'project' => $entry->project?->title,
                'employee' => $entry->employee?->name,
                'can_delete' => $canModerate || $entry->created_by === $user->id,
            ]);

        return Inertia::render('time-entries/index', [
            'entries' => $entries,
            'projects' => Project::query()->orderBy('title')->get(['id', 'title'])
                ->map(fn (Project $project): array => ['id' => $project->id, 'title' => $project->title]),
            'employees' => Employee::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'user_id'])
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'is_me' => $employee->user_id === $user->id,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', TimeEntry::class);

        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'employee_id' => ['required', 'integer'],
            'work_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'activity' => ['nullable', 'string', 'max:255'],
        ], [], ['hours' => 'Stunden', 'work_date' => 'Datum']);

        // Global Scope: fremde Projekte/Mitarbeiter sind gar nie sichtbar.
        Project::query()->whereKey((int) $validated['project_id'])->firstOrFail();
        Employee::query()->whereKey((int) $validated['employee_id'])->firstOrFail();

        TimeEntry::create($validated);

        return back()->with('success', "{$validated['hours']} Stunden erfasst.");
    }

    public function destroy(TimeEntry $timeEntry): RedirectResponse
    {
        Gate::authorize('delete', $timeEntry);

        $timeEntry->delete();

        return back()->with('success', 'Eintrag gelöscht.');
    }
}

<?php

namespace App\Http\Controllers\Projects;

use App\Enums\TaskKind;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aufgaben und Mängel (M7): am Projekt-Hub angelegt, firmenweit auf der
 * Aufgaben-Seite sichtbar. Erledigen heißt abhaken — die Frist dazu
 * verschwindet von selbst (Architekturblatt Abschnitt 5).
 */
class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Task::class);

        $kind = (string) $request->query('kind');
        $showDone = $request->boolean('done');
        $mine = $request->boolean('mine');

        $tasks = Task::query()
            ->with(['project:id,title', 'assignee:id,name'])
            ->when($kind !== '', fn ($query) => $query->where('kind', $kind))
            ->when(! $showDone, fn ($query) => $query->whereNull('done_at'))
            ->when($mine, fn ($query) => $query->where('assignee_user_id', $request->user()?->id))
            ->orderByRaw('done_at IS NOT NULL, due_on ASC NULLS LAST, id DESC')
            ->get()
            ->map(fn (Task $task): array => $this->taskArray($task));

        return Inertia::render('tasks/index', [
            'tasks' => $tasks,
            'filters' => ['kind' => $kind, 'done' => $showDone, 'mine' => $mine],
            'members' => $this->memberOptions(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('create', Task::class);

        $validated = $request->validate([
            'kind' => ['required', Rule::enum(TaskKind::class)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_on' => ['nullable', 'date'],
            'assignee_user_id' => ['nullable', 'integer'],
        ], [], ['title' => 'Titel', 'due_on' => 'Fälligkeit']);

        $project->tasks()->create($validated);

        return back()->with('success', $validated['kind'] === TaskKind::Defect->value
            ? 'Mangel erfasst.'
            : 'Aufgabe angelegt.');
    }

    public function complete(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->markDone($request->user());

        return back()->with('success', "„{$task->title}“ erledigt.");
    }

    public function reopen(Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->reopen();

        return back()->with('success', "„{$task->title}“ wieder geöffnet.");
    }

    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return back()->with('success', 'Eintrag gelöscht.');
    }

    /**
     * @return array<string, mixed>
     */
    private function taskArray(Task $task): array
    {
        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'project' => $task->project?->title,
            'kind' => $task->kind->value,
            'kind_label' => $task->kind->label(),
            'title' => $task->title,
            'description' => $task->description,
            'due_on' => $task->due_on?->toDateString(),
            'assignee' => $task->assignee?->name,
            'done_at' => $task->done_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function memberOptions(): array
    {
        return app(CompanyContext::class)->requireCompany()->users()->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->values()->all();
    }
}

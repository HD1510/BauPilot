<?php

namespace App\Http\Controllers\Times;

use App\Enums\CompanyRole;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Vacation;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Zeiterfassung je Projekt (M8): Schnellerfassung — Datum vorbelegt,
 * Mitarbeiter, Projekt, Stunden, fertig. Eine Partie lässt sich in einem
 * Zug erfassen oder direkt aus der Einteilung übernehmen. Bewertete
 * Zahlen (Lohnkosten) tauchen hier bewusst NICHT auf; die stehen hinter
 * dem Finanz-Gate in den Projektzahlen.
 */
class TimeEntryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TimeEntry::class);

        /** @var User $user */
        $user = $request->user();
        $canModerate = in_array($user->currentRole(), [CompanyRole::Admin, CompanyRole::Office], true);

        $date = CarbonImmutable::make($request->query('date')) ?? CarbonImmutable::today();
        $monday = $date->startOfWeek();

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
                'can_edit' => $canModerate || $entry->created_by === $user->id,
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
            'date' => $date->toDateString(),
            'plan' => $this->plan($date),
            'week' => $this->weekSummary($monday),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', TimeEntry::class);

        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            // Eine Partie in einem Zug: mehrere Mitarbeiter, je ein Eintrag.
            'employee_ids' => ['required_without:employee_id', 'array', 'min:1'],
            'employee_ids.*' => ['integer'],
            'employee_id' => ['required_without:employee_ids', 'integer'],
            'work_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'activity' => ['nullable', 'string', 'max:255'],
        ], [
            'employee_ids.required_without' => 'Bitte mindestens einen Mitarbeiter wählen.',
        ], ['hours' => 'Stunden', 'work_date' => 'Datum', 'employee_ids' => 'Mitarbeiter']);

        $employeeIds = array_values(array_unique(array_map(
            'intval',
            $validated['employee_ids'] ?? [$validated['employee_id']],
        )));

        // Global Scope: fremde Projekte/Mitarbeiter sind gar nie sichtbar.
        Project::query()->whereKey((int) $validated['project_id'])->firstOrFail();
        abort_unless(Employee::query()->whereKey($employeeIds)->count() === count($employeeIds), 404);

        DB::transaction(function () use ($validated, $employeeIds): void {
            foreach ($employeeIds as $employeeId) {
                TimeEntry::create([
                    'project_id' => $validated['project_id'],
                    'employee_id' => $employeeId,
                    'work_date' => $validated['work_date'],
                    'hours' => $validated['hours'],
                    'activity' => $validated['activity'] ?? null,
                ]);
            }
        });

        return back()->with('success', count($employeeIds) > 1
            ? "{$validated['hours']} Stunden für ".count($employeeIds).' Mitarbeiter erfasst.'
            : "{$validated['hours']} Stunden erfasst.");
    }

    public function update(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        Gate::authorize('update', $timeEntry);

        $validated = $request->validate([
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'activity' => ['nullable', 'string', 'max:255'],
        ], [], ['hours' => 'Stunden', 'activity' => 'Tätigkeit']);

        $timeEntry->update($validated);

        return back()->with('success', 'Eintrag gespeichert.');
    }

    /**
     * Zeiten aus der Einteilung eines Tages übernehmen: je eingeteiltem
     * Mitarbeiter ein Eintrag aufs Projekt. Wer an dem Tag schon Zeiten
     * auf dem Projekt hat oder im Urlaub ist, wird übersprungen — der
     * Knopf lässt sich also gefahrlos zweimal drücken.
     */
    public function fromAssignments(Request $request): RedirectResponse
    {
        Gate::authorize('create', TimeEntry::class);

        $validated = $request->validate([
            'work_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'assignment_ids' => ['nullable', 'array'],
            'assignment_ids.*' => ['integer'],
        ], [], ['hours' => 'Stunden', 'work_date' => 'Datum']);

        $date = CarbonImmutable::parse($validated['work_date']);
        $only = isset($validated['assignment_ids']) ? array_map('intval', $validated['assignment_ids']) : null;

        $rows = $this->plan($date)
            ->filter(fn (array $row): bool => $only === null || in_array($row['assignment_id'], $only, true));

        $created = 0;

        DB::transaction(function () use ($rows, $date, $validated, &$created): void {
            foreach ($rows as $row) {
                foreach ($row['employees'] as $employee) {
                    if ($employee['skip'] !== null) {
                        continue;
                    }

                    TimeEntry::create([
                        'project_id' => $row['project_id'],
                        'employee_id' => $employee['id'],
                        'work_date' => $date->toDateString(),
                        'hours' => $validated['hours'],
                    ]);
                    $created++;
                }
            }
        });

        return back()->with(
            $created > 0 ? 'success' : 'error',
            $created > 0
                ? "{$created} ".($created === 1 ? 'Eintrag' : 'Einträge').' aus der Einteilung übernommen.'
                : 'Nichts zu übernehmen — für diesen Tag ist alles schon erfasst oder nichts eingeteilt.',
        );
    }

    public function destroy(TimeEntry $timeEntry): RedirectResponse
    {
        Gate::authorize('delete', $timeEntry);

        $timeEntry->delete();

        return back()->with('success', 'Eintrag gelöscht.');
    }

    /**
     * Was die Einteilung für einen Tag hergibt: je Projekt-Einteilung die
     * Mitarbeiter, mit Grund, falls einer nicht übernommen wird.
     *
     * @return Collection<int, array{assignment_id: int, project_id: int, project: string, employees: list<array{id: int, name: string, skip: string|null}>}>
     */
    private function plan(CarbonImmutable $date): Collection
    {
        $day = $date->toDateString();

        $existing = TimeEntry::query()
            ->whereDate('work_date', $day)
            ->get(['project_id', 'employee_id'])
            ->map(fn (TimeEntry $entry): string => $entry->project_id.'|'.$entry->employee_id)
            ->all();

        $onVacation = Vacation::query()
            ->whereDate('starts_on', '<=', $day)
            ->whereDate('ends_on', '>=', $day)
            ->pluck('employee_id')
            ->all();

        $assignments = Assignment::query()
            ->whereDate('work_date', $day)
            ->whereNotNull('project_id')
            ->with(['project:id,title', 'employees:id,name'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($assignments as $assignment) {
            $employees = [];

            foreach ($assignment->employees as $employee) {
                $employees[] = [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'skip' => $this->skipReason(
                        in_array($employee->id, $onVacation, true),
                        in_array($assignment->project_id.'|'.$employee->id, $existing, true),
                    ),
                ];
            }

            $rows[] = [
                'assignment_id' => $assignment->id,
                'project_id' => (int) $assignment->project_id,
                'project' => (string) $assignment->project?->title,
                'employees' => $employees,
            ];
        }

        return collect($rows);
    }

    private function skipReason(bool $onVacation, bool $alreadyRecorded): ?string
    {
        if ($onVacation) {
            return 'Urlaub';
        }

        return $alreadyRecorded ? 'schon erfasst' : null;
    }

    /**
     * Wochenübersicht: Stunden je Mitarbeiter und Tag samt Summe.
     *
     * @return array{monday: string, previous: string, next: string, days: list<string>, rows: list<array{employee: string, hours: list<float>, total: float}>, total: float}
     */
    private function weekSummary(CarbonImmutable $monday): array
    {
        $days = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $days[] = $monday->addDays($offset)->toDateString();
        }

        $entries = TimeEntry::query()
            ->whereDate('work_date', '>=', $days[0])
            ->whereDate('work_date', '<=', $days[6])
            ->with('employee:id,name')
            ->get();

        $rows = [];

        foreach ($entries->groupBy('employee_id') as $group) {
            $hours = [];

            foreach ($days as $day) {
                $hours[] = round((float) $group
                    ->filter(fn (TimeEntry $entry): bool => $entry->work_date->toDateString() === $day)
                    ->sum(fn (TimeEntry $entry): float => (float) $entry->hours), 2);
            }

            $rows[] = [
                'employee' => (string) $group->first()?->employee?->name,
                'hours' => $hours,
                'total' => round(array_sum($hours), 2),
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['employee'], $b['employee']));

        return [
            'monday' => $monday->toDateString(),
            'previous' => $monday->subWeek()->toDateString(),
            'next' => $monday->addWeek()->toDateString(),
            'days' => $days,
            'rows' => $rows,
            'total' => round((float) array_sum(array_column($rows, 'total')), 2),
        ];
    }
}

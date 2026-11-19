<?php

namespace App\Support\Dashboard;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Vacation;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * „Mein Tag" — die Startseite für die Baustelle: Wo bin ich heute
 * eingeteilt, mit wem und welchem Fahrzeug, was habe ich an Stunden
 * erfasst und welche Aufgaben sind mir zugeteilt. Bewusst ohne jede
 * Finanzzahl.
 */
final class MyDay
{
    /**
     * @return array<string, mixed>
     */
    public function for(User $user, CarbonImmutable $today): array
    {
        // Global Scope: nur der Mitarbeiter der aktiven Firma.
        $employee = Employee::query()->where('user_id', $user->id)->first();

        $assignments = $employee === null ? collect() : $this->assignments($employee, $today);
        $entries = $employee === null ? collect() : $this->weekEntries($employee, $today);

        $todays = $assignments->filter(
            fn (Assignment $assignment): bool => $assignment->work_date->toDateString() === $today->toDateString(),
        );
        $next = $assignments->first(
            fn (Assignment $assignment): bool => $assignment->work_date->toDateString() > $today->toDateString(),
        );
        $nextDate = $next?->work_date->toDateString();

        $todaysEntries = $entries->filter(
            fn (TimeEntry $entry): bool => $entry->work_date->toDateString() === $today->toDateString(),
        );

        return [
            'today' => $today->toDateString(),
            'employee' => $employee === null ? null : ['id' => $employee->id, 'name' => $employee->name],
            'vacation' => $employee === null ? null : $this->vacation($employee, $today),
            'assignments' => $this->cards($todays, $employee, $todaysEntries),
            'next' => $nextDate === null ? null : [
                'date' => $nextDate,
                'assignments' => $this->cards(
                    $assignments->filter(fn (Assignment $assignment): bool => $assignment->work_date->toDateString() === $nextDate),
                    $employee,
                    collect(),
                ),
            ],
            'hours' => [
                'today' => round((float) $todaysEntries->sum(fn (TimeEntry $entry): float => (float) $entry->hours), 2),
                'week' => round((float) $entries->sum(fn (TimeEntry $entry): float => (float) $entry->hours), 2),
            ],
            'tasks' => $this->tasks($user, $today),
        ];
    }

    /**
     * Meine Einteilungen von heute bis in einer Woche.
     *
     * @return Collection<int, Assignment>
     */
    private function assignments(Employee $employee, CarbonImmutable $today): Collection
    {
        return Assignment::query()
            ->whereHas('employees', fn ($query) => $query->whereKey($employee->id))
            ->whereDate('work_date', '>=', $today->toDateString())
            ->whereDate('work_date', '<=', $today->addDays(7)->toDateString())
            ->with(['project:id,title,site_address', 'employees:id,name', 'vehicles:id,plate'])
            ->orderBy('work_date')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Meine Stunden dieser Woche (Montag bis Sonntag).
     *
     * @return Collection<int, TimeEntry>
     */
    private function weekEntries(Employee $employee, CarbonImmutable $today): Collection
    {
        return TimeEntry::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $today->startOfWeek()->toDateString())
            ->whereDate('work_date', '<=', $today->endOfWeek()->toDateString())
            ->get();
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     * @param  Collection<int, TimeEntry>  $todaysEntries
     * @return list<array<string, mixed>>
     */
    private function cards(Collection $assignments, ?Employee $employee, Collection $todaysEntries): array
    {
        $cards = [];

        foreach ($assignments as $assignment) {
            $cards[] = [
                'id' => $assignment->id,
                'label' => $assignment->label(),
                'project_id' => $assignment->project_id,
                'project' => $assignment->project?->title,
                'notes' => $assignment->notes,
                'color' => $assignment->color,
                // Die Kollegen — ohne mich selbst.
                'colleagues' => $assignment->employees
                    ->reject(fn (Employee $colleague): bool => $colleague->id === $employee?->id)
                    ->map(fn (Employee $colleague): string => $colleague->name)
                    ->values()
                    ->all(),
                'vehicles' => $assignment->vehicles
                    ->map(fn (Vehicle $vehicle): string => $vehicle->plate)
                    ->values()
                    ->all(),
                // Schon erfasste Stunden auf diesem Projekt (nur heute).
                'hours_recorded' => $assignment->project_id === null ? 0.0 : round((float) $todaysEntries
                    ->filter(fn (TimeEntry $entry): bool => $entry->project_id === $assignment->project_id)
                    ->sum(fn (TimeEntry $entry): float => (float) $entry->hours), 2),
            ];
        }

        return $cards;
    }

    /**
     * @return array{ends_on: string, notes: string|null}|null
     */
    private function vacation(Employee $employee, CarbonImmutable $today): ?array
    {
        $vacation = Vacation::query()
            ->where('employee_id', $employee->id)
            ->whereDate('starts_on', '<=', $today->toDateString())
            ->whereDate('ends_on', '>=', $today->toDateString())
            ->orderByDesc('ends_on')
            ->first();

        return $vacation === null ? null : [
            'ends_on' => $vacation->ends_on->toDateString(),
            'notes' => $vacation->notes,
        ];
    }

    /**
     * Offene Aufgaben und Mängel, die mir zugeteilt sind.
     *
     * @return list<array<string, mixed>>
     */
    private function tasks(User $user, CarbonImmutable $today): array
    {
        $tasks = Task::query()
            ->where('assignee_user_id', $user->id)
            ->whereNull('done_at')
            ->with('project:id,title')
            ->orderByRaw('due_on ASC NULLS LAST, id DESC')
            ->limit(15)
            ->get();

        $rows = [];

        foreach ($tasks as $task) {
            $rows[] = [
                'id' => $task->id,
                'title' => $task->title,
                'kind' => $task->kind->value,
                'kind_label' => $task->kind->label(),
                'due_on' => $task->due_on?->toDateString(),
                'overdue' => $task->due_on !== null && $task->due_on->lessThan($today),
                'project_id' => $task->project_id,
                'project' => $task->project?->title,
            ];
        }

        return $rows;
    }
}

<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Vehicle;
use App\Support\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Einteilung: Wochenansicht — je Tag die Baustellen mit zugeteilten
 * Mitarbeitern und Fahrzeugen. Doppelt eingeteilte Mitarbeiter bzw.
 * Fahrzeuge werden am selben Tag markiert.
 */
class AssignmentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Assignment::class);

        $anchor = CarbonImmutable::make($request->query('date')) ?? CarbonImmutable::today();
        $monday = $anchor->startOfWeek();
        $sunday = $monday->addDays(6);

        $assignments = Assignment::query()
            ->whereBetween('work_date', [$monday->toDateString(), $sunday->toDateString()])
            ->with(['project:id,title', 'employees:id,name', 'vehicles:id,plate'])
            ->orderBy('work_date')
            ->orderBy('id')
            ->get();

        // Doppelbelegung je Tag: derselbe Mitarbeiter bzw. dasselbe
        // Fahrzeug in mehr als einer Einteilung.
        $employeeConflicts = $this->conflicts($assignments, 'employees');
        $vehicleConflicts = $this->conflicts($assignments, 'vehicles');

        $canWrite = Gate::allows('create', Assignment::class);

        return Inertia::render('assignments/index', [
            'week' => [
                'monday' => $monday->toDateString(),
                'previous' => $monday->subWeek()->toDateString(),
                'next' => $monday->addWeek()->toDateString(),
                'today' => CarbonImmutable::today()->toDateString(),
            ],
            'days' => collect(range(0, 6))->map(fn (int $offset): string => $monday->addDays($offset)->toDateString())->all(),
            'assignments' => $assignments->map(fn (Assignment $assignment): array => [
                'id' => $assignment->id,
                'work_date' => $assignment->work_date->toDateString(),
                'project_id' => $assignment->project_id,
                'site' => $assignment->site,
                'label' => $assignment->label(),
                'notes' => $assignment->notes,
                'employees' => $assignment->employees->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'conflict' => in_array($assignment->work_date->toDateString().'|'.$employee->id, $employeeConflicts, true),
                ])->values(),
                'vehicles' => $assignment->vehicles->map(fn (Vehicle $vehicle): array => [
                    'id' => $vehicle->id,
                    'plate' => $vehicle->plate,
                    'conflict' => in_array($assignment->work_date->toDateString().'|'.$vehicle->id, $vehicleConflicts, true),
                ])->values(),
            ])->values(),
            'employees' => Employee::query()->where('active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->name])->values(),
            'vehicles' => Vehicle::query()->where('active', true)->orderBy('plate')->get(['id', 'plate'])
                ->map(fn (Vehicle $vehicle): array => ['id' => $vehicle->id, 'plate' => $vehicle->plate])->values(),
            'projects' => Project::query()->orderBy('title')->get(['id', 'title'])
                ->map(fn (Project $project): array => ['id' => $project->id, 'title' => $project->title])->values(),
            'canWrite' => $canWrite,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Assignment::class);

        [$validated, $employeeIds, $vehicleIds] = $this->validated($request);

        DB::transaction(function () use ($validated, $employeeIds, $vehicleIds): void {
            $assignment = Assignment::create($validated);
            $assignment->employees()->sync($employeeIds);
            $assignment->vehicles()->sync($vehicleIds);
        });

        return back()->with('success', 'Einteilung gespeichert.');
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        [$validated, $employeeIds, $vehicleIds] = $this->validated($request);

        DB::transaction(function () use ($assignment, $validated, $employeeIds, $vehicleIds): void {
            $assignment->update($validated);
            $assignment->employees()->sync($employeeIds);
            $assignment->vehicles()->sync($vehicleIds);
        });

        return back()->with('success', 'Einteilung gespeichert.');
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        Gate::authorize('delete', $assignment);

        $assignment->delete();

        return back()->with('success', 'Einteilung entfernt.');
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<int>, 2: list<int>}
     */
    private function validated(Request $request): array
    {
        $companyId = app(CompanyContext::class)->requireId();

        $validated = $request->validate([
            'work_date' => ['required', 'date'],
            'project_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->where('company_id', $companyId),
            ],
            'site' => ['nullable', 'string', 'max:255', 'required_without:project_id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'employee_ids' => ['array'],
            'employee_ids.*' => [Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'vehicle_ids' => ['array'],
            'vehicle_ids.*' => [Rule::exists('vehicles', 'id')->where('company_id', $companyId)],
        ], [
            'site.required_without' => 'Bitte eine Baustelle eintragen oder ein Projekt wählen.',
        ], [
            'work_date' => 'Tag',
            'project_id' => 'Projekt',
            'site' => 'Baustelle',
            'employee_ids' => 'Mitarbeiter',
            'vehicle_ids' => 'Fahrzeuge',
        ]);

        $employeeIds = array_map('intval', array_values($validated['employee_ids'] ?? []));
        $vehicleIds = array_map('intval', array_values($validated['vehicle_ids'] ?? []));

        unset($validated['employee_ids'], $validated['vehicle_ids']);

        return [$validated, $employeeIds, $vehicleIds];
    }

    /**
     * Schlüssel "datum|id" aller Mitarbeiter bzw. Fahrzeuge, die am
     * selben Tag in mehr als einer Einteilung stehen.
     *
     * @param  Collection<int, Assignment>  $assignments
     * @return list<string>
     */
    private function conflicts($assignments, string $relation): array
    {
        $seen = [];
        $conflicts = [];

        foreach ($assignments as $assignment) {
            foreach ($assignment->{$relation} as $item) {
                $key = $assignment->work_date->toDateString().'|'.$item->id;

                if (isset($seen[$key])) {
                    $conflicts[$key] = true;
                }

                $seen[$key] = true;
            }
        }

        return array_keys($conflicts);
    }
}

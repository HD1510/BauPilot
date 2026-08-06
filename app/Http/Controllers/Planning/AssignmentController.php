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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Einteilung: Wochenansicht — je Tag die Baustellen mit zugeteilten
 * Mitarbeitern und Fahrzeugen. Einträge lassen sich für einen ganzen
 * Zeitraum anlegen und per Drag & Drop auf andere Tage verschieben.
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
            ->with(['project:id,title,site_address', 'employees:id,name', 'vehicles:id,plate'])
            ->orderBy('work_date')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

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
                'color' => $assignment->color,
                'employees' => $assignment->employees->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                ])->values(),
                'vehicles' => $assignment->vehicles->map(fn (Vehicle $vehicle): array => [
                    'id' => $vehicle->id,
                    'plate' => $vehicle->plate,
                ])->values(),
            ])->values(),
            // Nur aktive, als planbar markierte Mitarbeiter stehen zur Wahl.
            'employees' => Employee::query()->where('active', true)->where('plannable', true)->orderBy('name')->get(['id', 'name'])
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

        [$validated, $employeeIds, $vehicleIds] = $this->validated($request, withRange: true);

        $from = CarbonImmutable::parse($validated['work_date']);
        $until = isset($validated['work_date_until'])
            ? CarbonImmutable::parse($validated['work_date_until'])
            : $from;
        unset($validated['work_date_until']);

        // Jede neue Anlage bekommt die nächste Farbe der Palette — alle
        // Tage eines Zeitraums teilen sich dieselbe.
        $color = ((int) Assignment::query()->max('id') + 1) % 10;

        DB::transaction(function () use ($validated, $employeeIds, $vehicleIds, $from, $until, $color): void {
            for ($day = $from; $day->lessThanOrEqualTo($until); $day = $day->addDay()) {
                $assignment = Assignment::create([
                    ...$validated,
                    'work_date' => $day->toDateString(),
                    'color' => $color,
                    'position' => (int) Assignment::query()->whereDate('work_date', $day->toDateString())->max('position') + 1,
                ]);
                $assignment->employees()->sync($employeeIds);
                $assignment->vehicles()->sync($vehicleIds);
            }
        });

        $days = (int) $from->diffInDays($until) + 1;

        return back()->with('success', $days > 1
            ? "Einteilung für {$days} Tage angelegt."
            : 'Einteilung gespeichert.');
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

    /**
     * Drag & Drop: Tag wechseln und/oder innerhalb des Tages einsortieren —
     * alles andere bleibt. Ohne Position wird hinten angehängt.
     */
    public function move(Request $request, Assignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $validated = $request->validate(
            [
                'work_date' => ['required', 'date'],
                'position' => ['nullable', 'integer', 'min:0'],
            ],
            [],
            ['work_date' => 'Tag', 'position' => 'Position'],
        );

        DB::transaction(function () use ($assignment, $validated): void {
            $day = CarbonImmutable::parse($validated['work_date'])->toDateString();

            $others = Assignment::query()
                ->whereDate('work_date', $day)
                ->whereKeyNot($assignment->id)
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->all();

            $index = isset($validated['position'])
                ? min((int) $validated['position'], count($others))
                : count($others);

            $assignment->work_date = Carbon::parse($day);
            array_splice($others, $index, 0, [$assignment]);

            foreach ($others as $position => $item) {
                $item->position = $position;
                $item->save();
            }
        });

        return back()->with('success', 'Einteilung verschoben.');
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
    private function validated(Request $request, bool $withRange = false): array
    {
        $companyId = app(CompanyContext::class)->requireId();

        $rules = [
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
        ];

        if ($withRange) {
            // Zeitraum-Anlage: ein Eintrag je Tag, begrenzt auf 31 Tage.
            $rules['work_date_until'] = [
                'nullable', 'date', 'after_or_equal:work_date',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $from = CarbonImmutable::make($request->input('work_date'));

                    if ($from !== null && CarbonImmutable::parse((string) $value)->greaterThan($from->addDays(31))) {
                        $fail('Der Zeitraum darf höchstens 31 Tage umfassen.');
                    }
                },
            ];
        }

        $validated = $request->validate($rules, [
            'site.required_without' => 'Bitte eine Baustelle eintragen oder ein Projekt wählen.',
        ], [
            'work_date' => 'Tag',
            'work_date_until' => 'bis',
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
}

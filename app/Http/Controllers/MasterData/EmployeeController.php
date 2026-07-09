<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\EmployeeRequest;
use App\Models\Employee;
use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Employee::class);

        $q = trim((string) $request->query('q'));
        $archived = $request->boolean('archived');

        $employees = Employee::query()
            ->where('active', ! $archived)
            ->when($q !== '', fn ($query) => $query->whereLike('name', "%{$q}%"))
            ->with('user:id,name,email')
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'overtime_rate' => $employee->overtime_rate,
                'calc_hourly_rate' => $employee->calc_hourly_rate,
                'user' => $employee->user?->only(['id', 'name', 'email']),
                'archived' => ! $employee->active,
            ]);

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'filters' => ['q' => $q, 'archived' => $archived],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Employee::class);

        return Inertia::render('employees/create', [
            'users' => $this->userOptions(),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $employee = Employee::create($request->validated());

        return redirect()->route('employees.edit', $employee)
            ->with('success', "Mitarbeiter „{$employee->name}“ wurde angelegt.");
    }

    public function edit(Employee $employee): Response
    {
        Gate::authorize('view', $employee);

        return Inertia::render('employees/edit', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'overtime_rate' => $employee->overtime_rate,
                'calc_hourly_rate' => $employee->calc_hourly_rate,
                'user_id' => $employee->user_id,
                'active' => $employee->active,
                'notes' => $employee->notes,
                'lock_version' => $employee->lock_version,
            ],
            'users' => $this->userOptions(),
            'canWrite' => Gate::allows('update', $employee),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        if ($employee->isStale($request->integer('lock_version'))) {
            return back()->withErrors([
                'lock_version' => 'Der Mitarbeiter wurde zwischenzeitlich geändert. Bitte Seite neu laden.',
            ]);
        }

        $employee->update($request->validated());

        return back()->with('success', 'Änderungen gespeichert.');
    }

    public function archive(Employee $employee): RedirectResponse
    {
        Gate::authorize('archive', $employee);

        $employee->update(['active' => ! $employee->active]);

        return back()->with('success', $employee->active
            ? "Mitarbeiter „{$employee->name}“ ist wieder aktiv."
            : "Mitarbeiter „{$employee->name}“ wurde archiviert.");
    }

    /**
     * Benutzerkonten der aktiven Firma für die optionale Verknüpfung.
     *
     * @return array<int, array{id: int, name: string, email: string}>
     */
    private function userOptions(): array
    {
        return app(CompanyContext::class)->requireCompany()
            ->users()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
            ->values()
            ->all();
    }
}

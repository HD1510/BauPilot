<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\CompanyRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\EmployeeRequest;
use App\Models\Document;
use App\Models\Employee;
use App\Models\OvertimeEntry;
use App\Models\OvertimePayout;
use App\Models\User;
use App\Support\Overtime\OvertimeBalance;
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
            ->with('user:id,name,username,email')
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'overtime_rate' => $employee->overtime_rate,
                'calc_hourly_rate' => $employee->calc_hourly_rate,
                'user' => $employee->user?->only(['id', 'name', 'username', 'email']),
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

        // Überstunden sind Lohndaten — nur für admin/büro (M8).
        $overtime = Gate::allows('viewAny', OvertimeEntry::class)
            ? [
                'entries' => $employee->overtimeEntries()->orderByDesc('year')->orderByDesc('month')->get()
                    ->map(fn (OvertimeEntry $entry): array => [
                        'id' => $entry->id,
                        'year' => $entry->year,
                        'month' => $entry->month,
                        'hours' => (float) $entry->hours,
                        'note' => $entry->note,
                    ])->values(),
                'payouts' => $employee->overtimePayouts()->orderByDesc('paid_on')->get()
                    ->map(fn (OvertimePayout $payout): array => [
                        'id' => $payout->id,
                        'paid_on' => $payout->paid_on->toDateString(),
                        'hours' => (float) $payout->hours,
                        'amount' => (float) $payout->amount,
                    ])->values(),
                'balance' => app(OvertimeBalance::class)->forEmployee($employee),
            ]
            : null;

        $canWrite = Gate::allows('update', $employee);

        return Inertia::render('employees/edit', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                // Personalakte (Adresse, SV-Nummer, IBAN …) ist
                // vertraulich — die Baustelle bekommt sie gar nicht.
                'address' => $canWrite ? $employee->address : null,
                'birth_date' => $canWrite ? $employee->birth_date?->toDateString() : null,
                'started_on' => $canWrite ? $employee->started_on?->toDateString() : null,
                'ended_on' => $canWrite ? $employee->ended_on?->toDateString() : null,
                'social_security_number' => $canWrite ? $employee->social_security_number : null,
                'iban' => $canWrite ? $employee->iban : null,
                'overtime_rate' => $employee->overtime_rate,
                'calc_hourly_rate' => $employee->calc_hourly_rate,
                'user_id' => $employee->user_id,
                'active' => $employee->active,
                'notes' => $employee->notes,
                'lock_version' => $employee->lock_version,
            ],
            'users' => $this->userOptions(),
            'canWrite' => $canWrite,
            'overtime' => $overtime,
            'account' => $employee->user?->only(['id', 'name', 'username', 'email']),
            'canManageAccount' => Gate::allows('manageMembers', app(CompanyContext::class)->requireCompany()),
            'roles' => collect(CompanyRole::cases())->map(fn (CompanyRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->values()->all(),
            'documents' => $canWrite
                ? $employee->documents()->latest('id')->get()
                    ->map(fn (Document $document): array => [
                        'id' => $document->id,
                        'original_name' => $document->original_name,
                        'category_label' => $document->category->label(),
                        'size' => $document->size,
                        'is_image' => str_starts_with($document->mime, 'image/'),
                    ])->values()
                : null,
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
     * @return array<int, array{id: int, name: string, login: string}>
     */
    private function userOptions(): array
    {
        return app(CompanyContext::class)->requireCompany()
            ->users()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.username', 'users.email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'login' => $user->username ?? $user->email ?? '—',
            ])
            ->values()
            ->all();
    }
}

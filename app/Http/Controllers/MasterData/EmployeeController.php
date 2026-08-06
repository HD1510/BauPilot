<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\CompanyRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\EmployeeRequest;
use App\Models\Document;
use App\Models\Employee;
use App\Models\OvertimeEntry;
use App\Models\OvertimePayout;
use App\Models\SiteReportEntry;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Employees\EmployeeAccountCreator;
use App\Support\Overtime\OvertimeBalance;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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

        $company = app(CompanyContext::class)->requireCompany();

        return Inertia::render('employees/create', [
            'users' => $this->userOptions(),
            // Nur wer Mitglieder verwalten darf (Admin), kann gleich
            // beim Anlegen ein Benutzerkonto miterstellen.
            'accountRoles' => Gate::allows('manageMembers', $company)
                ? collect(CompanyRole::cases())->map(fn (CompanyRole $role): array => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ])->values()->all()
                : null,
        ]);
    }

    public function store(EmployeeRequest $request, EmployeeAccountCreator $accounts): RedirectResponse
    {
        $account = null;

        // Optional gleich ein Benutzerkonto mit anlegen (nur Admin).
        if ($request->boolean('create_account')) {
            $company = app(CompanyContext::class)->requireCompany();

            Gate::authorize('manageMembers', $company);

            $account = $request->validate(
                EmployeeAccountCreator::rules(),
                EmployeeAccountCreator::messages(),
                EmployeeAccountCreator::attributes(),
            );

            $accounts->ensureUsernameFree($account['username']);
        }

        $employee = DB::transaction(function () use ($request, $accounts, $account): Employee {
            $employee = Employee::create($request->validated());

            if ($account !== null) {
                $accounts->create(
                    $employee,
                    app(CompanyContext::class)->requireCompany(),
                    $account['username'],
                    $account['email'] ?? null,
                    $account['password'],
                    CompanyRole::from($account['role']),
                );
            }

            return $employee;
        });

        return redirect()->route('employees.edit', $employee)
            ->with('success', $account !== null
                ? "Mitarbeiter „{$employee->name}“ und Konto angelegt — Zugangsdaten bitte persönlich weitergeben."
                : "Mitarbeiter „{$employee->name}“ wurde angelegt.");
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
                'plannable' => $employee->plannable,
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
     * Endgültig löschen — nur ohne Zeiten und Regieberichte (Lohn- und
     * Leistungsnachweise bleiben nachvollziehbar; sonst archivieren).
     * Personalakte (Dateien) und Überstunden gehen mit; ein verknüpftes
     * Benutzerkonto bleibt bestehen.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        Gate::authorize('delete', $employee);

        $inUse = TimeEntry::query()->where('employee_id', $employee->id)->exists()
            || SiteReportEntry::query()->where('employee_id', $employee->id)->exists();

        if ($inUse) {
            return back()->with('error', "Mitarbeiter „{$employee->name}“ hat Zeiten oder Regieberichte und kann nicht gelöscht werden — bitte archivieren.");
        }

        DB::transaction(function () use ($employee): void {
            foreach ($employee->documents as $document) {
                Storage::disk('documents')->delete($document->path);
                $document->delete();
            }

            $employee->delete();
        });

        return redirect()->route('employees.index')
            ->with('success', "Mitarbeiter „{$employee->name}“ wurde gelöscht.");
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

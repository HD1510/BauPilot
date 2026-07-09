<?php

namespace App\Http\Controllers\Companies;

use App\Enums\CompanyRole;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetActiveCompany;
use App\Http\Requests\Companies\CompanyRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Company::class);

        /** @var User $user */
        $user = $request->user();

        $companies = $user->companies()
            ->orderBy('name')
            ->get()
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'short_code' => $company->short_code,
                'color' => $company->color,
                'legal_form' => $company->legal_form,
                'archived_at' => $company->archived_at?->toIso8601String(),
                'role' => $company->pivot?->role,
            ]);

        return Inertia::render('companies/index', [
            'companies' => $companies,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Company::class);

        return Inertia::render('companies/create');
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        Gate::authorize('create', Company::class);

        /** @var User $user */
        $user = $request->user();

        $company = DB::transaction(function () use ($request, $user): Company {
            $company = Company::create($request->validated());
            $company->users()->attach($user->id, ['role' => CompanyRole::Admin->value]);

            return $company;
        });

        $request->session()->put(SetActiveCompany::SESSION_KEY, $company->id);

        return redirect()->route('companies.index')
            ->with('success', "Firma „{$company->name}“ wurde angelegt.");
    }

    public function edit(Request $request, Company $company): Response
    {
        Gate::authorize('update', $company);

        return Inertia::render('companies/edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'short_code' => $company->short_code,
                'color' => $company->color,
                'legal_form' => $company->legal_form,
                'address' => $company->address,
                'vat_id' => $company->vat_id,
                'fiscal_year_start_month' => $company->fiscal_year_start_month,
                'calc_hourly_rate' => $company->calc_hourly_rate,
                'warranty_years' => $company->warranty_years,
                'archived_at' => $company->archived_at?->toIso8601String(),
                'lock_version' => $company->lock_version,
            ],
            'members' => $company->users()->orderBy('name')->get()
                ->map(fn ($member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot?->role,
                ]),
            'roles' => collect(CompanyRole::cases())->map(fn (CompanyRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ]),
        ]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('update', $company);

        // Gleichzeitiges Arbeiten (Architekturblatt Abschnitt 3): stimmt die
        // gelesene Version nicht mehr, wird nicht überschrieben.
        if ($company->isStale($request->integer('lock_version'))) {
            return back()->withErrors([
                'lock_version' => 'Die Firma wurde zwischenzeitlich von jemand anderem geändert. Bitte Seite neu laden und Änderung wiederholen.',
            ]);
        }

        $company->update($request->validated());

        return back()->with('success', 'Änderungen gespeichert.');
    }

    public function archive(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('archive', $company);

        $company->isArchived() ? $company->unarchive() : $company->archive();

        return back()->with('success', $company->isArchived()
            ? "Firma „{$company->name}“ wurde archiviert."
            : "Firma „{$company->name}“ ist wieder aktiv.");
    }
}

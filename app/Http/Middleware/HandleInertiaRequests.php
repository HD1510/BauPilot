<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // Closures: erst beim Rendern ausgewertet, wenn SetActiveCompany gelaufen ist.
            'tenancy' => fn (): array => $this->tenancy($request),
            'flash' => fn (): array => [
                'success' => $request->session()->get('success'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Aktive Firma, Firmenliste und Rolle für Plakette und Wechsler.
     * Finanz-Sichtbarkeit als Flag, damit die Oberfläche nichts anbietet,
     * was der Server ohnehin verweigert.
     *
     * @return array<string, mixed>
     */
    private function tenancy(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return ['activeCompany' => null, 'companies' => [], 'role' => null, 'canViewFinancials' => false];
        }

        $active = app(CompanyContext::class)->current();
        $role = $user->currentRole();

        return [
            'activeCompany' => $active === null ? null : [
                'id' => $active->id,
                'name' => $active->name,
                'short_code' => $active->short_code,
                'color' => $active->color,
            ],
            'companies' => $user->companies()->orderBy('name')->get()
                ->map(fn ($company): array => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'short_code' => $company->short_code,
                    'color' => $company->color,
                    'role' => $company->pivot?->role,
                ]),
            'role' => $role?->value,
            'canViewFinancials' => $user->can('view-financials'),
        ];
    }
}

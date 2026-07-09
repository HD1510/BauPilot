<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ein Kontext je Request bzw. Job — nie über Grenzen hinweg geteilt.
        $this->app->scoped(CompanyContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
    }

    /**
     * Finanz-Sichtbarkeit (Architekturblatt Abschnitt 3): Rolle Baustelle
     * sieht keine Rechnungen, offenen Posten oder Beträge. Das Gate filtert
     * Policies und Inertia-Props gleichermaßen.
     */
    protected function configureGates(): void
    {
        Gate::define('view-financials', function (User $user): bool {
            $role = $user->currentRole();

            return $role !== null && $role->canViewFinancials();
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

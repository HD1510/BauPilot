<?php

namespace App\Providers;

use App\Models\ChangeOrder;
use App\Models\ExternalOffer;
use App\Models\IncomingInvoice;
use App\Models\Offer;
use App\Models\OutgoingInvoice;
use App\Models\Project;
use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
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
        $this->configureBlueprintMacros();
        $this->configureMorphMap();
    }

    /**
     * Stabile Aliasnamen für polymorphe Verknüpfungen (documents,
     * retentions) — Klassennamen gehören nicht in die Datenbank.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'offer' => Offer::class,
            'project' => Project::class,
            'change_order' => ChangeOrder::class,
            'external_offer' => ExternalOffer::class,
            'outgoing_invoice' => OutgoingInvoice::class,
            'incoming_invoice' => IncomingInvoice::class,
        ]);
    }

    /**
     * Wiederkehrende Spalten fachlicher Tabellen (Architekturblatt 3/4.1):
     * company_id auf jeder fachlichen Tabelle, lock_version für die
     * optimistische Sperre, created_by/updated_by als Benutzerstempel.
     */
    protected function configureBlueprintMacros(): void
    {
        Blueprint::macro('companyOwned', function (): void {
            /** @var Blueprint $this */
            $this->foreignId('company_id')->constrained()->restrictOnDelete();
        });

        Blueprint::macro('businessMeta', function (): void {
            /** @var Blueprint $this */
            $this->unsignedInteger('lock_version')->default(0);
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $this->timestampsTz();
        });
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

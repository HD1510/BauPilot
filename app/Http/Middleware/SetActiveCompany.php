<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt die aktive Firma je Request aus der Session (Architekturblatt
 * Abschnitt 3). Gültig ist nur eine Firma, für die ein company_user-Eintrag
 * des angemeldeten Benutzers existiert; sonst fällt die Wahl auf die erste
 * Firma des Benutzers. Für Gäste passiert nichts.
 */
class SetActiveCompany
{
    public const SESSION_KEY = 'active_company_id';

    public function __construct(private CompanyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $company = $this->resolveCompany($request);

            if ($company !== null) {
                $this->context->set($company);
                $request->session()->put(self::SESSION_KEY, $company->id);
            } else {
                $request->session()->forget(self::SESSION_KEY);
            }
        }

        return $next($request);
    }

    private function resolveCompany(Request $request): ?Company
    {
        /** @var User $user */
        $user = $request->user();

        $sessionId = $request->session()->get(self::SESSION_KEY);

        if ($sessionId !== null) {
            $company = $user->companies()->whereKey($sessionId)->first();

            if ($company !== null) {
                return $company;
            }
        }

        return $user->companies()->orderBy('name')->first();
    }
}

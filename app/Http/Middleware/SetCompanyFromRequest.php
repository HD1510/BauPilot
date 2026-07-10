<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\Tenancy\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mandantenkontext für die JSON-Endpunkte (Architekturblatt Abschnitte
 * 3 und 9): Jeder Schreib-Request trägt die Ziel-company_id EXPLIZIT —
 * nie implizit aus der Session. Ein zwischenzeitlicher Firmenwechsel in
 * Session oder Zweit-Tab kann einen gepufferten Offline-Request damit
 * nicht in die falsche Firma verbuchen.
 *
 * Muss vor SubstituteBindings laufen (bootstrap/app.php), damit
 * Route-Model-Binding bereits im richtigen Firmenkontext auflöst.
 */
class SetCompanyFromRequest
{
    public function __construct(private CompanyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);

        $companyId = $request->input('company_id');

        abort_unless(is_numeric($companyId), 422, 'company_id ist erforderlich.');

        /** @var Company|null $company */
        $company = $user->companies()->whereKey((int) $companyId)->first();

        abort_if($company === null, 403, 'Kein Zugriff auf diese Firma.');

        $this->context->set($company);

        return $next($request);
    }
}

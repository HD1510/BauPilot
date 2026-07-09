<?php

namespace App\Http\Controllers\Companies;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetActiveCompany;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanySwitchController extends Controller
{
    /**
     * Wechsel der aktiven Firma — nur auf Firmen möglich, für die ein
     * company_user-Eintrag existiert (Architekturblatt Abschnitt 3).
     */
    public function __invoke(Request $request, Company $company): RedirectResponse
    {
        Gate::authorize('switchTo', $company);

        $request->session()->put(SetActiveCompany::SESSION_KEY, $company->id);

        return redirect()->route('dashboard');
    }
}

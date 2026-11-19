<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Vacation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Urlaube je Mitarbeiter — erscheinen automatisch in der Einteilung.
 */
class VacationController extends Controller
{
    public function store(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('update', $employee);

        $validated = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ], [
            'ends_on.after_or_equal' => 'Das Urlaubsende darf nicht vor dem Beginn liegen.',
        ], [
            'starts_on' => 'von',
            'ends_on' => 'bis',
        ]);

        $employee->vacations()->create([
            ...$validated,
            'company_id' => $employee->company_id,
        ]);

        return back()->with('success', 'Urlaub eingetragen.');
    }

    public function destroy(Employee $employee, Vacation $vacation): RedirectResponse
    {
        Gate::authorize('update', $employee);

        abort_unless($vacation->employee_id === $employee->id, 404);

        $vacation->delete();

        return back()->with('success', 'Urlaub entfernt.');
    }
}

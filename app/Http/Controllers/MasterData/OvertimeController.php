<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OvertimeEntry;
use App\Models\OvertimePayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Überstunden je Monat und Auszahlungen (Architekturblatt M4/1A):
 * Lohndaten — nur admin/büro (FinancialPolicy). Ein Eintrag je Monat;
 * erneutes Erfassen desselben Monats überschreibt ihn bewusst.
 */
class OvertimeController extends Controller
{
    public function storeEntry(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('create', OvertimeEntry::class);

        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'hours' => ['required', 'numeric', 'between:-200,200'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['hours' => 'Stunden', 'month' => 'Monat', 'year' => 'Jahr']);

        OvertimeEntry::query()->updateOrCreate(
            [
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'year' => $validated['year'],
                'month' => $validated['month'],
            ],
            ['hours' => $validated['hours'], 'note' => $validated['note'] ?? null],
        );

        return back()->with('success', sprintf('Überstunden %02d/%d gespeichert.', $validated['month'], $validated['year']));
    }

    public function destroyEntry(Employee $employee, OvertimeEntry $entry): RedirectResponse
    {
        Gate::authorize('delete', $entry);
        abort_unless($entry->employee_id === $employee->id, 404);

        $entry->delete();

        return back()->with('success', 'Monatseintrag gelöscht.');
    }

    public function storePayout(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('create', OvertimePayout::class);

        $validated = $request->validate([
            'paid_on' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0'],
        ], [], ['paid_on' => 'Auszahlungsdatum', 'hours' => 'Stunden', 'amount' => 'Betrag']);

        $employee->overtimePayouts()->create($validated);

        return back()->with('success', 'Auszahlung erfasst.');
    }

    public function destroyPayout(Employee $employee, OvertimePayout $payout): RedirectResponse
    {
        Gate::authorize('delete', $payout);
        abort_unless($payout->employee_id === $employee->id, 404);

        $payout->delete();

        return back()->with('success', 'Auszahlung gelöscht.');
    }
}

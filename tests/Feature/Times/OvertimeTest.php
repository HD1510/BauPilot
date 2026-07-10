<?php

use App\Enums\CompanyRole;
use App\Models\Employee;
use App\Models\OvertimeEntry;
use App\Support\Overtime\OvertimeBalance;
use App\Support\Tenancy\CompanyContext;

afterEach(function () {
    app(CompanyContext::class)->clear();
});

/**
 * Abnahmekriterium (M4/1A, nachgeholt in M8): „Überstundensaldo je
 * Mitarbeiter stimmt gegen Kontrollwerte."
 *
 *   Einträge: +20, +12,5, −4 (Abbau)  = 28,5 aufgebaut
 *   Auszahlung: 10 h                  = 10,0 ausgezahlt
 *   Saldo                             = 18,5
 */
test('kontrollwerte: überstundensaldo = einträge minus auszahlungen', function () {
    [, $company] = actingMember(CompanyRole::Office);
    app(CompanyContext::class)->set($company);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    foreach ([[2026, 4, '20.00'], [2026, 5, '12.50'], [2026, 6, '-4.00']] as [$year, $month, $hours]) {
        $this->post("/employees/{$employee->id}/overtime-entries", [
            'year' => $year, 'month' => $month, 'hours' => $hours,
        ])->assertSessionHasNoErrors();
        app(CompanyContext::class)->clear();
    }

    $this->post("/employees/{$employee->id}/overtime-payouts", [
        'paid_on' => '2026-07-01', 'hours' => '10.00', 'amount' => '350.00',
    ])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);
    $balance = app(OvertimeBalance::class)->forEmployee($employee->fresh());

    expect($balance['accrued'])->toBe(28.5)
        ->and($balance['paid_out'])->toBe(10.0)
        ->and($balance['balance'])->toBe(18.5);
});

test('ein eintrag je monat: erneutes erfassen überschreibt', function () {
    [, $company] = actingMember(CompanyRole::Admin);
    app(CompanyContext::class)->set($company);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->post("/employees/{$employee->id}/overtime-entries", ['year' => 2026, 'month' => 7, 'hours' => '10.00']);
    app(CompanyContext::class)->clear();
    $this->post("/employees/{$employee->id}/overtime-entries", ['year' => 2026, 'month' => 7, 'hours' => '12.00', 'note' => 'korrigiert']);

    $entries = OvertimeEntry::withoutGlobalScopes()->where('employee_id', $employee->id)->get();

    expect($entries)->toHaveCount(1)
        ->and((float) $entries->first()->hours)->toBe(12.0)
        ->and($entries->first()->note)->toBe('korrigiert');
});

test('überstunden sind lohndaten: baustelle bleibt draußen', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $employee = Employee::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->post("/employees/{$employee->id}/overtime-entries", ['year' => 2026, 'month' => 7, 'hours' => '10.00'])
        ->assertForbidden();

    app(CompanyContext::class)->clear();

    // Auf dem Mitarbeiterblatt sieht die Baustelle keine Überstunden-Daten.
    $this->get("/employees/{$employee->id}/edit")
        ->assertInertia(fn ($page) => $page->where('overtime', null));
});

<?php

namespace App\Support\Overtime;

use App\Models\Employee;

/**
 * Überstundensaldo je Mitarbeiter (Architekturblatt M4/1A): nie
 * gespeichert — Summe der Monatseinträge (Abbau negativ) minus
 * ausgezahlte Stunden.
 */
class OvertimeBalance
{
    /**
     * @return array{accrued: float, paid_out: float, balance: float}
     */
    public function forEmployee(Employee $employee): array
    {
        $accrued = (float) $employee->overtimeEntries()->sum('hours');
        $paidOut = (float) $employee->overtimePayouts()->sum('hours');

        return [
            'accrued' => round($accrued, 2),
            'paid_out' => round($paidOut, 2),
            'balance' => round($accrued - $paidOut, 2),
        ];
    }
}

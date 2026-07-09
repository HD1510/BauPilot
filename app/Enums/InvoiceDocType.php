<?php

namespace App\Enums;

enum InvoiceDocType: string
{
    case Invoice = 'invoice';
    case Partial = 'partial';
    case Final = 'final';
    case CreditNote = 'credit_note';
    case Cancellation = 'cancellation';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Rechnung',
            self::Partial => 'Teilrechnung',
            self::Final => 'Schlussrechnung',
            self::CreditNote => 'Gutschrift',
            self::Cancellation => 'Storno',
        };
    }

    /**
     * Gutschrift und Storno beziehen sich immer auf eine Originalrechnung
     * und werden mit negativen Beträgen gespeichert (Architekturblatt v1.1).
     */
    public function isNegative(): bool
    {
        return $this === self::CreditNote || $this === self::Cancellation;
    }
}

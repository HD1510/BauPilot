<?php

namespace App\Enums;

enum OutgoingPaymentStatus: string
{
    case Open = 'open';
    case Partial = 'partial';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::Partial => 'Teilweise bezahlt',
            self::Paid => 'Bezahlt',
            self::Cancelled => 'Storniert',
        };
    }
}

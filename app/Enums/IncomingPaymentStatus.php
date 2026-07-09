<?php

namespace App\Enums;

enum IncomingPaymentStatus: string
{
    case Open = 'open';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::Partial => 'Teilweise bezahlt',
            self::Paid => 'Bezahlt',
        };
    }
}

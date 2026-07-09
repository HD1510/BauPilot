<?php

namespace App\Enums;

enum ChangeOrderStatus: string
{
    case Requested = 'requested';
    case Offered = 'offered';
    case Commissioned = 'commissioned';
    case Invoiced = 'invoiced';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Angefragt',
            self::Offered => 'Angeboten',
            self::Commissioned => 'Beauftragt',
            self::Invoiced => 'Verrechnet',
            self::Rejected => 'Abgelehnt',
        };
    }
}

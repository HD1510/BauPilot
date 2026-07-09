<?php

namespace App\Enums;

enum ZeroRateReason: string
{
    case ReverseCharge19_1a = 'reverse_charge_19_1a';
    case IntraCommunity = 'intra_community';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ReverseCharge19_1a => '§19 Abs 1a (Bauleistung, Übergang der Steuerschuld)',
            self::IntraCommunity => 'Innergemeinschaftliche Lieferung',
            self::Other => 'Sonstiger Grund',
        };
    }
}

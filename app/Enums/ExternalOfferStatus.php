<?php

namespace App\Enums;

enum ExternalOfferStatus: string
{
    case Received = 'received';
    case Commissioned = 'commissioned';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Erhalten',
            self::Commissioned => 'Beauftragt',
            self::Rejected => 'Abgelehnt',
        };
    }
}

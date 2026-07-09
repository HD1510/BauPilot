<?php

namespace App\Enums;

enum OfferStatus: string
{
    case Inquiry = 'inquiry';
    case ViewingPlanned = 'viewing_planned';
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case NoResponse = 'no_response';

    public function label(): string
    {
        return match ($this) {
            self::Inquiry => 'Anfrage',
            self::ViewingPlanned => 'Besichtigung geplant',
            self::Offered => 'Angebot gelegt',
            self::Accepted => 'Angenommen',
            self::Rejected => 'Abgelehnt',
            self::NoResponse => 'Keine Rückmeldung',
        };
    }
}

<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case Offer = 'offer';
    case Invoice = 'invoice';
    case Plan = 'plan';
    case Photo = 'photo';
    case DeliveryNote = 'delivery_note';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Offer => 'Angebot',
            self::Invoice => 'Rechnung',
            self::Plan => 'Plan',
            self::Photo => 'Foto',
            self::DeliveryNote => 'Lieferschein',
            self::Other => 'Sonstiges',
        };
    }
}

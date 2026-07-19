<?php

namespace App\Enums;

enum DocumentCategory: string
{
    case Offer = 'offer';
    case Invoice = 'invoice';
    case Plan = 'plan';
    case Photo = 'photo';
    case DeliveryNote = 'delivery_note';
    case Contract = 'contract';
    case Certificate = 'certificate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Offer => 'Angebot',
            self::Invoice => 'Rechnung',
            self::Plan => 'Plan',
            self::Photo => 'Foto',
            self::DeliveryNote => 'Lieferschein',
            self::Contract => 'Arbeitsvertrag',
            self::Certificate => 'Ausbildungsnachweis',
            self::Other => 'Sonstiges',
        };
    }
}

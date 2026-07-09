<?php

namespace App\Support\Deadlines;

enum DeadlineKind: string
{
    case PaymentDue = 'payment_due';
    case IncomingDue = 'incoming_due';
    case Skonto = 'skonto';
    case Retention = 'retention';
    case Appointment = 'appointment';
    case Vehicle = 'vehicle';
    case Warranty = 'warranty';
    case FollowUp = 'follow_up';

    public function label(): string
    {
        return match ($this) {
            self::PaymentDue => 'Zahlungsziel Kunde',
            self::IncomingDue => 'Zahlbar an Lieferant',
            self::Skonto => 'Skontofrist',
            self::Retention => 'Rücklass fällig',
            self::Appointment => 'Projekttermin',
            self::Vehicle => 'Fahrzeugtermin',
            self::Warranty => 'Gewährleistungsende',
            self::FollowUp => 'Wiedervorlage',
        };
    }
}

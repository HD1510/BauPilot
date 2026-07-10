<?php

namespace App\Enums;

enum SiteReportStatus: string
{
    case Draft = 'draft';
    case Signed = 'signed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Signed => 'Unterschrieben',
        };
    }
}

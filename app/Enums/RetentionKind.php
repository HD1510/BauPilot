<?php

namespace App\Enums;

enum RetentionKind: string
{
    case Warranty = 'warranty';
    case Coverage = 'coverage';

    public function label(): string
    {
        return match ($this) {
            self::Warranty => 'Haftrücklass',
            self::Coverage => 'Deckungsrücklass',
        };
    }
}

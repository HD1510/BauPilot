<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Open = 'open';
    case Active = 'active';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::Active => 'Aktiv',
            self::Done => 'Abgeschlossen',
        };
    }
}

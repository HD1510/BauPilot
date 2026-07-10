<?php

namespace App\Enums;

enum TaskKind: string
{
    case Task = 'task';
    case Defect = 'defect';

    public function label(): string
    {
        return match ($this) {
            self::Task => 'Aufgabe',
            self::Defect => 'Mangel',
        };
    }
}

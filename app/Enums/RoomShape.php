<?php

namespace App\Enums;

/**
 * Grundriss-Form eines Raums in der Baukalkulation.
 */
enum RoomShape: string
{
    case Rectangle = 'rectangle';
    case LShape = 'l_shape';
    case Trapezoid = 'trapezoid';
    case Triangle = 'triangle';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Rectangle => 'Rechteck',
            self::LShape => 'L-Form',
            self::Trapezoid => 'Trapez',
            self::Triangle => 'Dreieck',
            self::Manual => 'Manuell (Fläche/Umfang)',
        };
    }
}

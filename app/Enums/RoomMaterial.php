<?php

namespace App\Enums;

/**
 * Belag eines Raums in der Baukalkulation — bestimmt, welche
 * Gewerke-Mengen anfallen.
 */
enum RoomMaterial: string
{
    case Parquet = 'parquet';
    case FloorTiles = 'floor_tiles';
    case WallTiles = 'wall_tiles';

    public function label(): string
    {
        return match ($this) {
            self::Parquet => 'Parkett',
            self::FloorTiles => 'Bodenfliesen',
            self::WallTiles => 'Wand- + Bodenfliesen',
        };
    }
}

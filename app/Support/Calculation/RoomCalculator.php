<?php

namespace App\Support\Calculation;

use App\Enums\RoomMaterial;
use App\Enums\RoomShape;
use App\Models\Calculation;
use App\Models\CalculationRoom;

/**
 * Mengen- und Kostenermittlung je Raum (Baukalkulation).
 *
 * Gegenüber der Alt-Logik verbessert:
 *  - Verschnitt und Fliesenhöhe sind je Kalkulation einstellbar statt
 *    fix 15 % bzw. 2,10 m.
 *  - Türbreiten reduzieren Sockelleisten und Silikonfugen. Fenster und
 *    Öffnungen werden bewusst NICHT abgezogen: Das Ausarbeiten ist
 *    mehr Arbeit als die Fläche selbst — sie zählen voll mit.
 *  - Die Wandfliesenfläche reicht bis zur Fliesenhöhe (nicht bis zur
 *    Decke), der Maler übernimmt den Rest — beides passt zusammen.
 *  - Der Materialverschnitt steckt direkt in den Belagsmengen, statt
 *    als eigene Position doppelt zu zählen.
 *  - Zu jeder Menge gibt es einen Preis je Kalkulation — unterm Strich
 *    steht eine Angebotssumme netto.
 */
class RoomCalculator
{
    /**
     * @return array{
     *     area: float, perimeter: float,
     *     parquet_area: float,
     *     floor_tile_area_raw: float, floor_tile_area: float,
     *     wall_tile_area_raw: float, wall_tile_area: float,
     *     silicone: float,
     *     skirting_parquet: float, skirting_tiles: float, skirting: float,
     *     painting_area: float,
     *     cost: float
     * }
     */
    public function quantities(CalculationRoom $room, Calculation $calculation): array
    {
        [$area, $perimeter] = $this->base($room);

        $wasteFactor = 1 + (float) $calculation->waste_percent / 100;
        $tileHeight = (float) $calculation->wall_tile_height;
        $height = (float) $room->height;
        $doorWidth = min((float) $room->door_width, $perimeter);

        // Sockel und Bodenrand-Fugen laufen nicht durch Türöffnungen.
        $edgePerimeter = $perimeter - $doorWidth;

        $parquetArea = $room->material === RoomMaterial::Parquet
            ? $area * $wasteFactor
            : 0.0;

        // Wandfliesen-Räume (Bad, WC …) bekommen auch Bodenfliesen.
        // Die Fliesenflächen gibt es ohne und mit Verschnitt — bestellt
        // wird mit, verlegt ohne.
        $floorTileAreaRaw = in_array($room->material, [RoomMaterial::FloorTiles, RoomMaterial::WallTiles], true)
            ? $area
            : 0.0;

        $wallTileAreaRaw = $room->material === RoomMaterial::WallTiles
            ? $perimeter * min($height, $tileHeight)
            : 0.0;

        $silicone = match ($room->material) {
            // Kanten (Außenecken) plus Boden-/Wannenfuge unten und
            // Abschlussfuge oben.
            RoomMaterial::WallTiles => (float) $room->edges * $tileHeight + $edgePerimeter * 2,
            RoomMaterial::FloorTiles => $edgePerimeter,
            RoomMaterial::Parquet => 0.0,
        };

        // Sockelleisten getrennt nach Belag — Parkett- und Fliesensockel
        // sind unterschiedliche Produkte.
        $skirtingParquet = $room->material === RoomMaterial::Parquet ? $edgePerimeter : 0.0;
        $skirtingTiles = $room->material === RoomMaterial::FloorTiles ? $edgePerimeter : 0.0;

        $paintingArea = $room->material === RoomMaterial::WallTiles
            ? $perimeter * max($height - $tileHeight, 0) + $area
            : $perimeter * $height + $area;

        $quantities = [
            'area' => $this->round($area),
            'perimeter' => $this->round($perimeter),
            'parquet_area' => $this->round($parquetArea),
            'floor_tile_area_raw' => $this->round($floorTileAreaRaw),
            'floor_tile_area' => $this->round($floorTileAreaRaw * $wasteFactor),
            'wall_tile_area_raw' => $this->round($wallTileAreaRaw),
            'wall_tile_area' => $this->round($wallTileAreaRaw * $wasteFactor),
            'silicone' => $this->round($silicone),
            'skirting_parquet' => $this->round($skirtingParquet),
            'skirting_tiles' => $this->round($skirtingTiles),
            'skirting' => $this->round($skirtingParquet + $skirtingTiles),
            'painting_area' => $this->round($paintingArea),
        ];

        $quantities['cost'] = $this->round(
            $quantities['parquet_area'] * (float) $calculation->price_parquet
            + $quantities['floor_tile_area'] * (float) $calculation->price_floor_tiles
            + $quantities['wall_tile_area'] * (float) $calculation->price_wall_tiles
            + $quantities['silicone'] * (float) $calculation->price_silicone
            + $quantities['skirting'] * (float) $calculation->price_skirting
            + $quantities['painting_area'] * (float) $calculation->price_painting,
        );

        return $quantities;
    }

    /**
     * Summen über alle Räume einer Kalkulation.
     *
     * @param  iterable<int, CalculationRoom>  $rooms
     * @return array<string, float>
     */
    public function totals(iterable $rooms, Calculation $calculation): array
    {
        $totals = [
            'area' => 0.0, 'parquet_area' => 0.0,
            'floor_tile_area_raw' => 0.0, 'floor_tile_area' => 0.0,
            'wall_tile_area_raw' => 0.0, 'wall_tile_area' => 0.0,
            'silicone' => 0.0,
            'skirting_parquet' => 0.0, 'skirting_tiles' => 0.0, 'skirting' => 0.0,
            'painting_area' => 0.0, 'cost' => 0.0,
        ];

        foreach ($rooms as $room) {
            $quantities = $this->quantities($room, $calculation);

            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $quantities[$key];
            }
        }

        return array_map(fn (float $value): float => $this->round($value), $totals);
    }

    /**
     * Fläche und Umfang je Grundriss-Form. Bei der L-Form entspricht
     * der Umfang dem umschließenden Rechteck — eine ausgeschnittene
     * Ecke ändert den Umfang nicht.
     *
     * @return array{0: float, 1: float}
     */
    private function base(CalculationRoom $room): array
    {
        $length = (float) $room->length;
        $width = (float) $room->width;
        $length2 = (float) $room->length2;
        $width2 = (float) $room->width2;
        $depth = (float) $room->depth;

        return match ($room->shape) {
            RoomShape::Rectangle => [
                $length * $width,
                ($length + $width) * 2,
            ],
            RoomShape::LShape => [
                max($length * $width - $length2 * $width2, 0),
                ($length + $width) * 2,
            ],
            RoomShape::Trapezoid => [
                ($length + $width) / 2 * $depth,
                $length + $width + 2 * sqrt($depth ** 2 + (($length - $width) / 2) ** 2),
            ],
            RoomShape::Triangle => [
                $length * $depth / 2,
                $length + $depth + sqrt($length ** 2 + $depth ** 2),
            ],
            RoomShape::Manual => [
                (float) $room->area_manual,
                (float) $room->perimeter_manual,
            ],
        };
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }
}

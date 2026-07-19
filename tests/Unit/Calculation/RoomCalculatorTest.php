<?php

use App\Models\Calculation;
use App\Models\CalculationRoom;
use App\Support\Calculation\RoomCalculator;

/**
 * Mengen- und Kostenermittlung je Raum — reine Geometrie, keine KI.
 */
function calcFixture(array $overrides = []): Calculation
{
    return new Calculation([
        'waste_percent' => 15,
        'wall_tile_height' => 2.10,
        'price_parquet' => 40,
        'price_floor_tiles' => 50,
        'price_wall_tiles' => 60,
        'price_silicone' => 5,
        'price_skirting' => 10,
        'price_painting' => 12,
        ...$overrides,
    ]);
}

function roomFixture(array $overrides = []): CalculationRoom
{
    return new CalculationRoom([
        'name' => 'Raum',
        'shape' => 'rectangle',
        'material' => 'parquet',
        'length' => 5,
        'width' => 4,
        'height' => 2.5,
        'edges' => 0,
        'door_width' => 0,
        'opening_area' => 0,
    ]);
}

test('rechteck mit parkett: fläche, verschnitt, sockel, maler', function () {
    $q = (new RoomCalculator)->quantities(roomFixture(), calcFixture());

    expect($q['area'])->toBe(20.0)
        ->and($q['perimeter'])->toBe(18.0)
        ->and($q['parquet_area'])->toBe(23.0)   // 20 × 1,15
        ->and($q['floor_tile_area'])->toBe(0.0)
        ->and($q['silicone'])->toBe(0.0)
        ->and($q['skirting'])->toBe(18.0)
        ->and($q['painting_area'])->toBe(65.0)  // 18 × 2,5 + 20
        ->and($q['cost'])->toBe(23.0 * 40 + 18.0 * 10 + 65.0 * 12);
});

test('türbreite reduziert sockel und fugen, öffnungen die malerfläche', function () {
    $room = roomFixture();
    $room->door_width = '1.8';
    $room->opening_area = '4';

    $q = (new RoomCalculator)->quantities($room, calcFixture());

    expect($q['skirting'])->toBe(16.2)           // 18 − 1,8
        ->and($q['painting_area'])->toBe(61.0);  // 18 × 2,5 − 4 + 20
});

test('wandfliesen: fliesenband bis fliesenhöhe, maler übernimmt den rest', function () {
    $room = roomFixture();
    $room->material = 'wall_tiles';
    $room->length = '2.4';
    $room->width = '2.0';
    $room->edges = 2;

    // Umfang 8,8; Band 8,8 × 2,1 = 18,48; × 1,15 = 21,25
    $q = (new RoomCalculator)->quantities($room, calcFixture());

    expect($q['wall_tile_area'])->toBe(21.25)
        ->and($q['floor_tile_area'])->toBe(5.52) // 4,8 × 1,15
        ->and($q['silicone'])->toBe(2 * 2.1 + 8.8 * 2)
        ->and($q['skirting'])->toBe(0.0)
        ->and($q['painting_area'])->toBe(round(8.8 * 0.4 + 4.8, 2));
});

test('l-form: ausschnitt mindert die fläche, nicht den umfang', function () {
    $room = roomFixture();
    $room->shape = 'l_shape';
    $room->length2 = '2';
    $room->width2 = '1.5';

    $q = (new RoomCalculator)->quantities($room, calcFixture());

    expect($q['area'])->toBe(17.0)      // 20 − 3
        ->and($q['perimeter'])->toBe(18.0);
});

test('trapez und dreieck rechnen mit pythagoras', function () {
    $trapez = roomFixture();
    $trapez->shape = 'trapezoid';
    $trapez->length = '6';
    $trapez->width = '4';
    $trapez->depth = '3';

    $dreieck = roomFixture();
    $dreieck->shape = 'triangle';
    $dreieck->length = '3';
    $dreieck->depth = '4';

    $calc = calcFixture();
    $qTrapez = (new RoomCalculator)->quantities($trapez, $calc);
    $qDreieck = (new RoomCalculator)->quantities($dreieck, $calc);

    expect($qTrapez['area'])->toBe(15.0)     // (6+4)/2 × 3
        ->and($qTrapez['perimeter'])->toBe(round(6 + 4 + 2 * sqrt(9 + 1), 2))
        ->and($qDreieck['area'])->toBe(6.0)  // 3 × 4 / 2
        ->and($qDreieck['perimeter'])->toBe(12.0); // 3 + 4 + 5
});

test('manuelle form nutzt eingegebene fläche und umfang', function () {
    $room = roomFixture();
    $room->shape = 'manual';
    $room->area_manual = '12.5';
    $room->perimeter_manual = '15';

    $q = (new RoomCalculator)->quantities($room, calcFixture());

    expect($q['area'])->toBe(12.5)
        ->and($q['perimeter'])->toBe(15.0);
});

test('verschnitt und fliesenhöhe sind einstellbar', function () {
    $room = roomFixture();
    $room->material = 'wall_tiles';
    $room->height = '3';

    $calc = calcFixture(['waste_percent' => 10, 'wall_tile_height' => 1.5]);
    $q = (new RoomCalculator)->quantities($room, $calc);

    expect($q['floor_tile_area'])->toBe(22.0)                        // 20 × 1,10
        ->and($q['wall_tile_area'])->toBe(round(18 * 1.5 * 1.1, 2))  // Band bis 1,5 m
        ->and($q['painting_area'])->toBe(round(18 * 1.5 + 20, 2));   // Rest bis 3 m + Decke
});

test('summen addieren alle räume', function () {
    $calc = calcFixture();
    $rooms = [roomFixture(), roomFixture()];

    $totals = (new RoomCalculator)->totals($rooms, $calc);

    expect($totals['area'])->toBe(40.0)
        ->and($totals['cost'])->toBe(2 * (23.0 * 40 + 18.0 * 10 + 65.0 * 12));
});

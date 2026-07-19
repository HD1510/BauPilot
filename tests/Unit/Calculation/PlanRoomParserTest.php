<?php

use App\Support\Calculation\PlanRoomParser;

/**
 * Raumstempel aus der Textebene von CAD-Plänen — die Muster stammen
 * 1:1 aus einem echten Einreichplan (Aufbau-/Abbruchplan, AutoCAD).
 */
test('raumstempel mit fläche, raumhöhe und belag werden erkannt', function () {
    $text = implode("\n", [
        "GARDEROBE4,04 m²RH 2.30mFLIESEN\tABSTELLRAUM6,39 m²RH 2.30mFLIESEN",
        'TERRASSE24,42 m²TRAVERTIN',
        'KINDERZIMMER14,16 m²RH 2.65mHOLZBODEN',
        'ABSTELLNISCHE4,70 m²RH ca.190cmBETON',
        'BAD / WC3,25 m²RH 2.65mFLIESEN',
    ]);

    $rooms = PlanRoomParser::parse($text);

    expect($rooms)->toHaveCount(6)
        ->and($rooms[0]['name'])->toBe('Garderobe')
        ->and($rooms[0]['area'])->toBe(4.04)
        ->and($rooms[0]['height'])->toBe(2.3)
        ->and($rooms[0]['material'])->toBe('floor_tiles')
        ->and($rooms[0]['surface'])->toBe('Fliesen')
        ->and($rooms[1]['name'])->toBe('Abstellraum')
        ->and($rooms[2]['name'])->toBe('Terrasse')
        ->and($rooms[2]['height'])->toBe(2.5)     // keine RH am Stempel
        ->and($rooms[3]['material'])->toBe('parquet') // Holzboden
        ->and($rooms[4]['height'])->toBe(1.9)     // RH ca.190cm
        ->and($rooms[5]['name'])->toBe('Bad / Wc');
});

test('bad und wc werden zu wandfliesen, holzboden zu parkett', function () {
    $rooms = PlanRoomParser::parse(implode("\n", [
        'BAD / WC3,25 m²RH 2.65mFLIESEN',
        'SCHLAFZIMMER11,22 m²RH 2.30mHOLZBODEN',
        'VORRAUM4,34 m²RH 2.65mFLIESEN',
    ]));

    expect($rooms[0]['material'])->toBe('wall_tiles')
        ->and($rooms[1]['material'])->toBe('parquet')
        ->and($rooms[2]['material'])->toBe('floor_tiles');
});

test('gebäudewerte, summenzeilen und tabellen mit punktlinien sind keine räume', function () {
    $rooms = PlanRoomParser::parse(implode("\n", [
        'GEBÄUDEFRONT 225,23 m²',
        'GEBÄUDEHÖHE =25,23 m² / 7,18 m = ',
        'HAUS 20GWNF:54,29 m²',
        'ERDGESCHOSS NFL............................... ',
        '26,70 m²',
        'NUTZFLÄCHE GESAMT..........................',
        '55,23 m²',
    ]));

    expect($rooms)->toBe([]);
});

test('idente stempel aus mehreren planansichten zählen nur einmal', function () {
    $rooms = PlanRoomParser::parse(implode("\n", [
        'OUTDOORDUSCHE1,50 m²FLIESEN',
        'WOHNKÜCHE18,24 m²RH 2.65mFLIESEN',
        'OUTDOORDUSCHE1,50 m²FLIESEN',
        'WOHNKÜCHE18,10 m²RH 2.65mFLIESEN',
    ]));

    // Die Dusche steht doppelt am Blatt (Bestand + Neubau) — die zwei
    // Wohnküchen unterscheiden sich in der Fläche und bleiben beide.
    expect($rooms)->toHaveCount(3);
});

test('der name des folgestempels wird nicht als belag verschluckt', function () {
    $rooms = PlanRoomParser::parse(
        "GARDER.4,37 m²\tSCHLAFZIMMER11,22 m²RH 2.30mFLIESEN",
    );

    expect($rooms)->toHaveCount(2)
        ->and($rooms[0]['name'])->toBe('Garder')
        ->and($rooms[0]['surface'])->toBeNull()
        ->and($rooms[1]['name'])->toBe('Schlafzimmer')
        ->and($rooms[1]['area'])->toBe(11.22);
});

test('der umfang wird als rechteck mit seitenverhältnis 1,5 geschätzt', function () {
    $rooms = PlanRoomParser::parse('WOHNZIMMER24,00 m²RH 2.50mHOLZBODEN');

    // 24 m² → Seiten 6,0 × 4,0 → Umfang 20 lfm.
    expect($rooms[0]['perimeter'])->toBe(20.0);
});

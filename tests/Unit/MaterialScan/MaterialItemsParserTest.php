<?php

use App\Support\MaterialScan\MaterialItemsParser;

/**
 * Zeilen-Heuristik für Preislisten und Rechnungen: Artikelzeilen werden
 * erkannt, Summen- und Fußzeilen nicht.
 */
test('artikelzeilen mit nummer, einheit und einzelpreis werden erkannt', function () {
    $text = implode("\n", [
        'Preisliste 2026 — Baustoffhandel Nord',
        '104711 Zement CEM II/A-LL 42,5 N 25 Sack 4,90 122,50',
        'ZK-25 Ziegel 25er Hochloch 480 Stk 1,85 888,00',
        'Schalholz Fichte 3m 24,50',
        'Zwischensumme 1.010,50',
        'Nettobetrag 1.010,50',
        'IBAN AT61 1904 3002 3457 3201',
    ]);

    $items = MaterialItemsParser::parse($text);

    expect($items)->toHaveCount(3)
        ->and($items[0]->articleNo)->toBe('104711')
        ->and($items[0]->name)->toBe('Zement CEM II/A-LL 42,5 N')
        ->and($items[0]->unit)->toBe('Sack')
        ->and($items[0]->priceNet)->toBe(4.90)
        ->and($items[1]->articleNo)->toBe('ZK-25')
        ->and($items[1]->name)->toBe('Ziegel 25er Hochloch')
        ->and($items[1]->unit)->toBe('Stk')
        ->and($items[1]->priceNet)->toBe(1.85)
        ->and($items[2]->articleNo)->toBeNull()
        ->and($items[2]->name)->toBe('Schalholz Fichte 3m')
        ->and($items[2]->priceNet)->toBe(24.50);
});

test('einzelpreis wird über menge mal preis gleich gesamt bestimmt', function () {
    // Reihenfolge Gesamt vor Einzelpreis — die Menge entscheidet.
    $items = MaterialItemsParser::parse('Estrichbeton E300 12 m3 1.140,00 95,00');

    expect($items)->toHaveCount(1)
        ->and($items[0]->unit)->toBe('m³')
        ->and($items[0]->priceNet)->toBe(95.00);
});

test('kopf-, summen- und fusszeilen liefern keine artikel', function () {
    $text = implode("\n", [
        'Rechnung Nr. 2026-014 vom 01.07.2026',
        'Gesamtbetrag brutto 1.212,60',
        'Zahlbar innerhalb 14 Tagen: 3 % Skonto 36,38',
        'UID ATU12345678 — Seite 1 von 1',
    ]);

    expect(MaterialItemsParser::parse($text))->toBe([]);
});

test('einheiten werden normalisiert', function () {
    $items = MaterialItemsParser::parse(implode("\n", [
        'Mauermörtel MG III 40 St 3,20 128,00',
        'Estrich fein 5 to 89,00 445,00',
        'Abdichtbahn V60 3 Rol 42,00 126,00',
    ]));

    expect($items)->toHaveCount(3)
        ->and($items[0]->unit)->toBe('Stk')
        ->and($items[1]->unit)->toBe('t')
        ->and($items[2]->unit)->toBe('Rolle');
});

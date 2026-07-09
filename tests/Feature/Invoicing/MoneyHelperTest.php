<?php

use App\Support\Money\MoneyHelper;

test('netto-eingabe: ust und brutto aus dem steuersatz', function () {
    expect(MoneyHelper::fromNet('1000.00', '20'))->toBe([
        'net' => '1000.00', 'vat' => '200.00', 'gross' => '1200.00',
    ]);

    expect(MoneyHelper::fromNet('150.50', '10'))->toBe([
        'net' => '150.50', 'vat' => '15.05', 'gross' => '165.55',
    ]);
});

test('brutto-eingabe: netto und ust, summe geht exakt auf', function () {
    expect(MoneyHelper::fromGross('1200.00', '20'))->toBe([
        'net' => '1000.00', 'vat' => '200.00', 'gross' => '1200.00',
    ]);

    // 100 / 1.2 = 83.333… → netto 83.33, USt als Differenz 16.67
    $amounts = MoneyHelper::fromGross('100.00', '20');
    expect($amounts)->toBe(['net' => '83.33', 'vat' => '16.67', 'gross' => '100.00'])
        ->and((float) $amounts['net'] + (float) $amounts['vat'])->toBe((float) $amounts['gross']);
});

test('reverse charge: satz 0, ust 0, brutto gleich netto', function () {
    expect(MoneyHelper::fromNet('5000.00', '0'))->toBe([
        'net' => '5000.00', 'vat' => '0.00', 'gross' => '5000.00',
    ]);
});

test('kaufmännische rundung auf zwei nachkommastellen', function () {
    expect(MoneyHelper::round('2.675'))->toBe('2.68')
        ->and(MoneyHelper::round('2.674'))->toBe('2.67')
        ->and(MoneyHelper::round('-2.675'))->toBe('-2.68');
});

test('negation für gutschrift und storno', function () {
    expect(MoneyHelper::negate(['net' => '1000.00', 'vat' => '200.00', 'gross' => '1200.00']))
        ->toBe(['net' => '-1000.00', 'vat' => '-200.00', 'gross' => '-1200.00']);
});

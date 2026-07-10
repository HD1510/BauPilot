<?php

use App\Support\InvoiceScan\TextInvoiceParser;

test('typische österreichische rechnung wird vollständig erkannt', function () {
    $text = <<<'TEXT'
    Huber Transporte GmbH
    Industriestraße 12, 4020 Linz
    UID: ATU12345678

    Rechnung Nr: RE-2026-0815
    Rechnungsdatum: 01.07.2026

    Schotterlieferung BV Lagerhalle

    Nettobetrag: 1.000,00 EUR
    20 % USt: 200,00 EUR
    Gesamtbetrag: 1.200,00 EUR

    Zahlbar innerhalb 21 Tagen. 3 % Skonto bei Zahlung binnen 14 Tagen.
    IBAN: AT61 1904 3002 3457 3201
    TEXT;

    $invoice = TextInvoiceParser::parse($text);

    expect($invoice->supplierName)->toBe('Huber Transporte GmbH')
        ->and($invoice->supplierUid)->toBe('ATU12345678')
        ->and($invoice->supplierIban)->toBe('AT611904300234573201')
        ->and($invoice->supplierInvoiceNo)->toBe('RE-2026-0815')
        ->and($invoice->invoiceDate)->toBe('2026-07-01')
        ->and($invoice->net)->toBe(1000.0)
        ->and($invoice->vatRate)->toBe(20.0)
        ->and($invoice->gross)->toBe(1200.0)
        ->and($invoice->paymentTargetDays)->toBe(21)
        ->and($invoice->skontoPercent)->toBe(3.0)
        ->and($invoice->skontoDays)->toBe(14)
        ->and($invoice->reverseCharge)->toBeFalse();
});

test('eigene uid des empfängers wird übersprungen', function () {
    $text = "Zimmerei Holzmann e.U.\nUID ATU99999999\nRechnung an Bau GmbH, UID ATU11111111";

    expect(TextInvoiceParser::parse($text, ownVatId: 'ATU99999999')->supplierUid)->toBe('ATU11111111')
        ->and(TextInvoiceParser::parse($text)->supplierUid)->toBe('ATU99999999');
});

test('reverse charge und paragraph 19 werden erkannt', function () {
    expect(TextInvoiceParser::parse('Übergang der Steuerschuld gem. § 19 UStG')->reverseCharge)->toBeTrue()
        ->and(TextInvoiceParser::parse('Reverse-Charge-Verfahren')->reverseCharge)->toBeTrue()
        ->and(TextInvoiceParser::parse('Normale Rechnung mit 20 % USt')->reverseCharge)->toBeFalse();
});

test('zahlungsziel-varianten: direkt, netto-klausel und fälligkeitsdatum', function () {
    expect(TextInvoiceParser::paymentTargetDays('Zahlungsziel: 30 Tage', null))->toBe(30)
        ->and(TextInvoiceParser::paymentTargetDays('zahlbar binnen 14 Tagen', null))->toBe(14)
        ->and(TextInvoiceParser::paymentTargetDays('30 Tage netto', null))->toBe(30)
        ->and(TextInvoiceParser::paymentTargetDays('fällig am 22.07.2026', '2026-07-01'))->toBe(21)
        ->and(TextInvoiceParser::paymentTargetDays('prompt zu bezahlen', null))->toBeNull();
});

test('skonto-varianten inkl. umgekehrter reihenfolge', function () {
    expect(TextInvoiceParser::skonto('2,5 % Skonto bei Zahlung innerhalb von 7 Tagen'))
        ->toBe(['percent' => 2.5, 'days' => 7])
        ->and(TextInvoiceParser::skonto('Skonto: 3 % binnen 10 Tagen'))
        ->toBe(['percent' => 3.0, 'days' => 10])
        ->and(TextInvoiceParser::skonto('ohne Abzug'))
        ->toBe(['percent' => null, 'days' => null]);
});

test('brutto-erkennung mit rechnungsbetrag und internationalem zahlenformat', function () {
    $invoice = TextInvoiceParser::parse("Rechnungsbetrag: € 590.00\nDatum 05.07.26");

    expect($invoice->gross)->toBe(590.0)
        ->and($invoice->invoiceDate)->toBe('2026-07-05');
});

test('briefkopf-heuristik findet firma mit rechtsform, bekannter lieferant hat vorrang', function () {
    $text = "Zimmerei Holzmann e.U.\nGewerbepark 3\nRechnung Nr. HZ-77";

    expect(TextInvoiceParser::parse($text)->supplierName)->toBe('Zimmerei Holzmann e.U.')
        ->and(TextInvoiceParser::parse($text, knownSupplierName: 'Holzmann Zimmerei')->supplierName)->toBe('Holzmann Zimmerei')
        ->and(TextInvoiceParser::parse("Max Mustermann\nPrivatrechnung")->supplierName)->toBeNull();
});

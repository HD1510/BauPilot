<?php

use App\Support\InvoiceScan\ScanDocumentKind;
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

    $invoice = TextInvoiceParser::parse($text, ScanDocumentKind::IncomingInvoice);

    expect($invoice->partnerName)->toBe('Huber Transporte GmbH')
        ->and($invoice->partnerUid)->toBe('ATU12345678')
        ->and($invoice->partnerIban)->toBe('AT611904300234573201')
        ->and($invoice->docNumber)->toBe('RE-2026-0815')
        ->and($invoice->docDate)->toBe('2026-07-01')
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

    expect(TextInvoiceParser::parse($text, ScanDocumentKind::IncomingInvoice, ownVatId: 'ATU99999999')->partnerUid)->toBe('ATU11111111')
        ->and(TextInvoiceParser::parse($text, ScanDocumentKind::IncomingInvoice)->partnerUid)->toBe('ATU99999999');
});

test('reverse charge und paragraph 19 werden erkannt', function () {
    expect(TextInvoiceParser::parse('Übergang der Steuerschuld gem. § 19 UStG', ScanDocumentKind::IncomingInvoice)->reverseCharge)->toBeTrue()
        ->and(TextInvoiceParser::parse('Reverse-Charge-Verfahren', ScanDocumentKind::IncomingInvoice)->reverseCharge)->toBeTrue()
        ->and(TextInvoiceParser::parse('Normale Rechnung mit 20 % USt', ScanDocumentKind::IncomingInvoice)->reverseCharge)->toBeFalse();
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
    $invoice = TextInvoiceParser::parse("Rechnungsbetrag: € 590.00\nDatum 05.07.26", ScanDocumentKind::IncomingInvoice);

    expect($invoice->gross)->toBe(590.0)
        ->and($invoice->docDate)->toBe('2026-07-05');
});

test('briefkopf-heuristik findet firma mit rechtsform, bekannter lieferant hat vorrang', function () {
    $text = "Zimmerei Holzmann e.U.\nGewerbepark 3\nRechnung Nr. HZ-77";

    expect(TextInvoiceParser::parse($text, ScanDocumentKind::IncomingInvoice)->partnerName)->toBe('Zimmerei Holzmann e.U.')
        ->and(TextInvoiceParser::parse($text, ScanDocumentKind::IncomingInvoice, knownPartnerName: 'Holzmann Zimmerei')->partnerName)->toBe('Holzmann Zimmerei')
        ->and(TextInvoiceParser::parse("Max Mustermann\nPrivatrechnung", ScanDocumentKind::IncomingInvoice)->partnerName)->toBeNull();
});

test('auftragssumme zählt nur bei angeboten als netto, nicht auf rechnungen', function () {
    $text = "Auftragssumme: 250.000,00 EUR\nNettobetrag: 12.345,00 EUR";

    expect(TextInvoiceParser::parse($text, ScanDocumentKind::IncomingInvoice)->net)->toBe(12345.0)
        ->and(TextInvoiceParser::parse('Angebotssumme netto: 5.000,00', ScanDocumentKind::Offer)->net)->toBe(5000.0);
});

test('angebotsdatum schlägt das rechnungsdatum auf rechnungen nicht', function () {
    $text = "Angebotsdatum: 12.03.2026\nRechnungsdatum: 05.07.2026";

    expect(TextInvoiceParser::parse($text, ScanDocumentKind::IncomingInvoice)->docDate)->toBe('2026-07-05')
        ->and(TextInvoiceParser::parse($text, ScanDocumentKind::Offer)->docDate)->toBe('2026-03-12');
});

test('angebots-nummernmuster fängt füllwörter wie „vom" nicht', function () {
    expect(TextInvoiceParser::parse("Angebot vom 12.03.2026\nSanierung", ScanDocumentKind::Offer)->docNumber)->not->toBe('vom')
        ->and(TextInvoiceParser::parse('Angebot AN-2026-12 für BV Dach', ScanDocumentKind::Offer)->docNumber)->toBe('AN-2026-12');
});

test('briefkopf-heuristik rät auf eigenen belegen nie den partner', function () {
    // Gedruckter Name weicht vom hinterlegten ab — trotzdem kein Vorschlag,
    // sonst würde die eigene Firma als Kunde angelegt.
    $text = "Dienbauer Bau- und Zimmereibetrieb GmbH\nRechnung Nr: 250199";

    expect(TextInvoiceParser::parse($text, ScanDocumentKind::OutgoingInvoice, ownCompanyName: 'Dienbauer GmbH')->partnerName)->toBeNull()
        ->and(TextInvoiceParser::parse($text, ScanDocumentKind::Offer)->partnerName)->toBeNull();
});

test('eingangsrechnung: lieferant wird auch in der fußzeile gefunden', function () {
    // Kopf ohne Rechtsform (Logo/Anschrift), alle Firmendaten unten —
    // wie bei vielen echten Rechnungen.
    $lines = array_merge(
        ['RECHNUNG', 'Bau GmbH', 'Musterstraße 1', '4020 Linz'],
        array_fill(0, 14, 'Position Material und Arbeitszeit'),
        ['Huber Transporte GmbH | Industriestraße 12, 4021 Linz | ATU12345678 | IBAN AT61 1904 3002 3457 3201'],
    );

    $invoice = TextInvoiceParser::parse(
        implode("\n", $lines),
        ScanDocumentKind::IncomingInvoice,
        ownCompanyName: 'Bau GmbH',
    );

    expect($invoice->partnerName)->toBe('Huber Transporte GmbH');
});

test('ausgangsrechnung: kunde wird im anschriftenfeld gefunden — auch privat', function () {
    $text = implode("\n", [
        'Bau GmbH · Musterstraße 1 · 4020 Linz',
        'Familie Maier',
        'Ringstraße 5',
        '4030 Linz',
        'Rechnung Nr: 250200',
    ]);

    expect(TextInvoiceParser::parse($text, ScanDocumentKind::OutgoingInvoice, ownCompanyName: 'Bau GmbH')->partnerName)
        ->toBe('Familie Maier');

    // Mit „An:"-Beschriftung und Firma als Empfänger.
    $text2 = implode("\n", [
        'Bau GmbH',
        'Musterstraße 1',
        '4020 Linz',
        'An: Wohnbau Steiner GmbH',
        'Ringstraße 5',
        '4030 Linz',
    ]);

    expect(TextInvoiceParser::parse($text2, ScanDocumentKind::Offer, ownCompanyName: 'Bau GmbH')->partnerName)
        ->toBe('Wohnbau Steiner GmbH');
});

test('anschriftenfeld: eigene absenderadresse wird nicht zum kunden', function () {
    // Nur der eigene Adressblock vorhanden — kein Vorschlag.
    $text = implode("\n", [
        'Bau GmbH',
        'Musterstraße 1',
        '4020 Linz',
        'Rechnung Nr: 250201',
    ]);

    expect(TextInvoiceParser::parse($text, ScanDocumentKind::OutgoingInvoice, ownCompanyName: 'Bau GmbH')->partnerName)
        ->toBeNull();
});

test('praxisbeleg: einzelunternehmer-absenderzeile, kleinbuchstaben-ort, e-mail und telefon', function () {
    // Struktur der echten Beispielrechnung (HD5): kein Firmenwortlaut
    // mit Rechtsform, Absenderzeile mit · getrennt, Daten in Kopf UND Fuß.
    $text = implode("\n", [
        'HD5',
        'while(idea) → code();',
        'Herbert Dienbauer — HD5 · Eisenstädterstraße 32 · 7202 Bad Sauerbrunn',
        'Tets',
        'test',
        '7202 test',
        "Rechnungsnummer\t2026-001",
        "Rechnungsdatum\t19.07.2026",
        'Rechnung 2026-001',
        "POS. BESCHREIBUNG\tMENGE EINHEIT\tEINZELPREIS\tBETRAG",
        "1 Webseite\t1,00Pauschale\t500,00 € 500,00 €",
        "Netto\t600,00 €",
        "Gesamtbetrag\t600,00 €",
        'Umsatzsteuerbefreit gemäß § 6 Abs. 1 Z 27 UStG (Kleinunternehmerregelung).',
        'Zahlbar innerhalb von 8 Tagen bis 27.07.2026 auf IBAN AT35 3300 0000 0182 5231, BIC RLBBAT2E',
        'Vielen Dank für Ihren Auftrag!',
        'Herbert Dienbauer — HD5 · Eisenstädterstraße 32 · 7202 Bad Sauerbrunn · office@hd5.at · +43 664 5368836',
    ]);

    // Als Eingangsrechnung: Aussteller samt Kontaktdaten aus der Absenderzeile.
    $in = TextInvoiceParser::parse($text, ScanDocumentKind::IncomingInvoice, ownCompanyName: 'Bau GmbH');

    expect($in->partnerName)->toBe('Herbert Dienbauer — HD5')
        ->and($in->partnerEmail)->toBe('office@hd5.at')
        ->and($in->partnerPhone)->toBe('+43 664 5368836')
        ->and($in->partnerIban)->toBe('AT353300000001825231')
        ->and($in->paymentTargetDays)->toBe(8)
        ->and($in->docNumber)->toBe('2026-001')
        ->and($in->docDate)->toBe('2026-07-19')
        ->and($in->net)->toBe(600.0)
        ->and($in->gross)->toBe(600.0)
        // § 6 (Kleinunternehmer) ist KEIN Reverse Charge (§ 19).
        ->and($in->reverseCharge)->toBeFalse();

    // Als Ausgangsrechnung: Kunde aus dem Anschriftenfeld — auch mit
    // kleingeschriebenem Ort; „BETRAG" wird nie zur Firma (AG-Wortgrenze).
    $out = TextInvoiceParser::parse($text, ScanDocumentKind::OutgoingInvoice, ownCompanyName: 'Herbert Dienbauer — HD5');

    expect($out->partnerName)->toBe('Tets')
        ->and($out->partnerEmail)->toBeNull()
        ->and($out->docNumber)->toBe('2026-001')
        ->and($out->paymentTargetDays)->toBe(8);
});

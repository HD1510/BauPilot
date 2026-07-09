<?php

namespace App\Support\Import;

/**
 * BEISPIEL-MAPPING — WIRD GEGEN DIE ECHTE ÜBERSICHT KALIBRIERT.
 *
 * Das Architekturblatt (Abschnitt 8) sieht das erprobte Mapping aus der
 * Prototyp-Analyse vor (Blätter, Spalten, Monatsblöcke, Geschäftsjahre,
 * §19-Spalte, „Diverse"-Einträge). Bis die echte Übersicht_GmbH.xlsx
 * vorliegt, definiert diese Klasse eine schlichte, dokumentierte
 * Spaltenzuordnung; alle Blatt- und Spaltennamen sind bewusst an einer
 * Stelle gesammelt, damit die Kalibrierung ein reiner Konfigurations-
 * schritt bleibt.
 */
final class ImportMapping
{
    // Blattnamen
    public const SHEET_OUTGOING = 'Ausgangsrechnungen';

    public const SHEET_INCOMING = 'Eingangsrechnungen';

    // Spalten Ausgangsrechnungen (1-basiert)
    public const OUT_NUMBER = 1;      // Rechnungsnummer

    public const OUT_DATE = 2;        // Rechnungsdatum

    public const OUT_CUSTOMER = 3;    // Kundenname

    public const OUT_NET = 4;         // Netto

    public const OUT_VAT_RATE = 5;    // USt-Satz in % (leer bei §19)

    public const OUT_PARAGRAPH19 = 6; // §19-Spalte: "x"/"§19" = Bauleistung

    public const OUT_PAID_ON = 7;     // bezahlt am (leer = offen)

    public const OUT_PAID_AMOUNT = 8; // Zahlbetrag (leer = brutto)

    // Spalten Eingangsrechnungen (1-basiert)
    public const IN_SUPPLIER = 1;     // Lieferantenname

    public const IN_NUMBER = 2;       // Rechnungsnummer des Lieferanten

    public const IN_DATE = 3;         // Rechnungsdatum

    public const IN_NET = 4;          // Netto

    public const IN_VAT_RATE = 5;     // USt-Satz in % (leer bei §19)

    public const IN_PARAGRAPH19 = 6;  // §19/reverse charge

    public const IN_COST_TYPE = 7;    // Kostenart (Text)

    public const IN_PAID_ON = 8;      // bezahlt am

    // Erste Datenzeile (Zeile 1 ist die Überschrift)
    public const FIRST_DATA_ROW = 2;

    /** Kunden-/Lieferantenname für Sammel-Einträge ohne echten Stamm. */
    public const DIVERSE = 'Diverse';

    public static function isParagraph19(mixed $value): bool
    {
        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, ['x', 'ja', '§19', '19'], true);
    }
}

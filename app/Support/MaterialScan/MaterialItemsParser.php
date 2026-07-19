<?php

namespace App\Support\MaterialScan;

/**
 * Zeilen-Heuristik für Preislisten, Rechnungen und Lieferscheine:
 * Artikelzeilen tragen eine Bezeichnung und mindestens einen Betrag —
 * Summen-, Kopf- und Fußzeilen werden ausgefiltert. Bei Menge ×
 * Einzelpreis = Gesamt wird der Einzelpreis über die Menge bestimmt.
 */
class MaterialItemsParser
{
    /** Zeilen mit diesen Wörtern sind keine Artikelzeilen. */
    private const STOP_WORDS = [
        'summe', 'gesamt', 'zwischensumme', 'übertrag', 'netto', 'brutto',
        'ust', 'mwst', 'umsatzsteuer', 'skonto', 'zahlbar', 'zahlung',
        'iban', 'bic', 'uid', 'seite', 'rechnungsbetrag', 'fällig',
        'lieferdatum', 'rechnungsdatum', 'kundennummer', 'steuernummer',
        'vielen dank', 'gerichtsstand', 'firmenbuch',
    ];

    private const UNITS = [
        'stk', 'stück', 'st', 'stg', 'm', 'lfm', 'm2', 'm²', 'qm', 'm3', 'm³',
        'kg', 't', 'to', 'l', 'ltr', 'pkg', 'pkt', 'pal', 'sack', 'sa',
        'rolle', 'rol', 'bund', 'bd', 'h', 'std', 'psch', 'pausch', 'paar',
        'karton', 'ktn', 'dose', 'eimer', 'kübel', 'tafel', 'platte', 'gebinde',
    ];

    /**
     * @return list<ScannedMaterialItem>
     */
    public static function parse(string $text): array
    {
        $items = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $item = self::parseLine(trim($line));

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private static function parseLine(string $line): ?ScannedMaterialItem
    {
        if (mb_strlen($line) < 8 || mb_strlen($line) > 300) {
            return null;
        }

        $lower = mb_strtolower($line);

        foreach (self::STOP_WORDS as $word) {
            if (str_contains($lower, $word)) {
                return null;
            }
        }

        // Beträge im Format 1.234,56 / 1234,56 / 1234.56 — mindestens
        // einer muss vorkommen, sonst ist es keine Artikelzeile. Datums-
        // angaben (01.07.2026) zählen nicht als Betrag.
        preg_match_all('/\d{1,3}(?:\.\d{3})+,\d{2}|\d+,\d{2}|\d+\.\d{2}(?![\d.])/', $line, $amountMatches, PREG_OFFSET_CAPTURE);
        $amounts = $amountMatches[0];

        if ($amounts === []) {
            return null;
        }

        // Bezeichnung = Text vor dem ersten Betrag (Offset in Bytes —
        // deshalb substr, nicht mb_substr), bereinigt um Positions-
        // nummer und Artikelnummer am Zeilenanfang.
        $head = trim(substr($line, 0, (int) $amounts[0][1]));
        [$articleNo, $name, $qty, $unit] = self::splitHead($head);

        if ($name === null) {
            return null;
        }

        $values = array_map(
            fn (array $match): float => self::toFloat($match[0]),
            $amounts,
        );

        return new ScannedMaterialItem(
            name: $name,
            articleNo: $articleNo,
            unit: $unit,
            priceNet: self::unitPrice($values, $qty),
        );
    }

    /**
     * @return array{0: string|null, 1: string|null, 2: float|null, 3: string|null}
     */
    private static function splitHead(string $head): array
    {
        // Positionsnummer (1–3 Ziffern, ggf. mit Punkt) am Anfang weg.
        $head = (string) preg_replace('/^\d{1,3}[.)]?\s+/u', '', $head);

        // Artikelnummer: erster Token mit Ziffer und mindestens vier
        // Zeichen (z. B. 104711, ZK-25, EPS-W20) vor der Bezeichnung.
        $articleNo = null;

        if (preg_match('/^(?=\S*\d)([A-Za-z0-9][\w.\-\/]{3,})\s+(?=\p{L})/u', $head, $match) === 1) {
            $articleNo = $match[1];
            $head = trim(mb_substr($head, mb_strlen($match[0])));
        }

        // Menge + Einheit am Ende der Bezeichnung (z. B. "… 25 Sack").
        // Ohne Leerraum dazwischen ("3m") ist es eine Maßangabe im Namen.
        $qty = null;
        $unit = null;
        $unitPattern = implode('|', array_map('preg_quote', self::UNITS));

        if (preg_match('/\s(\d+(?:[.,]\d+)?)\s+('.$unitPattern.')\.?\s*$/iu', $head, $match) === 1) {
            $qty = self::toFloat($match[1]);
            $unit = self::normalizeUnit($match[2]);
            $head = trim(mb_substr($head, 0, mb_strlen($head) - mb_strlen($match[0])));
        }

        $name = trim($head, " \t-–·|");

        // Ohne echte Bezeichnung (mindestens drei Buchstaben) keine Zeile.
        if (preg_match_all('/\p{L}/u', $name) < 3) {
            return [null, null, null, null];
        }

        return [$articleNo, $name, $qty, $unit];
    }

    /**
     * Einzelpreis aus den Beträgen der Zeile: passt Menge × A ≈ B,
     * ist A der Einzelpreis; sonst zählt der erste Betrag.
     *
     * @param  list<float>  $values
     */
    private static function unitPrice(array $values, ?float $qty): ?float
    {
        if ($qty !== null && $qty > 0) {
            foreach ($values as $candidate) {
                foreach ($values as $total) {
                    if ($candidate !== $total && abs($candidate * $qty - $total) < 0.01) {
                        return $candidate;
                    }
                }
            }
        }

        $price = $values[0];

        return $price > 0 && $price < 1000000 ? $price : null;
    }

    private static function normalizeUnit(string $unit): string
    {
        $lower = mb_strtolower($unit);

        $canonical = match ($lower) {
            'stück', 'st', 'stg' => 'Stk',
            'm2', 'qm' => 'm²',
            'm3' => 'm³',
            'ltr' => 'l',
            'to' => 't',
            'std' => 'h',
            'pkt' => 'Pkg',
            'rol' => 'Rolle',
            'bd' => 'Bund',
            'sa' => 'Sack',
            'ktn' => 'Karton',
            'pausch' => 'psch',
            default => null,
        };

        if ($canonical !== null) {
            return $canonical;
        }

        // Maßeinheiten bleiben klein (m, kg, l, h, lfm, psch …),
        // Gebinde werden großgeschrieben (Sack, Pkg, Rolle …).
        return in_array($lower, ['m', 'm²', 'm³', 'kg', 't', 'l', 'h', 'lfm', 'psch'], true)
            ? $lower
            : ucfirst($lower);
    }

    private static function toFloat(string $value): float
    {
        // 1.234,56 → 1234.56; 1234.56 bleibt.
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return (float) $value;
    }
}

<?php

namespace App\Support\Money;

/**
 * Zentrale Betragsrechnung (Architekturblatt Abschnitt 5): Eingabe wahlweise
 * netto oder brutto, das Gegenstück und die USt aus dem Steuersatz,
 * kaufmännisch auf zwei Nachkommastellen gerundet. Bei reverse charge ist
 * der Satz 0 und die USt 0. Alle drei Werte werden gespeichert, damit
 * Auswertungen nicht nachrechnen müssen und Rundungen stabil bleiben.
 */
final class MoneyHelper
{
    public static function round(float|string $value): string
    {
        return number_format(round((float) $value, 2, PHP_ROUND_HALF_UP), 2, '.', '');
    }

    /**
     * @return array{net: string, vat: string, gross: string}
     */
    public static function fromNet(float|string $net, float|string $vatRate): array
    {
        $netRounded = round((float) $net, 2, PHP_ROUND_HALF_UP);
        $vat = round($netRounded * (float) $vatRate / 100, 2, PHP_ROUND_HALF_UP);

        return [
            'net' => self::round($netRounded),
            'vat' => self::round($vat),
            'gross' => self::round($netRounded + $vat),
        ];
    }

    /**
     * @return array{net: string, vat: string, gross: string}
     */
    public static function fromGross(float|string $gross, float|string $vatRate): array
    {
        $grossRounded = round((float) $gross, 2, PHP_ROUND_HALF_UP);
        $net = round($grossRounded / (1 + (float) $vatRate / 100), 2, PHP_ROUND_HALF_UP);

        return [
            'net' => self::round($net),
            // USt als Differenz, damit net + vat exakt gross ergibt.
            'vat' => self::round($grossRounded - $net),
            'gross' => self::round($grossRounded),
        ];
    }

    /**
     * Vorzeichenkonvention v1.1: Gutschrift und Storno werden negativ
     * gespeichert — die Auswertung summiert dann stumpf.
     *
     * @param  array{net: string, vat: string, gross: string}  $amounts
     * @return array{net: string, vat: string, gross: string}
     */
    public static function negate(array $amounts): array
    {
        return [
            'net' => self::round(-(float) $amounts['net']),
            'vat' => self::round(-(float) $amounts['vat']),
            'gross' => self::round(-(float) $amounts['gross']),
        ];
    }
}

<?php

namespace App\Support\Calculation;

use App\Enums\RoomMaterial;

/**
 * Einreichplan-Import ohne KI: CAD-Pläne (AutoCAD, ArchiCAD …) tragen
 * ihre Raumstempel als Text im PDF — NAME, Fläche in m², oft die
 * Raumhöhe (RH 2.30m) und der Belag (FLIESEN, HOLZBODEN …). Genau die
 * werden hier aus der Textebene gelesen. Der Umfang steht nicht am
 * Plan und wird über ein Rechteck mit Seitenverhältnis 1,5 geschätzt —
 * die Räume kommen deshalb als „manuell" und ≈ geschätzt herein.
 */
class PlanRoomParser
{
    /** Beschriftungen, die wie Räume aussehen, aber keine sind. */
    private const STOP_NAMES = [
        'GEBÄUDE', 'FRONT', 'HÖHE', 'WNF', 'NFL', 'NUTZFLÄCHE', 'GESAMT',
        'BAUKLASSE', 'STPL', 'GOK', 'TRAUFE', 'FIRST', 'HAUS', 'GRUNDSTÜCK',
        'BAUPLATZ', 'BEBAUT', 'DACH', 'FLÄCHE GESAMT', 'GESCHOSS',
    ];

    /** Angenommenes Seitenverhältnis für den Umfang aus der Fläche. */
    private const ASPECT_RATIO = 1.5;

    /**
     * @return list<array{
     *     name: string, area: float, height: float, perimeter: float,
     *     material: string, surface: string|null
     * }>
     */
    public static function parse(string $text): array
    {
        // Raumstempel: NAME direkt gefolgt von "12,34 m²", optional
        // "RH 2.30m" bzw. "RH ca.190cm" und ein bekannter Belag. Nur
        // bekannte Beläge zulassen — ein beliebiges Großbuchstaben-Wort
        // wäre sonst oft der Name des nächsten Stempels.
        $surfaces = 'FLIESEN|TRAVERTIN|HOLZBODEN|PARKETT|BETON|ESTRICH|TEPPICH|LAMINAT|VINYL|NATURSTEIN|STEIN|MARMOR|GRANIT|KORK';
        $pattern = '/(\p{Lu}[\p{Lu}\p{Ll}ÄÖÜäöüß \/.\-]{2,40}?)\s?(\d{1,3},\d{2})\s*(?:m²|m2)'
            .'(?:\s*RH\s*(?:ca\.?\s*)?(\d+(?:[.,]\d+)?)\s*(m|cm))?'
            .'(?:\s*('.$surfaces.'))?/u';

        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

        $rooms = [];
        $seen = [];

        foreach ($matches as $match) {
            $name = self::cleanName($match[1]);

            if ($name === null) {
                continue;
            }

            $area = (float) str_replace(',', '.', $match[2]);

            if ($area <= 0 || $area > 1000) {
                continue;
            }

            $height = self::height($match[3] ?? '', $match[4] ?? '');
            $surface = self::cleanSurface($match[5] ?? '');

            // Derselbe Stempel taucht auf Plänen mehrfach auf (Bestand,
            // Abbruch, Neubau nebeneinander) — exakt gleiche Räume nur einmal.
            $key = $name.'|'.$match[2].'|'.$height;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $rooms[] = [
                'name' => $name,
                'area' => $area,
                'height' => $height,
                'perimeter' => self::estimatedPerimeter($area),
                'material' => self::material($name, $surface)->value,
                'surface' => $surface,
            ];
        }

        return $rooms;
    }

    private static function cleanName(string $raw): ?string
    {
        // Punktlinien („KÜCHE......") sind Tabellenzeilen der Flächen-
        // aufstellung, keine Stempel — die Summen dort wären doppelt.
        if (str_contains($raw, '..')) {
            return null;
        }

        $name = trim((string) preg_replace('/\s+/', ' ', $raw), " \t.-/");

        if (mb_strlen($name) < 3 || preg_match('/\d/', $name) === 1) {
            return null;
        }

        $upper = mb_strtoupper($name);

        foreach (self::STOP_NAMES as $stop) {
            if (str_contains($upper, $stop)) {
                return null;
            }
        }

        // Plan-Beschriftungen sind meist versal — hübsch als Wortanfang groß.
        return mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8');
    }

    private static function height(string $value, string $unit): float
    {
        if ($value === '') {
            return 2.5;
        }

        $height = (float) str_replace(',', '.', $value);

        if (mb_strtolower($unit) === 'cm') {
            $height /= 100;
        }

        return $height >= 0.5 && $height <= 20 ? round($height, 2) : 2.5;
    }

    private static function cleanSurface(string $raw): ?string
    {
        $surface = trim($raw);

        return $surface === '' ? null : mb_convert_case(mb_strtolower($surface), MB_CASE_TITLE, 'UTF-8');
    }

    private static function material(string $name, ?string $surface): RoomMaterial
    {
        if (preg_match('/bad|wc\b|dusche|sauna|waschk/iu', $name) === 1) {
            return RoomMaterial::WallTiles;
        }

        $surfaceLower = mb_strtolower((string) $surface);

        if (str_contains($surfaceLower, 'holz') || str_contains($surfaceLower, 'parkett')) {
            return RoomMaterial::Parquet;
        }

        if ($surfaceLower !== '') {
            // Fliesen, Travertin, Beton, Stein … — alles Fliesenleger-Terrain.
            return RoomMaterial::FloorTiles;
        }

        return RoomMaterial::Parquet;
    }

    /**
     * Umfang aus der Fläche über ein Rechteck mit Seitenverhältnis 1,5 —
     * eine bewusste Schätzung, am Plan steht der Umfang nicht.
     */
    private static function estimatedPerimeter(float $area): float
    {
        $long = sqrt($area * self::ASPECT_RATIO);
        $short = sqrt($area / self::ASPECT_RATIO);

        return round(($long + $short) * 2, 2);
    }
}

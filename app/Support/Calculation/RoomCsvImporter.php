<?php

namespace App\Support\Calculation;

use App\Enums\RoomMaterial;

/**
 * CSV/TXT-Import für Räume: erkennt Komma, Strichpunkt oder Tabulator
 * als Trenner und deutsche wie englische Spaltennamen über
 * Teilstring-Suche (länge/length, breite/width, höhe/height,
 * material/belag, kanten/edges, tür/door, öffnung/opening …).
 */
class RoomCsvImporter
{
    /**
     * @return array{rows: list<array<string, mixed>>, skipped: int}
     */
    public static function parse(string $csv): array
    {
        $lines = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', trim($csv)) ?: [],
            fn (string $line): bool => trim($line) !== '',
        ));

        if (count($lines) < 2) {
            return ['rows' => [], 'skipped' => 0];
        }

        $delimiter = self::delimiter($lines[0]);
        $headers = array_map(
            fn (?string $header): string => mb_strtolower(trim((string) $header, " \t\"'")),
            str_getcsv($lines[0], $delimiter, '"', '\\'),
        );

        $columns = [
            'name' => self::findColumn($headers, ['name', 'raum', 'room', 'bezeichnung']),
            'length' => self::findColumn($headers, ['länge', 'laenge', 'length']),
            'width' => self::findColumn($headers, ['breite', 'width']),
            'height' => self::findColumn($headers, ['höhe', 'hoehe', 'height']),
            'material' => self::findColumn($headers, ['material', 'belag']),
            'edges' => self::findColumn($headers, ['kanten', 'edges']),
            'door_width' => self::findColumn($headers, ['tür', 'tuer', 'door']),
            'opening_area' => self::findColumn($headers, ['öffnung', 'oeffnung', 'opening', 'fenster', 'window']),
        ];

        $rows = [];
        $skipped = 0;

        foreach (array_slice($lines, 1) as $line) {
            $cells = str_getcsv($line, $delimiter, '"', '\\');

            $length = self::number(self::cell($cells, $columns['length']));
            $width = self::number(self::cell($cells, $columns['width']));
            $height = self::number(self::cell($cells, $columns['height'])) ?? 2.5;

            if ($length === null || $width === null || $length <= 0 || $width <= 0 || $height <= 0) {
                $skipped++;

                continue;
            }

            $name = trim((string) self::cell($cells, $columns['name']));

            $rows[] = [
                'name' => $name !== '' ? $name : 'Raum '.(count($rows) + 1),
                'shape' => 'rectangle',
                'material' => self::material((string) self::cell($cells, $columns['material']))->value,
                'length' => $length,
                'width' => $width,
                'height' => $height,
                'edges' => (int) (self::number(self::cell($cells, $columns['edges'])) ?? 0),
                'door_width' => self::number(self::cell($cells, $columns['door_width'])) ?? 0,
                'opening_area' => self::number(self::cell($cells, $columns['opening_area'])) ?? 0,
            ];
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $needles
     */
    private static function findColumn(array $headers, array $needles): ?int
    {
        foreach ($headers as $index => $header) {
            foreach ($needles as $needle) {
                if (str_contains($header, $needle)) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string|null>  $cells
     */
    private static function cell(array $cells, ?int $index): ?string
    {
        return $index !== null ? ($cells[$index] ?? null) : null;
    }

    private static function delimiter(string $headerLine): string
    {
        foreach ([';', "\t", ','] as $candidate) {
            if (substr_count($headerLine, $candidate) >= 1) {
                return $candidate;
            }
        }

        return ',';
    }

    private static function number(?string $value): ?float
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private static function material(string $value): RoomMaterial
    {
        $value = mb_strtolower(trim($value));

        return match (true) {
            str_contains($value, 'wand') => RoomMaterial::WallTiles,
            str_contains($value, 'fliese') || str_contains($value, 'tile') => RoomMaterial::FloorTiles,
            default => RoomMaterial::Parquet,
        };
    }
}

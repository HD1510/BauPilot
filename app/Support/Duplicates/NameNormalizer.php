<?php

namespace App\Support\Duplicates;

/**
 * Normalisiert Namen für die Dubletten-Erkennung (Architekturblatt
 * Abschnitt 5): Kleinschreibung, Umlaute aufgelöst, Satzzeichen entfernt,
 * Leerzeichen bereinigt.
 */
class NameNormalizer
{
    public static function normalize(string $name): string
    {
        $value = mb_strtolower(trim($name));

        $value = strtr($value, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'á' => 'a', 'à' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u',
            'ç' => 'c', 'š' => 's', 'ć' => 'c', 'č' => 'c', 'ž' => 'z', 'đ' => 'd',
        ]);

        $value = (string) preg_replace('/[^a-z0-9 ]+/', ' ', $value);

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}

<?php

namespace App\Support\Pdf;

use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Textebene aus einem PDF ziehen. CAD-Pläne (Einreichpläne) haben
 * riesige Vektor-Inhalte — der Parser braucht dafür kurzzeitig mehr
 * Speicher, als das übliche PHP-FPM-Limit (128 MB) hergibt. Das Limit
 * wird deshalb nur für die Dauer des Parsens angehoben.
 */
class PdfTextExtractor
{
    private const MEMORY_LIMIT = '768M';

    public static function fromContent(string $content): string
    {
        return self::withMemory(fn (): string => trim((new PdfParser)->parseContent($content)->getText()));
    }

    public static function fromFile(string $path): string
    {
        return self::withMemory(fn (): string => trim((new PdfParser)->parseFile($path)->getText()));
    }

    /**
     * Limit anheben und angehoben lassen: ini_set gilt nur für den
     * laufenden Request, und ein Absenken nach dem Parsen würde die
     * nächste Allokation treffen, solange der Parser-Speicher lebt.
     *
     * @param  callable(): string  $parse
     */
    private static function withMemory(callable $parse): string
    {
        $current = (string) ini_get('memory_limit');

        if ($current !== '-1' && self::toBytes($current) < self::toBytes(self::MEMORY_LIMIT)) {
            ini_set('memory_limit', self::MEMORY_LIMIT);
        }

        try {
            return $parse();
        } catch (Throwable) {
            return '';
        }
    }

    private static function toBytes(string $limit): int
    {
        $value = (int) $limit;

        return match (strtoupper(substr(trim($limit), -1))) {
            'G' => $value * 1024 ** 3,
            'M' => $value * 1024 ** 2,
            'K' => $value * 1024,
            default => $value,
        };
    }
}

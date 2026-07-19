<?php

namespace App\Support\MaterialScan;

use App\Support\Pdf\PdfTextExtractor;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Scan-Leiter für Material-Positionen (Preislisten, Rechnungen,
 * Lieferscheine) — erst die exakten, kostenlosen Stufen, KI nur als
 * Fallback:
 *
 *  1. E-Rechnung (ZUGFeRD/Factur-X): Positionen aus dem XML — exakt
 *  2. Text-PDF mit Zeilen-Heuristik
 *  3. KI (Claude) — für Fotos, gescannte PDFs und leere Texttreffer;
 *     ohne ANTHROPIC_API_KEY entfällt nur diese Stufe.
 */
class MaterialScanPipeline
{
    public function __construct(
        private ERechnungItemsReader $eRechnung,
        private MaterialItemScanner $scanner,
    ) {}

    /**
     * @return array{items: list<ScannedMaterialItem>, source: string}
     */
    public function run(UploadedFile $file): array
    {
        if ((string) $file->getMimeType() !== 'application/pdf') {
            // Fotos haben keine Textschicht — hier hilft nur die KI.
            if ($this->scanner->enabled()) {
                return ['items' => $this->scanner->scan($file), 'source' => 'ki'];
            }

            throw new RuntimeException('Für Fotos wird die KI-Erkennung benötigt (ANTHROPIC_API_KEY). PDF-Listen mit Textinhalt funktionieren auch ohne.');
        }

        $content = (string) file_get_contents((string) $file->getRealPath());

        $fromXml = $this->eRechnung->readItems($content);

        if ($fromXml !== null && $fromXml !== []) {
            return ['items' => $fromXml, 'source' => 'e_rechnung'];
        }

        $text = $this->extractText($content);

        if ($text !== '') {
            $parsed = MaterialItemsParser::parse($text);

            if ($parsed !== []) {
                return ['items' => $parsed, 'source' => 'text'];
            }

            if ($this->scanner->enabled()) {
                return ['items' => $this->scanner->scan($file), 'source' => 'ki'];
            }

            throw new RuntimeException('Im PDF wurden keine Artikelzeilen erkannt. Mit KI-Erkennung (ANTHROPIC_API_KEY) klappen auch ungewöhnliche Layouts — oder die Artikel manuell erfassen.');
        }

        // PDF ohne Textschicht (eingescannt): nur die KI liest Pixel.
        if ($this->scanner->enabled()) {
            return ['items' => $this->scanner->scan($file), 'source' => 'ki'];
        }

        throw new RuntimeException('Das PDF enthält keinen lesbaren Text (vermutlich ein Scan). Dafür wird die KI-Erkennung benötigt (ANTHROPIC_API_KEY) — oder die Artikel manuell erfassen.');
    }

    private function extractText(string $pdfContent): string
    {
        return PdfTextExtractor::fromContent($pdfContent);
    }
}

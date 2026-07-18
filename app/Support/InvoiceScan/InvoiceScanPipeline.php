<?php

namespace App\Support\InvoiceScan;

use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Scan-Leiter für Belege (Eingangs-/Ausgangsrechnungen, Angebote):
 * erst die exakten, kostenlosen Stufen, KI nur als Fallback.
 *
 *  1. E-Rechnung (ZUGFeRD/Factur-X im PDF eingebettet) — exakt
 *  2. Text-PDF mit Mustererkennung + Partnersuche im Text
 *  3. KI (Claude) — für Fotos, gescannte PDFs und dünne Texttreffer;
 *     ohne ANTHROPIC_API_KEY entfällt nur diese Stufe.
 */
class InvoiceScanPipeline
{
    public function __construct(
        private ERechnungReader $eRechnung,
        private InvoiceScanner $scanner,
        private PartnerMatcher $matcher,
        private CompanyContext $context,
    ) {}

    /**
     * @return array{invoice: ScannedInvoice, source: string}
     */
    public function run(UploadedFile $file, ScanDocumentKind $kind): array
    {
        if ((string) $file->getMimeType() !== 'application/pdf') {
            // Fotos haben keine Textschicht — hier hilft nur die KI.
            if ($this->scanner->enabled()) {
                return ['invoice' => $this->scanner->scan($file, $kind), 'source' => 'ki'];
            }

            throw new RuntimeException('Für Fotos wird die KI-Erkennung benötigt (ANTHROPIC_API_KEY). PDF-Belege mit Textinhalt funktionieren auch ohne.');
        }

        $content = (string) file_get_contents((string) $file->getRealPath());

        $fromXml = $this->eRechnung->read($content, $kind);

        if ($fromXml !== null) {
            return ['invoice' => $fromXml, 'source' => 'e_rechnung'];
        }

        $text = $this->extractText($content);

        if ($text !== '') {
            $company = $this->context->requireCompany();
            $known = $this->matcher->findInText($kind->partnerModel(), $text);
            $parsed = TextInvoiceParser::parse(
                $text,
                $kind,
                $known?->getAttribute('name'),
                $company->vat_id,
                $company->name,
            );

            // Starker Treffer: Betrag plus Partner oder Nummer — dann
            // braucht es keine KI. Sonst darf die KI übernehmen.
            if ($this->isStrong($parsed) || ! $this->scanner->enabled()) {
                return ['invoice' => $parsed, 'source' => 'text'];
            }

            return ['invoice' => $this->scanner->scan($file, $kind), 'source' => 'ki'];
        }

        // PDF ohne Textschicht (eingescannt): nur die KI liest Pixel.
        if ($this->scanner->enabled()) {
            return ['invoice' => $this->scanner->scan($file, $kind), 'source' => 'ki'];
        }

        throw new RuntimeException('Das PDF enthält keinen lesbaren Text (vermutlich ein Scan). Dafür wird die KI-Erkennung benötigt (ANTHROPIC_API_KEY) — oder den Beleg manuell erfassen.');
    }

    private function extractText(string $pdfContent): string
    {
        try {
            $text = (new PdfParser)->parseContent($pdfContent)->getText();
        } catch (Throwable) {
            return '';
        }

        return trim($text);
    }

    private function isStrong(ScannedInvoice $invoice): bool
    {
        $hasAmount = $invoice->gross !== null || $invoice->net !== null;
        $hasIdentity = $invoice->partnerName !== null || $invoice->docNumber !== null;

        return $hasAmount && $hasIdentity;
    }
}

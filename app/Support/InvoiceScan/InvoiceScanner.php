<?php

namespace App\Support\InvoiceScan;

use Anthropic\Client;
use Anthropic\Messages\Base64ImageSource;
use Anthropic\Messages\Base64ImageSource\MediaType;
use Anthropic\Messages\Base64PDFSource;
use Anthropic\Messages\DocumentBlockParam;
use Anthropic\Messages\ImageBlockParam;
use Anthropic\Messages\JSONOutputFormat;
use Anthropic\Messages\MessageParam;
use Anthropic\Messages\OutputConfig;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\TextBlockParam;
use Anthropic\Messages\ThinkingConfigAdaptive;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

/**
 * KI-Stufe der Scan-Leiter: liest einen Beleg (PDF oder Foto) per
 * Claude aus — Partner, Konditionen und Belegkopf. Je nach Belegart ist
 * der gesuchte Partner der Aussteller (Eingangsrechnung) oder der
 * Empfänger (eigene Ausgangsrechnung/Angebot). Ohne ANTHROPIC_API_KEY
 * ist die Stufe abgeschaltet — die übrigen Stufen bleiben unberührt.
 */
class InvoiceScanner
{
    public function enabled(): bool
    {
        return (string) config('services.anthropic.key') !== '';
    }

    public function scan(UploadedFile $file, ScanDocumentKind $kind): ScannedInvoice
    {
        if (! $this->enabled()) {
            throw new RuntimeException('KI-Erkennung ist nicht konfiguriert (ANTHROPIC_API_KEY fehlt).');
        }

        $data = base64_encode((string) file_get_contents($file->getRealPath()));

        try {
            $client = new Client(apiKey: (string) config('services.anthropic.key'));

            $message = $client->messages->create(
                maxTokens: 2048,
                messages: [MessageParam::with(
                    content: [
                        $this->fileBlock($file, $data),
                        TextBlockParam::with(text: 'Lies diesen Beleg aus und liefere das JSON.'),
                    ],
                    role: 'user',
                )],
                model: (string) config('services.anthropic.model'),
                outputConfig: OutputConfig::with(format: JSONOutputFormat::with(schema: $this->schema())),
                system: $this->systemPrompt($kind),
                thinking: ThinkingConfigAdaptive::with(),
            );
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('Der Beleg konnte nicht ausgelesen werden. Bitte manuell erfassen oder erneut versuchen.');
        }

        foreach ($message->content as $block) {
            if ($block instanceof TextBlock) {
                $decoded = json_decode($block->text, true);

                if (is_array($decoded)) {
                    /** @var array<string, mixed> $decoded */
                    return ScannedInvoice::fromArray($decoded);
                }
            }
        }

        throw new RuntimeException('Die Antwort der KI war unbrauchbar. Bitte manuell erfassen.');
    }

    private function fileBlock(UploadedFile $file, string $base64): DocumentBlockParam|ImageBlockParam
    {
        $mime = (string) $file->getMimeType();

        if ($mime === 'application/pdf') {
            return DocumentBlockParam::with(source: Base64PDFSource::with(data: $base64));
        }

        return ImageBlockParam::with(source: Base64ImageSource::with(data: $base64, mediaType: MediaType::from($mime)));
    }

    private function systemPrompt(ScanDocumentKind $kind): string
    {
        $partnerRule = match ($kind) {
            ScanDocumentKind::IncomingInvoice => <<<'RULE'
            Das Dokument ist eine EINGANGSRECHNUNG (Lieferantenrechnung an unser Bauunternehmen).
            - partner_name: der Rechnungssteller (Absender), nie der Empfänger. Firmenname wie gedruckt, ohne Adresszusätze.
            - partner_uid: UID-Nummer des Rechnungsstellers (z. B. ATU12345678), falls angegeben.
            - partner_iban: IBAN des Rechnungsstellers, falls angegeben, ohne Leerzeichen.
            RULE,
            ScanDocumentKind::OutgoingInvoice => <<<'RULE'
            Das Dokument ist eine AUSGANGSRECHNUNG unseres eigenen Bauunternehmens an einen Kunden.
            - partner_name: der RECHNUNGSEMPFÄNGER (Kunde), nie der Absender. Name wie gedruckt, ohne Adresszusätze.
            - partner_uid: UID-Nummer des Empfängers, falls angegeben (die UID des Absenders ignorieren).
            - partner_iban: immer null (die IBAN auf dem Beleg gehört dem Absender).
            RULE,
            ScanDocumentKind::Offer => <<<'RULE'
            Das Dokument ist ein ANGEBOT unseres eigenen Bauunternehmens an einen Kunden.
            - partner_name: der ANGEBOTSEMPFÄNGER (Kunde), nie der Absender.
            - partner_uid: UID-Nummer des Empfängers, falls angegeben (die UID des Absenders ignorieren).
            - partner_iban: immer null.
            - doc_number: die Angebotsnummer.
            - subject: die angebotene Leistung bzw. das Bauvorhaben in wenigen Worten (inkl. Ort, falls genannt).
            RULE,
        };

        return $partnerRule."\n".<<<'PROMPT'

        Weitere Regeln:
        - payment_target_days: Zahlungsziel in Tagen (z. B. aus "zahlbar innerhalb 30 Tagen" oder aus Beleg- und Fälligkeitsdatum errechnet).
        - skonto_percent / skonto_days: falls Skonto angeboten wird (z. B. "3 % Skonto bei Zahlung innerhalb 14 Tagen"), sonst null.
        - doc_number: die Belegnummer (Rechnungs- bzw. Angebotsnummer).
        - doc_date: Belegdatum als YYYY-MM-DD.
        - net, vat_rate, gross: Beträge als Zahlen mit Punkt als Dezimaltrenner. vat_rate in Prozent (z. B. 20).
        - reverse_charge: true bei Übergang der Steuerschuld (§ 19 UStG, Reverse Charge, Bauleistung ohne USt-Ausweis), sonst false.
        - subject: kurzer Betreff (Leistung/Lieferung in wenigen Worten), auf Deutsch.
        - Was nicht sicher erkennbar ist, bleibt null. Nichts erfinden.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $nullable = fn (string $type): array => ['type' => [$type, 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'partner_name' => $nullable('string'),
                'partner_uid' => $nullable('string'),
                'partner_iban' => $nullable('string'),
                'payment_target_days' => $nullable('integer'),
                'skonto_percent' => $nullable('number'),
                'skonto_days' => $nullable('integer'),
                'doc_number' => $nullable('string'),
                'doc_date' => $nullable('string'),
                'net' => $nullable('number'),
                'vat_rate' => $nullable('number'),
                'gross' => $nullable('number'),
                'reverse_charge' => ['type' => 'boolean'],
                'subject' => $nullable('string'),
            ],
            'required' => [
                'partner_name', 'partner_uid', 'partner_iban', 'payment_target_days',
                'skonto_percent', 'skonto_days', 'doc_number', 'doc_date',
                'net', 'vat_rate', 'gross', 'reverse_charge', 'subject',
            ],
        ];
    }
}

<?php

namespace App\Support\MaterialScan;

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
 * KI-Stufe des Material-Scans: liest die einzelnen Positionen einer
 * Preisliste, Rechnung oder eines Lieferscheins (PDF oder Foto) per
 * Claude aus. Ohne ANTHROPIC_API_KEY ist die Stufe abgeschaltet — die
 * übrigen Stufen bleiben unberührt.
 */
class MaterialItemScanner
{
    public function enabled(): bool
    {
        return (string) config('services.anthropic.key') !== '';
    }

    /**
     * @return list<ScannedMaterialItem>
     */
    public function scan(UploadedFile $file): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('KI-Erkennung ist nicht konfiguriert (ANTHROPIC_API_KEY fehlt).');
        }

        $data = base64_encode((string) file_get_contents($file->getRealPath()));

        try {
            $client = new Client(apiKey: (string) config('services.anthropic.key'));

            $message = $client->messages->create(
                maxTokens: 8192,
                messages: [MessageParam::with(
                    content: [
                        $this->fileBlock($file, $data),
                        TextBlockParam::with(text: 'Lies alle Material-Positionen aus und liefere das JSON.'),
                    ],
                    role: 'user',
                )],
                model: (string) config('services.anthropic.model'),
                outputConfig: OutputConfig::with(format: JSONOutputFormat::with(schema: $this->schema())),
                system: $this->systemPrompt(),
                thinking: ThinkingConfigAdaptive::with(),
            );
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('Das Dokument konnte nicht ausgelesen werden. Bitte erneut versuchen oder die Artikel manuell erfassen.');
        }

        foreach ($message->content as $block) {
            if ($block instanceof TextBlock) {
                $decoded = json_decode($block->text, true);

                if (is_array($decoded) && is_array($decoded['items'] ?? null)) {
                    return $this->itemsFrom($decoded['items']);
                }
            }
        }

        throw new RuntimeException('Die Antwort der KI war unbrauchbar. Bitte die Artikel manuell erfassen.');
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @return list<ScannedMaterialItem>
     */
    private function itemsFrom(array $raw): array
    {
        $items = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            /** @var array<string, mixed> $entry */
            $item = ScannedMaterialItem::fromArray($entry);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function fileBlock(UploadedFile $file, string $base64): DocumentBlockParam|ImageBlockParam
    {
        $mime = (string) $file->getMimeType();

        if ($mime === 'application/pdf') {
            return DocumentBlockParam::with(source: Base64PDFSource::with(data: $base64));
        }

        return ImageBlockParam::with(source: Base64ImageSource::with(data: $base64, mediaType: MediaType::from($mime)));
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        Das Dokument ist eine Lieferanten-Preisliste, Rechnung oder ein Lieferschein eines Baustoffhändlers.
        Extrahiere JEDE einzelne Material-Position (Artikelzeile) als Eintrag in items:
        - name: die Artikelbezeichnung wie gedruckt, ohne Mengen- und Preisangaben.
        - article_no: die Artikelnummer des Lieferanten, falls angegeben, sonst null.
        - package_unit: die Einheit bzw. das Gebinde (z. B. Stk, m², m³, kg, t, l, lfm, Sack, Pkg, Rolle, Pal), sonst null.
        - price_net: der NETTO-EINZELPREIS je Einheit als Zahl mit Punkt als Dezimaltrenner — nicht der Zeilen-Gesamtbetrag, nicht brutto. Falls nicht sicher erkennbar: null.
        Keine Summen-, Rabatt-, Versand- oder Pfandzeilen aufnehmen. Nichts erfinden.
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
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'article_no' => $nullable('string'),
                            'package_unit' => $nullable('string'),
                            'price_net' => $nullable('number'),
                        ],
                        'required' => ['name', 'article_no', 'package_unit', 'price_net'],
                    ],
                ],
            ],
            'required' => ['items'],
        ];
    }
}

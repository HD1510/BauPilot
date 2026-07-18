<?php

namespace App\Support\InvoiceScan;

use App\Models\Customer;
use App\Models\Supplier;
use App\Support\Duplicates\DuplicateFinder;
use App\Support\Duplicates\NameNormalizer;
use Illuminate\Database\Eloquent\Model;

/**
 * Ordnet den erkannten Partnernamen dem Bestand zu (Lieferanten oder
 * Kunden): exakter Treffer (normalisiert) zuerst, dahinter ähnliche
 * Kandidaten über den DuplicateFinder. Zusammengeführt wird nie
 * automatisch — die Auswahl bestätigt immer ein Mensch (Architekturblatt
 * Abschnitt 5).
 */
class PartnerMatcher
{
    public function __construct(private DuplicateFinder $finder) {}

    /**
     * @param  class-string<Supplier|Customer>  $modelClass
     * @return list<array{id: int, name: string, similarity: float, payment_target_days: int, default_cost_type_id: int|null, skonto_percent: string|null, skonto_days: int|null}>
     */
    public function match(string $modelClass, ?string $name): array
    {
        if ($name === null || trim($name) === '') {
            return [];
        }

        $matches = [];
        $normalized = NameNormalizer::normalize($name);

        $exact = $modelClass::query()->where('normalized_name', $normalized)->first();

        if ($exact !== null) {
            $matches[] = $this->entry($exact, 1.0);
        }

        $similar = $this->finder->findSimilar($modelClass, $name, $exact?->id);

        // Ein Rutsch statt fünf Einzelabfragen (N+1).
        $partners = $modelClass::query()->whereIn('id', $similar->pluck('id'))->get()->keyBy('id');

        foreach ($similar as $candidate) {
            $partner = $partners->get($candidate['id']);

            if ($partner !== null) {
                $matches[] = $this->entry($partner, $candidate['similarity']);
            }
        }

        return $matches;
    }

    /**
     * Bestehenden Partner direkt im Belegtext finden — sicherer als jede
     * Namens-Schätzung: steht „Huber Transporte" im Text, ist der
     * Treffer eindeutig. Der längste Name gewinnt (spezifischster).
     *
     * @param  class-string<Supplier|Customer>  $modelClass
     */
    public function findInText(string $modelClass, string $text): Supplier|Customer|null
    {
        $normalizedText = ' '.NameNormalizer::normalize($text).' ';
        $best = null;
        $bestLength = 0;

        $query = $modelClass::query();

        if ($modelClass === Supplier::class) {
            $query->where('active', true);
        }

        // Nur die Spalten fürs Namens-Matching laden — nicht jede Zeile
        // komplett hydrieren.
        foreach ($query->get(['id', 'name', 'normalized_name']) as $partner) {
            $name = (string) $partner->getAttribute('normalized_name');

            // Zu kurze Namen träfen überall („bau") — mindestens 5 Zeichen.
            if (strlen($name) < 5) {
                continue;
            }

            if (str_contains($normalizedText, ' '.$name.' ') && strlen($name) > $bestLength) {
                $best = $partner;
                $bestLength = strlen($name);
            }
        }

        return $best;
    }

    /**
     * @return array{id: int, name: string, similarity: float, payment_target_days: int, default_cost_type_id: int|null, skonto_percent: string|null, skonto_days: int|null}
     */
    private function entry(Model $partner, float $similarity): array
    {
        return [
            'id' => (int) $partner->getAttribute('id'),
            'name' => (string) $partner->getAttribute('name'),
            'similarity' => round($similarity, 2),
            'payment_target_days' => (int) $partner->getAttribute('payment_target_days'),
            // Nur Lieferanten kennen Kostenart und Skonto — beim Kunden null.
            'default_cost_type_id' => $partner instanceof Supplier ? $partner->default_cost_type_id : null,
            'skonto_percent' => $partner instanceof Supplier ? $partner->skonto_percent : null,
            'skonto_days' => $partner instanceof Supplier ? $partner->skonto_days : null,
        ];
    }
}

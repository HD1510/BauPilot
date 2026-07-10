<?php

namespace App\Support\InvoiceScan;

use App\Models\Supplier;
use App\Support\Duplicates\DuplicateFinder;
use App\Support\Duplicates\NameNormalizer;

/**
 * Ordnet den erkannten Lieferantennamen dem Bestand zu: exakter Treffer
 * (normalisiert) zuerst, dahinter ähnliche Kandidaten über den
 * DuplicateFinder. Zusammengeführt wird nie automatisch — die Auswahl
 * bestätigt immer ein Mensch (Architekturblatt Abschnitt 5).
 */
class SupplierMatcher
{
    public function __construct(private DuplicateFinder $finder) {}

    /**
     * Bestehenden Lieferanten direkt im Rechnungstext finden — sicherer
     * als jede Namens-Schätzung: steht „Huber Transporte" im Text, ist
     * der Treffer eindeutig. Der längste Name gewinnt (spezifischster).
     */
    public function findInText(string $text): ?Supplier
    {
        $normalizedText = ' '.NameNormalizer::normalize($text).' ';
        $best = null;
        $bestLength = 0;

        foreach (Supplier::query()->where('active', true)->get() as $supplier) {
            $name = $supplier->normalized_name;

            // Zu kurze Namen träfen überall („bau") — mindestens 5 Zeichen.
            if (strlen($name) < 5) {
                continue;
            }

            if (str_contains($normalizedText, ' '.$name.' ') && strlen($name) > $bestLength) {
                $best = $supplier;
                $bestLength = strlen($name);
            }
        }

        return $best;
    }

    /**
     * @return list<array{id: int, name: string, similarity: float, payment_target_days: int, default_cost_type_id: int|null, skonto_percent: string|null, skonto_days: int|null}>
     */
    public function match(?string $name): array
    {
        if ($name === null || trim($name) === '') {
            return [];
        }

        $matches = [];
        $normalized = NameNormalizer::normalize($name);

        $exact = Supplier::query()->where('normalized_name', $normalized)->first();

        if ($exact !== null) {
            $matches[] = $this->entry($exact, 1.0);
        }

        $similar = $this->finder->findSimilar(Supplier::class, $name, $exact?->id);

        foreach ($similar as $candidate) {
            $supplier = Supplier::query()->find($candidate['id']);

            if ($supplier !== null) {
                $matches[] = $this->entry($supplier, $candidate['similarity']);
            }
        }

        return $matches;
    }

    /**
     * @return array{id: int, name: string, similarity: float, payment_target_days: int, default_cost_type_id: int|null, skonto_percent: string|null, skonto_days: int|null}
     */
    private function entry(Supplier $supplier, float $similarity): array
    {
        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'similarity' => round($similarity, 2),
            'payment_target_days' => $supplier->payment_target_days,
            'default_cost_type_id' => $supplier->default_cost_type_id,
            'skonto_percent' => $supplier->skonto_percent,
            'skonto_days' => $supplier->skonto_days,
        ];
    }
}

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

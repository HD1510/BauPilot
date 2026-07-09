<?php

namespace App\Support\Duplicates;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dubletten-Vorschlag nach Architekturblatt Abschnitt 5: normalisierte
 * Namen werden per Trigramm-Ähnlichkeit verglichen. Auf PostgreSQL macht
 * das pg_trgm direkt in der Datenbank; auf anderen Treibern (sqlite in
 * Tests) rechnet PHP dieselbe Metrik. Zusammengeführt wird nie automatisch —
 * der Vorschlag verlangt immer eine Bestätigung.
 */
class DuplicateFinder
{
    public const THRESHOLD = 0.45;

    /**
     * @param  class-string<Model>  $modelClass  Modell mit normalized_name (BelongsToCompany begrenzt auf die aktive Firma)
     * @return Collection<int, array{id: int, name: string, similarity: float}>
     */
    public function findSimilar(string $modelClass, string $name, ?int $ignoreId = null): Collection
    {
        $normalized = NameNormalizer::normalize($name);

        if ($normalized === '') {
            return collect();
        }

        $query = $modelClass::query()
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId));

        if (DB::connection()->getDriverName() === 'pgsql' && $this->pgTrgmAvailable()) {
            /** @var Collection<int, Model> $matches */
            $matches = $query
                ->select(['id', 'name'])
                ->selectRaw('similarity(normalized_name, ?) as similarity', [$normalized])
                ->whereRaw('similarity(normalized_name, ?) >= ?', [$normalized, self::THRESHOLD])
                ->orderByDesc('similarity')
                ->limit(5)
                ->get();

            return $matches->map(fn (Model $model): array => [
                'id' => (int) $model->getAttribute('id'),
                'name' => (string) $model->getAttribute('name'),
                'similarity' => round((float) $model->getAttribute('similarity'), 2),
            ])->values();
        }

        return $query
            ->get(['id', 'name', 'normalized_name'])
            ->map(fn (Model $model): array => [
                'id' => (int) $model->getAttribute('id'),
                'name' => (string) $model->getAttribute('name'),
                'similarity' => round(self::trigramSimilarity($normalized, (string) $model->getAttribute('normalized_name')), 2),
            ])
            ->filter(fn (array $match): bool => $match['similarity'] >= self::THRESHOLD)
            ->sortByDesc('similarity')
            ->take(5)
            ->values();
    }

    /**
     * Trigramm-Ähnlichkeit wie pg_trgm: Wörter werden mit zwei Leerzeichen
     * vorne und einem hinten gepolstert, verglichen wird die Jaccard-Menge.
     */
    public static function trigramSimilarity(string $a, string $b): float
    {
        $setA = self::trigrams($a);
        $setB = self::trigrams($b);

        if ($setA === [] || $setB === []) {
            return 0.0;
        }

        $intersection = count(array_intersect_key($setA, $setB));
        $union = count($setA + $setB);

        return $intersection / $union;
    }

    /**
     * @return array<string, true>
     */
    private static function trigrams(string $value): array
    {
        $trigrams = [];

        foreach (explode(' ', $value) as $word) {
            if ($word === '') {
                continue;
            }

            $padded = '  '.$word.' ';
            $length = strlen($padded);

            for ($i = 0; $i <= $length - 3; $i++) {
                $trigrams[substr($padded, $i, 3)] = true;
            }
        }

        return $trigrams;
    }

    private function pgTrgmAvailable(): bool
    {
        static $available = null;

        if ($available === null) {
            $available = DB::selectOne("select exists(select 1 from pg_extension where extname = 'pg_trgm') as installed")->installed === true;
        }

        return (bool) $available;
    }
}

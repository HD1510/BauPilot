<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Support\MaterialScan\MaterialScanPipeline;
use App\Support\MaterialScan\ScannedMaterialItem;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Preisliste/Rechnung einlesen: Die Scan-Leiter erkennt die einzelnen
 * Positionen, die Oberfläche zeigt sie als Übersicht — je Zeile
 * entscheidet der Mensch, ob daraus ein Artikel wird.
 */
class MaterialScanController extends Controller
{
    public function __construct(private MaterialScanPipeline $pipeline) {}

    public function scan(Request $request): JsonResponse
    {
        Gate::authorize('create', Material::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
        ], [], ['file' => 'Datei']);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            ['items' => $items, 'source' => $source] = $this->pipeline->run($file);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'source' => $source,
            'items' => $this->withExistsFlag($items),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        Gate::authorize('create', Material::class);

        $validated = $request->validate([
            'supplier_id' => [
                'required',
                Rule::exists('suppliers', 'id')->where('company_id', app(CompanyContext::class)->requireId()),
            ],
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.article_no' => ['nullable', 'string', 'max:100'],
            'items.*.package_unit' => ['nullable', 'string', 'max:100'],
            'items.*.price_net' => ['nullable', 'numeric', 'between:0,1000000'],
        ], [], [
            'supplier_id' => 'Lieferant',
            'items' => 'Artikel',
        ]);

        $existing = $this->existingNames();
        $created = 0;
        $skipped = 0;

        foreach ($validated['items'] as $item) {
            $key = mb_strtolower(trim($item['name']));

            if (isset($existing[$key])) {
                $skipped++;

                continue;
            }

            Material::create([
                'supplier_id' => $validated['supplier_id'],
                'name' => trim($item['name']),
                'article_no' => $item['article_no'] ?? null,
                'package_unit' => $item['package_unit'] ?? null,
                'price_net' => $item['price_net'] ?? null,
                'active' => true,
            ]);

            $existing[$key] = true;
            $created++;
        }

        $message = $created === 1 ? '1 Artikel angelegt.' : "{$created} Artikel angelegt.";

        if ($skipped > 0) {
            $message .= " {$skipped} übersprungen — bereits vorhanden.";
        }

        return back()->with('success', $message);
    }

    /**
     * Bestehende Artikel markieren — die Oberfläche wählt sie ab, damit
     * keine Dubletten entstehen.
     *
     * @param  list<ScannedMaterialItem>  $items
     * @return list<array<string, mixed>>
     */
    private function withExistsFlag(array $items): array
    {
        $existing = $this->existingNames();

        return array_map(function (ScannedMaterialItem $item) use ($existing): array {
            return [
                ...$item->toArray(),
                'exists' => isset($existing[mb_strtolower($item->name)]),
            ];
        }, $items);
    }

    /**
     * @return array<string, true>
     */
    private function existingNames(): array
    {
        $names = [];

        foreach (Material::query()->pluck('name') as $name) {
            $names[mb_strtolower(trim($name))] = true;
        }

        return $names;
    }
}

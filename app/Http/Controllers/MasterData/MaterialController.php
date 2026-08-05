<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\MaterialRequest;
use App\Models\Material;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MaterialController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Material::class);

        $q = trim((string) $request->query('q'));
        $archived = $request->boolean('archived');

        $materials = Material::query()
            ->where('active', ! $archived)
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->whereLike('name', "%{$q}%")
                ->orWhereLike('article_no', "%{$q}%")))
            ->with('supplier:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Material $material): array => [
                'id' => $material->id,
                'name' => $material->name,
                'article_no' => $material->article_no,
                'price_net' => $material->price_net,
                'package_unit' => $material->package_unit,
                'supplier' => $material->supplier?->name,
                'archived' => ! $material->active,
            ]);

        $canWrite = Gate::allows('create', Material::class);

        return Inertia::render('materials/index', [
            'materials' => $materials,
            'filters' => ['q' => $q, 'archived' => $archived],
            // Für das Einlesen von Preislisten (Scan-Karte).
            'canWrite' => $canWrite,
            'suppliers' => $canWrite ? $this->supplierOptions() : [],
            'scanImagesEnabled' => (string) config('services.anthropic.key') !== '',
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Material::class);

        return Inertia::render('materials/create', [
            'suppliers' => $this->supplierOptions(),
        ]);
    }

    public function store(MaterialRequest $request): RedirectResponse
    {
        $material = Material::create($request->validated());

        return redirect()->route('materials.edit', $material)
            ->with('success', "Artikel „{$material->name}“ wurde angelegt.");
    }

    public function edit(Material $material): Response
    {
        Gate::authorize('view', $material);

        return Inertia::render('materials/edit', [
            'material' => [
                'id' => $material->id,
                'supplier_id' => $material->supplier_id,
                'article_no' => $material->article_no,
                'name' => $material->name,
                'price_net' => $material->price_net,
                'package_unit' => $material->package_unit,
                'active' => $material->active,
                'notes' => $material->notes,
                'lock_version' => $material->lock_version,
            ],
            'suppliers' => $this->supplierOptions(),
            'canWrite' => Gate::allows('update', $material),
        ]);
    }

    public function update(MaterialRequest $request, Material $material): RedirectResponse
    {
        if ($material->isStale($request->integer('lock_version'))) {
            return back()->withErrors([
                'lock_version' => 'Der Artikel wurde zwischenzeitlich geändert. Bitte Seite neu laden.',
            ]);
        }

        $material->update($request->validated());

        return back()->with('success', 'Änderungen gespeichert.');
    }

    public function archive(Material $material): RedirectResponse
    {
        Gate::authorize('archive', $material);

        $material->update(['active' => ! $material->active]);

        return back()->with('success', $material->active
            ? "Artikel „{$material->name}“ ist wieder aktiv."
            : "Artikel „{$material->name}“ wurde archiviert.");
    }

    /**
     * Endgültig löschen — Artikel hängen an nichts weiter.
     */
    public function destroy(Material $material): RedirectResponse
    {
        Gate::authorize('delete', $material);

        $material->delete();

        return redirect()->route('materials.index')
            ->with('success', "Artikel „{$material->name}“ wurde gelöscht.");
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function supplierOptions(): array
    {
        return Supplier::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Supplier $supplier): array => ['id' => $supplier->id, 'name' => $supplier->name])
            ->values()
            ->all();
    }
}

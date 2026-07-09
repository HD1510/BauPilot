<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\SupplierRequest;
use App\Models\CostType;
use App\Models\Supplier;
use App\Support\Duplicates\DuplicateFinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Supplier::class);

        $q = trim((string) $request->query('q'));
        $archived = $request->boolean('archived');

        $suppliers = Supplier::query()
            ->where('active', ! $archived)
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->whereLike('name', "%{$q}%")
                ->orWhereLike('short_code', "%{$q}%")))
            ->with('defaultCostType:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier): array => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'short_code' => $supplier->short_code,
                'payment_target_days' => $supplier->payment_target_days,
                'skonto_percent' => $supplier->skonto_percent,
                'skonto_days' => $supplier->skonto_days,
                'default_cost_type' => $supplier->defaultCostType?->name,
                'archived' => ! $supplier->active,
            ]);

        return Inertia::render('suppliers/index', [
            'suppliers' => $suppliers,
            'filters' => ['q' => $q, 'archived' => $archived],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Supplier::class);

        return Inertia::render('suppliers/create', [
            'costTypes' => $this->costTypeOptions(),
        ]);
    }

    public function store(SupplierRequest $request, DuplicateFinder $finder): RedirectResponse
    {
        $validated = $request->validated();

        if (! $request->boolean('force')) {
            $duplicates = $finder->findSimilar(Supplier::class, $validated['name']);

            if ($duplicates->isNotEmpty()) {
                return back()
                    ->with('duplicates', $duplicates->all())
                    ->withErrors(['name' => 'Es gibt ähnliche Lieferanten — bitte prüfen, ob der Lieferant schon existiert.']);
            }
        }

        $supplier = Supplier::create($validated);

        return redirect()->route('suppliers.edit', $supplier)
            ->with('success', "Lieferant „{$supplier->name}“ wurde angelegt.");
    }

    public function edit(Supplier $supplier): Response
    {
        Gate::authorize('view', $supplier);

        return Inertia::render('suppliers/edit', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'short_code' => $supplier->short_code,
                'payment_target_days' => $supplier->payment_target_days,
                'default_cost_type_id' => $supplier->default_cost_type_id,
                'skonto_percent' => $supplier->skonto_percent,
                'skonto_days' => $supplier->skonto_days,
                'active' => $supplier->active,
                'notes' => $supplier->notes,
                'lock_version' => $supplier->lock_version,
            ],
            'costTypes' => $this->costTypeOptions(),
            'canWrite' => Gate::allows('update', $supplier),
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        if ($supplier->isStale($request->integer('lock_version'))) {
            return back()->withErrors([
                'lock_version' => 'Der Lieferant wurde zwischenzeitlich von jemand anderem geändert. Bitte Seite neu laden.',
            ]);
        }

        $supplier->update($request->validated());

        return back()->with('success', 'Änderungen gespeichert.');
    }

    public function archive(Supplier $supplier): RedirectResponse
    {
        Gate::authorize('archive', $supplier);

        $supplier->update(['active' => ! $supplier->active]);

        return back()->with('success', $supplier->active
            ? "Lieferant „{$supplier->name}“ ist wieder aktiv."
            : "Lieferant „{$supplier->name}“ wurde archiviert.");
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function costTypeOptions(): array
    {
        return CostType::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CostType $costType): array => ['id' => $costType->id, 'name' => $costType->name])
            ->values()
            ->all();
    }
}

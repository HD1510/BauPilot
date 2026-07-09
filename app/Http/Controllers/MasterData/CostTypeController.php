<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\CostTypeRequest;
use App\Models\CostType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CostTypeController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CostType::class);

        $q = trim((string) $request->query('q'));
        $archived = $request->boolean('archived');

        $costTypes = CostType::query()
            ->where('active', ! $archived)
            ->when($q !== '', fn ($query) => $query->whereLike('name', "%{$q}%"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CostType $costType): array => [
                'id' => $costType->id,
                'name' => $costType->name,
                'sort_order' => $costType->sort_order,
                'archived' => ! $costType->active,
                'lock_version' => $costType->lock_version,
            ]);

        return Inertia::render('cost-types/index', [
            'costTypes' => $costTypes,
            'filters' => ['q' => $q, 'archived' => $archived],
            'canWrite' => Gate::allows('create', CostType::class),
        ]);
    }

    public function store(CostTypeRequest $request): RedirectResponse
    {
        CostType::create($request->validated());

        return back()->with('success', 'Kostenart angelegt.');
    }

    public function update(CostTypeRequest $request, CostType $costType): RedirectResponse
    {
        if ($costType->isStale($request->integer('lock_version'))) {
            return back()->withErrors([
                'lock_version' => 'Die Kostenart wurde zwischenzeitlich geändert. Bitte Seite neu laden.',
            ]);
        }

        $costType->update($request->validated());

        return back()->with('success', 'Änderungen gespeichert.');
    }

    public function archive(CostType $costType): RedirectResponse
    {
        Gate::authorize('archive', $costType);

        $costType->update(['active' => ! $costType->active]);

        return back()->with('success', $costType->active
            ? "Kostenart „{$costType->name}“ ist wieder aktiv."
            : "Kostenart „{$costType->name}“ wurde archiviert.");
    }
}

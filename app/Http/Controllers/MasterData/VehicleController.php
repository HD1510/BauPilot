<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\VehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Vehicle::class);

        $q = trim((string) $request->query('q'));
        $archived = $request->boolean('archived');

        $vehicles = Vehicle::query()
            ->where('active', ! $archived)
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->whereLike('plate', "%{$q}%")
                ->orWhereLike('brand', "%{$q}%")
                ->orWhereLike('model', "%{$q}%")))
            ->withCount('dates')
            ->orderBy('plate')
            ->get()
            ->map(fn (Vehicle $vehicle): array => [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'inspection_due_on' => $vehicle->inspection_due_on?->toDateString(),
                'vignette_until' => $vehicle->vignette_until?->toDateString(),
                'dates_count' => $vehicle->dates_count,
                'archived' => ! $vehicle->active,
            ]);

        return Inertia::render('vehicles/index', [
            'vehicles' => $vehicles,
            'filters' => ['q' => $q, 'archived' => $archived],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Vehicle::class);

        return Inertia::render('vehicles/create');
    }

    public function store(VehicleRequest $request): RedirectResponse
    {
        $vehicle = Vehicle::create($request->validated());

        return redirect()->route('vehicles.edit', $vehicle)
            ->with('success', "Fahrzeug „{$vehicle->plate}“ wurde angelegt.");
    }

    public function edit(Vehicle $vehicle): Response
    {
        Gate::authorize('view', $vehicle);

        return Inertia::render('vehicles/edit', [
            'vehicle' => [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'inspection_due_on' => $vehicle->inspection_due_on?->toDateString(),
                'vignette_until' => $vehicle->vignette_until?->toDateString(),
                'fuel_card' => $vehicle->fuel_card,
                'active' => $vehicle->active,
                'notes' => $vehicle->notes,
                'lock_version' => $vehicle->lock_version,
            ],
            'dates' => $vehicle->dates()->orderBy('due_on')->get()
                ->map(fn ($date): array => [
                    'id' => $date->id,
                    'label' => $date->label,
                    'due_on' => $date->due_on->toDateString(),
                ]),
            'canWrite' => Gate::allows('update', $vehicle),
        ]);
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        if ($vehicle->isStale($request->integer('lock_version'))) {
            return back()->withErrors([
                'lock_version' => 'Das Fahrzeug wurde zwischenzeitlich geändert. Bitte Seite neu laden.',
            ]);
        }

        $vehicle->update($request->validated());

        return back()->with('success', 'Änderungen gespeichert.');
    }

    public function archive(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('archive', $vehicle);

        $vehicle->update(['active' => ! $vehicle->active]);

        return back()->with('success', $vehicle->active
            ? "Fahrzeug „{$vehicle->plate}“ ist wieder aktiv."
            : "Fahrzeug „{$vehicle->plate}“ wurde archiviert.");
    }

    /**
     * Endgültig löschen — die Zusatztermine des Fahrzeugs gehen mit.
     */
    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('delete', $vehicle);

        $vehicle->delete();

        return redirect()->route('vehicles.index')
            ->with('success', "Fahrzeug „{$vehicle->plate}“ wurde gelöscht.");
    }
}

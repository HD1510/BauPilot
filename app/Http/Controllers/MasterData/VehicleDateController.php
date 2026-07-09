<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VehicleDateController extends Controller
{
    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'due_on' => ['required', 'date'],
        ], [], ['label' => 'Bezeichnung', 'due_on' => 'fällig am']);

        $vehicle->dates()->create($validated);

        return back()->with('success', 'Termin hinzugefügt.');
    }

    public function destroy(Vehicle $vehicle, VehicleDate $date): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        abort_unless($date->vehicle_id === $vehicle->id, 404);

        $date->delete();

        return back()->with('success', 'Termin entfernt.');
    }
}

<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleDate;
use Carbon\CarbonImmutable;
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

    /**
     * Pickerl erledigt: nächster Termin ein Jahr nach dem fälligen —
     * der Monat bleibt damit wie beim §57a-Lochkartensystem verankert.
     */
    public function inspectionDone(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        abort_if($vehicle->inspection_due_on === null, 404);

        $vehicle->update(['inspection_due_on' => self::nextYear($vehicle->inspection_due_on)]);

        return back()->with('success', "Pickerl {$vehicle->plate} erledigt — nächster Termin am "
            .$vehicle->inspection_due_on->format('d.m.Y').'.');
    }

    public function vignetteDone(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        abort_if($vehicle->vignette_until === null, 404);

        $vehicle->update(['vignette_until' => self::nextYear($vehicle->vignette_until)]);

        return back()->with('success', "Vignette {$vehicle->plate} erledigt — gültig bis "
            .$vehicle->vignette_until->format('d.m.Y').'.');
    }

    public function dateDone(Vehicle $vehicle, VehicleDate $date): RedirectResponse
    {
        Gate::authorize('update', $vehicle);

        abort_unless($date->vehicle_id === $vehicle->id, 404);

        $date->update(['due_on' => self::nextYear($date->due_on)]);

        return back()->with('success', "{$date->label} {$vehicle->plate} erledigt — nächster Termin am "
            .$date->due_on->format('d.m.Y').'.');
    }

    /**
     * Ein Jahr weiter — bei länger Überfälligem so oft, bis der
     * nächste Termin wieder in der Zukunft liegt.
     */
    private static function nextYear(CarbonImmutable $dueOn): CarbonImmutable
    {
        $next = $dueOn->addYear();

        while ($next->lessThanOrEqualTo(CarbonImmutable::today())) {
            $next = $next->addYear();
        }

        return $next;
    }
}

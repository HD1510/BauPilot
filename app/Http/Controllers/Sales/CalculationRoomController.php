<?php

namespace App\Http\Controllers\Sales;

use App\Enums\RoomMaterial;
use App\Enums\RoomShape;
use App\Http\Controllers\Controller;
use App\Models\Calculation;
use App\Models\CalculationRoom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Räume einer Baukalkulation: je nach Form sind andere Maße Pflicht.
 */
class CalculationRoomController extends Controller
{
    public function store(Request $request, Calculation $calculation): RedirectResponse
    {
        Gate::authorize('update', $calculation);

        $calculation->rooms()->create([
            ...$this->validated($request),
            'company_id' => $calculation->company_id,
        ]);

        return back()->with('success', 'Raum hinzugefügt.');
    }

    public function update(Request $request, Calculation $calculation, CalculationRoom $room): RedirectResponse
    {
        Gate::authorize('update', $calculation);
        abort_unless($room->calculation_id === $calculation->id, 404);

        $room->update($this->validated($request));

        return back()->with('success', 'Raum gespeichert.');
    }

    public function destroy(Calculation $calculation, CalculationRoom $room): RedirectResponse
    {
        Gate::authorize('update', $calculation);
        abort_unless($room->calculation_id === $calculation->id, 404);

        $room->delete();

        return back()->with('success', "Raum „{$room->name}“ entfernt.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'shape' => ['required', Rule::enum(RoomShape::class)],
            'material' => ['required', Rule::enum(RoomMaterial::class)],
            'length' => ['nullable', 'numeric', 'between:0.01,1000', 'required_if:shape,rectangle,l_shape,trapezoid,triangle'],
            'width' => ['nullable', 'numeric', 'between:0.01,1000', 'required_if:shape,rectangle,l_shape,trapezoid'],
            'height' => ['required', 'numeric', 'between:0.5,20'],
            'length2' => ['nullable', 'numeric', 'between:0,1000', 'required_if:shape,l_shape'],
            'width2' => ['nullable', 'numeric', 'between:0,1000', 'required_if:shape,l_shape'],
            'depth' => ['nullable', 'numeric', 'between:0.01,1000', 'required_if:shape,trapezoid,triangle'],
            'area_manual' => ['nullable', 'numeric', 'between:0.01,100000', 'required_if:shape,manual'],
            'perimeter_manual' => ['nullable', 'numeric', 'between:0,100000', 'required_if:shape,manual'],
            'edges' => ['nullable', 'integer', 'between:0,50'],
            'door_width' => ['nullable', 'numeric', 'between:0,100'],
            'opening_area' => ['nullable', 'numeric', 'between:0,1000'],
            'estimated' => ['boolean'],
        ], [], [
            'name' => 'Name',
            'shape' => 'Form',
            'material' => 'Belag',
            'length' => 'Länge',
            'width' => 'Breite',
            'height' => 'Höhe',
            'length2' => 'Ausschnitt-Länge',
            'width2' => 'Ausschnitt-Breite',
            'depth' => 'Tiefe',
            'area_manual' => 'Fläche',
            'perimeter_manual' => 'Umfang',
            'edges' => 'Außenkanten',
            'door_width' => 'Türbreiten',
            'opening_area' => 'Öffnungsflächen',
        ]);
    }
}

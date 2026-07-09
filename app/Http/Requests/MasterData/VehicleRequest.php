<?php

namespace App\Http\Requests\MasterData;

use App\Models\Vehicle;
use Illuminate\Validation\Rule;

class VehicleRequest extends MasterDataRequest
{
    protected string $modelClass = Vehicle::class;

    protected string $routeParameter = 'vehicle';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $vehicle = $this->route('vehicle');
        $vehicleId = $vehicle instanceof Vehicle ? $vehicle->id : null;

        return [
            'plate' => [
                'required', 'string', 'max:20',
                Rule::unique('vehicles', 'plate')
                    ->where('company_id', $this->activeCompanyId())
                    ->ignore($vehicleId),
            ],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'inspection_due_on' => ['nullable', 'date'],
            'vignette_until' => ['nullable', 'date'],
            'fuel_card' => ['nullable', 'string', 'max:100'],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'plate' => 'Kennzeichen',
            'brand' => 'Marke',
            'model' => 'Modell',
            'inspection_due_on' => 'Pickerl fällig am',
            'vignette_until' => 'Vignette gültig bis',
            'fuel_card' => 'Tankkarte',
            'notes' => 'Notizen',
        ];
    }
}

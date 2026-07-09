<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\VehicleDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleDate>
 */
class VehicleDateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'company_id' => fn (array $attributes) => Vehicle::withoutGlobalScopes()->whereKey($attributes['vehicle_id'])->value('company_id'),
            'label' => fake()->randomElement(['Service', 'Eichung', 'Kranprüfung']),
            'due_on' => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
        ];
    }
}

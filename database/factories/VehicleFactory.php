<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'plate' => strtoupper(fake()->unique()->bothify('?? ###??')),
            'brand' => fake()->randomElement(['MAN', 'Mercedes', 'Iveco', 'VW']),
            'model' => fake()->word(),
            'active' => true,
        ];
    }
}

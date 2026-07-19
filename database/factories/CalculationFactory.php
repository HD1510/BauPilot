<?php

namespace Database\Factories;

use App\Models\Calculation;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Calculation>
 */
class CalculationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Kalkulation '.fake()->streetName(),
            'waste_percent' => 15,
            'wall_tile_height' => 2.10,
        ];
    }
}

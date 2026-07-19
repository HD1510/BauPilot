<?php

namespace Database\Factories;

use App\Models\Calculation;
use App\Models\CalculationRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalculationRoom>
 */
class CalculationRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'calculation_id' => Calculation::factory(),
            'company_id' => fn (array $attributes) => Calculation::withoutGlobalScopes()
                ->whereKey($attributes['calculation_id'])
                ->value('company_id'),
            'name' => fake()->randomElement(['Wohnzimmer', 'Küche', 'Bad', 'Flur', 'Schlafzimmer']),
            'shape' => 'rectangle',
            'material' => 'parquet',
            'length' => fake()->randomFloat(2, 2, 8),
            'width' => fake()->randomFloat(2, 2, 6),
            'height' => 2.50,
        ];
    }
}

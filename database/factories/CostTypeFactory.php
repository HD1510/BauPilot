<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CostType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostType>
 */
class CostTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->words(2, true),
            'sort_order' => fake()->numberBetween(0, 100),
            'active' => true,
        ];
    }
}

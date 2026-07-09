<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'short_code' => strtoupper(fake()->unique()->lexify('???')),
            'color' => fake()->hexColor(),
            'legal_form' => fake()->randomElement(['GmbH', 'e.U.', 'KG']),
            'fiscal_year_start_month' => 9,
            'warranty_years' => 3,
        ];
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'work_date' => fake()->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
            'site' => 'Baustelle '.fake()->streetName(),
        ];
    }
}

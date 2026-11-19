<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Vacation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vacation>
 */
class VacationFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+4 weeks');

        return [
            'company_id' => Company::factory(),
            'employee_id' => fn (array $attributes) => Employee::factory()->create(['company_id' => $attributes['company_id']])->id,
            'starts_on' => $start->format('Y-m-d'),
            'ends_on' => $start->modify('+1 week')->format('Y-m-d'),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeEntry>
 */
class OvertimeEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => fn (array $attributes) => Employee::factory()->create(['company_id' => $attributes['company_id']])->id,
            'year' => (int) now()->format('Y'),
            'month' => (int) now()->format('n'),
            'hours' => '10.00',
        ];
    }
}

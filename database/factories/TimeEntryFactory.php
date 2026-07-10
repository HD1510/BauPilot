<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'project_id' => fn (array $attributes) => Project::factory()->create(['company_id' => $attributes['company_id']])->id,
            'employee_id' => fn (array $attributes) => Employee::factory()->create(['company_id' => $attributes['company_id']])->id,
            'work_date' => now()->toDateString(),
            'hours' => '8.00',
        ];
    }
}

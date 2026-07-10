<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimePayout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimePayout>
 */
class OvertimePayoutFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => fn (array $attributes) => Employee::factory()->create(['company_id' => $attributes['company_id']])->id,
            'paid_on' => now()->toDateString(),
            'hours' => '10.00',
            'amount' => '350.00',
        ];
    }
}

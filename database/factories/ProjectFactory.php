<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['company_id' => $attributes['company_id']])->id,
            'title' => 'BV '.fake()->streetName(),
            'site_address' => fake()->address(),
            'status' => 'open',
        ];
    }
}

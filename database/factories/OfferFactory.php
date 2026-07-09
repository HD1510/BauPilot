<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['company_id' => $attributes['company_id']])->id,
            'location' => fake()->address(),
            'status' => 'inquiry',
        ];
    }
}

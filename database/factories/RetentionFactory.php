<?php

namespace Database\Factories;

use App\Models\OutgoingInvoice;
use App\Models\Retention;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Retention>
 */
class RetentionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'retainable_type' => 'outgoing_invoice',
            'retainable_id' => OutgoingInvoice::factory(),
            'company_id' => fn (array $attributes) => OutgoingInvoice::withoutGlobalScopes()->whereKey($attributes['retainable_id'])->value('company_id'),
            'kind' => 'warranty',
            'amount' => fake()->randomFloat(2, 100, 5000),
            'due_on' => now()->addYears(3)->toDateString(),
        ];
    }
}

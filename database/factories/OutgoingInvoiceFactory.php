<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\OutgoingInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutgoingInvoice>
 */
class OutgoingInvoiceFactory extends Factory
{
    public function definition(): array
    {
        $net = fake()->randomFloat(2, 1000, 50000);

        return [
            'company_id' => Company::factory(),
            'customer_id' => fn (array $attributes) => Customer::factory()->create(['company_id' => $attributes['company_id']])->id,
            'doc_type' => 'invoice',
            'number' => (string) fake()->unique()->numberBetween(250000, 259999),
            'invoice_date' => now()->toDateString(),
            'due_on' => now()->addDays(14)->toDateString(),
            'net' => number_format($net, 2, '.', ''),
            'vat_rate' => '20.00',
            'vat' => number_format(round($net * 0.2, 2), 2, '.', ''),
            'gross' => number_format(round($net * 1.2, 2), 2, '.', ''),
        ];
    }
}

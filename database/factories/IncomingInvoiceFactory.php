<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CostType;
use App\Models\IncomingInvoice;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomingInvoice>
 */
class IncomingInvoiceFactory extends Factory
{
    public function definition(): array
    {
        $net = fake()->randomFloat(2, 100, 20000);

        return [
            'company_id' => Company::factory(),
            'supplier_id' => fn (array $attributes) => Supplier::factory()->create(['company_id' => $attributes['company_id']])->id,
            'cost_type_id' => fn (array $attributes) => CostType::factory()->create(['company_id' => $attributes['company_id']])->id,
            'supplier_invoice_no' => fake()->bothify('RE-####'),
            'invoice_date' => now()->toDateString(),
            'net' => number_format($net, 2, '.', ''),
            'vat_rate' => '20.00',
            'vat' => number_format(round($net * 0.2, 2), 2, '.', ''),
            'gross' => number_format(round($net * 1.2, 2), 2, '.', ''),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\OutgoingInvoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'outgoing_invoice_id' => OutgoingInvoice::factory(),
            'company_id' => fn (array $attributes) => OutgoingInvoice::withoutGlobalScopes()->whereKey($attributes['outgoing_invoice_id'])->value('company_id'),
            'paid_on' => now()->toDateString(),
            'amount' => fake()->randomFloat(2, 100, 10000),
        ];
    }
}

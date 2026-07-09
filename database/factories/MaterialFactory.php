<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'company_id' => fn (array $attributes) => Supplier::withoutGlobalScopes()->whereKey($attributes['supplier_id'])->value('company_id'),
            'name' => fake()->unique()->words(3, true),
            'article_no' => fake()->bothify('ART-####'),
            'price_net' => fake()->randomFloat(2, 1, 500),
            'package_unit' => fake()->randomElement(['Stk', 'm²', 'kg', 'Palette']),
            'active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\ExternalOffer;
use App\Models\Project;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalOffer>
 */
class ExternalOfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => fn (array $attributes) => Project::withoutGlobalScopes()->whereKey($attributes['project_id'])->value('company_id'),
            'supplier_id' => fn (array $attributes) => Supplier::factory()->create(['company_id' => $attributes['company_id']])->id,
            'title' => 'Fremdangebot '.fake()->word(),
            'status' => 'received',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\ChangeOrder;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangeOrder>
 */
class ChangeOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => fn (array $attributes) => Project::withoutGlobalScopes()->whereKey($attributes['project_id'])->value('company_id'),
            'title' => 'Nachtrag '.fake()->word(),
            'status' => 'requested',
        ];
    }
}

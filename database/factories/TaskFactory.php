<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'project_id' => fn (array $attributes) => Project::factory()->create(['company_id' => $attributes['company_id']])->id,
            'kind' => 'task',
            'title' => fake()->sentence(4),
        ];
    }
}

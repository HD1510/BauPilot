<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectNote>
 */
class ProjectNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'project_id' => fn (array $attributes) => Project::factory()->create(['company_id' => $attributes['company_id']])->id,
            'body' => fake()->sentence(8),
        ];
    }
}

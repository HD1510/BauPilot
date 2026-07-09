<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectAppointment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectAppointment>
 */
class ProjectAppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'company_id' => fn (array $attributes) => Project::withoutGlobalScopes()->whereKey($attributes['project_id'])->value('company_id'),
            'on_date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'label' => fake()->randomElement(['Baubeginn', 'Abnahme', 'Besprechung']),
        ];
    }
}

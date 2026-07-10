<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use App\Models\SiteReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteReport>
 */
class SiteReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'project_id' => fn (array $attributes) => Project::factory()->create(['company_id' => $attributes['company_id']])->id,
            'number' => fn (array $attributes) => (int) SiteReport::withoutGlobalScopes()
                ->where('company_id', $attributes['company_id'])->max('number') + 1,
            'report_date' => now()->toDateString(),
            'status' => 'draft',
            'body_text' => fake()->sentence(10),
        ];
    }
}

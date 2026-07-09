<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'documentable_type' => 'project',
            'documentable_id' => Project::factory(),
            'company_id' => fn (array $attributes) => Project::withoutGlobalScopes()->whereKey($attributes['documentable_id'])->value('company_id'),
            'category' => 'other',
            'original_name' => fake()->word().'.pdf',
            'path' => 'testing/'.Str::uuid().'.pdf',
            'size' => fake()->numberBetween(1000, 500000),
            'mime' => 'application/pdf',
        ];
    }
}

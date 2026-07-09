<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ImportRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRun>
 */
class ImportRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'source_filename' => 'uebersicht.xlsx',
            'path' => 'imports/'.fake()->uuid().'.xlsx',
            'status' => 'dry_run',
            'stats' => [],
        ];
    }
}

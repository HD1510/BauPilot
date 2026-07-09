<?php

namespace Database\Seeders;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Entwicklungsdaten: ein Benutzer mit zwei Firmen (Vorbild: GmbH und
     * Einzelfirma aus der Spezifikation). Anmeldung: test@example.com / password.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $gmbh = Company::factory()->create([
            'name' => 'Bau GmbH',
            'short_code' => 'GMBH',
            'color' => '#2563eb',
            'legal_form' => 'GmbH',
        ]);

        $einzel = Company::factory()->create([
            'name' => 'Baumeister e.U.',
            'short_code' => 'EU',
            'color' => '#16a34a',
            'legal_form' => 'e.U.',
        ]);

        $gmbh->users()->attach($user->id, ['role' => CompanyRole::Admin->value]);
        $einzel->users()->attach($user->id, ['role' => CompanyRole::Admin->value]);
    }
}

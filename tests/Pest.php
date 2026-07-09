<?php

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Meldet einen Benutzer mit gegebener Rolle in einer frischen Firma an.
 *
 * @return array{0: User, 1: Company}
 */
function actingMember(CompanyRole $role = CompanyRole::Office): array
{
    $company = Company::factory()->create();
    $user = User::factory()->create();
    $company->users()->attach($user->id, ['role' => $role->value]);

    test()->actingAs($user);

    return [$user, $company];
}

<?php

namespace App\Models;

use App\Enums\CompanyRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * company_user-Pivot: Rolle je Firma (Architekturblatt Abschnitt 3).
 *
 * @property int $company_id
 * @property int $user_id
 * @property CompanyRole $role
 */
class CompanyUser extends Pivot
{
    protected $table = 'company_user';

    protected function casts(): array
    {
        return [
            'role' => CompanyRole::class,
        ];
    }
}

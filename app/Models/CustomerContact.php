<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasLockVersion;
use App\Models\Concerns\TracksUserStamps;
use Database\Factories\CustomerContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ansprechpartner eines Kunden.
 *
 * @property int $id
 * @property int $company_id
 * @property int $customer_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property int $lock_version
 */
#[Fillable(['name', 'phone', 'email'])]
class CustomerContact extends Model
{
    /** @use HasFactory<CustomerContactFactory> */
    use BelongsToCompany, HasFactory, HasLockVersion, TracksUserStamps;

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

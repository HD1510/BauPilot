<?php

namespace App\Http\Requests\MasterData;

use App\Models\Customer;

class CustomerRequest extends MasterDataRequest
{
    protected string $modelClass = Customer::class;

    protected string $routeParameter = 'customer';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'vat_id' => ['nullable', 'string', 'max:50'],
            'payment_target_days' => ['required', 'integer', 'between:0,365'],
            'external_ref' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'address' => 'Adresse',
            'phone' => 'Telefon',
            'email' => 'E-Mail',
            'vat_id' => 'UID-Nummer',
            'payment_target_days' => 'Zahlungsziel (Tage)',
            'external_ref' => 'externe Referenz',
            'notes' => 'Notizen',
        ];
    }
}

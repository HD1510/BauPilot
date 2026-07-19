<?php

namespace App\Http\Requests\MasterData;

use App\Models\Employee;
use Illuminate\Validation\Rule;

class EmployeeRequest extends MasterDataRequest
{
    protected string $modelClass = Employee::class;

    protected string $routeParameter = 'employee';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'birth_date' => ['nullable', 'date'],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
            'social_security_number' => ['nullable', 'string', 'max:20'],
            'iban' => ['nullable', 'string', 'max:34'],
            'overtime_rate' => ['nullable', 'decimal:0,2', 'between:0,1000'],
            'calc_hourly_rate' => ['nullable', 'decimal:0,2', 'between:0,10000'],
            'user_id' => [
                'nullable',
                // Nur Benutzer, die der aktiven Firma zugeordnet sind.
                Rule::exists('company_user', 'user_id')->where('company_id', $this->activeCompanyId()),
            ],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'address' => 'Adresse',
            'birth_date' => 'Geburtsdatum',
            'started_on' => 'Eintrittsdatum',
            'ended_on' => 'Austrittsdatum',
            'social_security_number' => 'SV-Nummer',
            'iban' => 'IBAN',
            'overtime_rate' => 'Überstundensatz',
            'calc_hourly_rate' => 'kalkulatorischer Stundensatz',
            'user_id' => 'Benutzerkonto',
            'notes' => 'Notizen',
        ];
    }
}

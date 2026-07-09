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
            'overtime_rate' => 'Überstundensatz',
            'calc_hourly_rate' => 'kalkulatorischer Stundensatz',
            'user_id' => 'Benutzerkonto',
            'notes' => 'Notizen',
        ];
    }
}

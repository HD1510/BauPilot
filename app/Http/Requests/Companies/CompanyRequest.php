<?php

namespace App\Http\Requests\Companies;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        // Läuft vor der Validierung — die Policy-Prüfung gehört deshalb
        // hierher, damit Fremde 403 statt Validierungsfehler bekommen.
        return $company instanceof Company
            ? $user->can('update', $company)
            : $user->can('create', Company::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $company = $this->route('company');
        $companyId = $company instanceof Company ? $company->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'short_code' => [
                'required', 'string', 'max:8', 'alpha_num:ascii',
                Rule::unique('companies', 'short_code')->ignore($companyId),
            ],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'legal_form' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'vat_id' => ['nullable', 'string', 'max:50'],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'calc_hourly_rate' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999'],
            'warranty_years' => ['required', 'integer', 'between:0,30'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Firmenname',
            'short_code' => 'Kurzzeichen',
            'color' => 'Kennfarbe',
            'legal_form' => 'Rechtsform',
            'address' => 'Adresse',
            'vat_id' => 'UID-Nummer',
            'fiscal_year_start_month' => 'Beginn des Geschäftsjahres',
            'calc_hourly_rate' => 'kalkulatorischer Stundensatz',
            'warranty_years' => 'Gewährleistung (Jahre)',
        ];
    }
}

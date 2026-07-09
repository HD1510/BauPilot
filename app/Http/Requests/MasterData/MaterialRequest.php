<?php

namespace App\Http\Requests\MasterData;

use App\Models\Material;
use Illuminate\Validation\Rule;

class MaterialRequest extends MasterDataRequest
{
    protected string $modelClass = Material::class;

    protected string $routeParameter = 'material';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => [
                'required',
                Rule::exists('suppliers', 'id')->where('company_id', $this->activeCompanyId()),
            ],
            'article_no' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'price_net' => ['nullable', 'decimal:0,2', 'between:0,1000000'],
            'package_unit' => ['nullable', 'string', 'max:100'],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'Lieferant',
            'article_no' => 'Artikelnummer',
            'name' => 'Bezeichnung',
            'price_net' => 'Preis netto',
            'package_unit' => 'Gebindeeinheit',
            'notes' => 'Notizen',
        ];
    }
}

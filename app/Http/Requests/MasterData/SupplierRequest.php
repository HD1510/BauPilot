<?php

namespace App\Http\Requests\MasterData;

use App\Models\Supplier;
use Illuminate\Validation\Rule;

class SupplierRequest extends MasterDataRequest
{
    protected string $modelClass = Supplier::class;

    protected string $routeParameter = 'supplier';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $supplierId = $supplier instanceof Supplier ? $supplier->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'short_code' => [
                'nullable', 'string', 'max:20',
                Rule::unique('suppliers', 'short_code')
                    ->where('company_id', $this->activeCompanyId())
                    ->ignore($supplierId),
            ],
            'payment_target_days' => ['required', 'integer', 'between:0,365'],
            'default_cost_type_id' => [
                'nullable',
                Rule::exists('cost_types', 'id')->where('company_id', $this->activeCompanyId()),
            ],
            'skonto_percent' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'skonto_days' => ['nullable', 'integer', 'between:0,365', 'required_with:skonto_percent'],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'short_code' => 'Kurzzeichen',
            'payment_target_days' => 'Zahlungsziel (Tage)',
            'default_cost_type_id' => 'Standard-Kostenart',
            'skonto_percent' => 'Skonto (%)',
            'skonto_days' => 'Skontofrist (Tage)',
            'notes' => 'Notizen',
        ];
    }
}

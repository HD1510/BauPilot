<?php

namespace App\Http\Requests\MasterData;

use App\Models\CostType;
use Illuminate\Validation\Rule;

class CostTypeRequest extends MasterDataRequest
{
    protected string $modelClass = CostType::class;

    protected string $routeParameter = 'cost_type';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $costType = $this->route('cost_type');
        $costTypeId = $costType instanceof CostType ? $costType->id : null;

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('cost_types', 'name')
                    ->where('company_id', $this->activeCompanyId())
                    ->ignore($costTypeId),
            ],
            'sort_order' => ['required', 'integer', 'between:0,10000'],
            'active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Name',
            'sort_order' => 'Reihenfolge',
        ];
    }
}

<?php

namespace App\Http\Requests\Sales;

use App\Enums\OfferStatus;
use App\Http\Requests\MasterData\MasterDataRequest;
use App\Models\Offer;
use Illuminate\Validation\Rule;

class OfferRequest extends MasterDataRequest
{
    protected string $modelClass = Offer::class;

    protected string $routeParameter = 'offer';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $this->activeCompanyId())],
            'location' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(OfferStatus::class)],
            'viewing_on' => ['nullable', 'date'],
            'follow_up_on' => ['nullable', 'date'],
            'offer_number' => ['nullable', 'string', 'max:50'],
            'offer_amount_net' => ['nullable', 'decimal:0,2', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'scan_token' => ['nullable', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return [
            'customer_id' => 'Kunde',
            'location' => 'Ort/Baustelle',
            'description' => 'Beschreibung',
            'status' => 'Status',
            'viewing_on' => 'Besichtigung am',
            'follow_up_on' => 'Wiedervorlage am',
            'offer_number' => 'Angebotsnummer',
            'offer_amount_net' => 'Angebotssumme netto',
            'notes' => 'Notizen',
        ];
    }
}

<?php

namespace App\Http\Requests\Invoicing;

use App\Http\Requests\MasterData\MasterDataRequest;
use App\Models\IncomingInvoice;
use Illuminate\Validation\Rule;

class IncomingInvoiceRequest extends MasterDataRequest
{
    protected string $modelClass = IncomingInvoice::class;

    protected string $routeParameter = 'incoming_invoice';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $this->activeCompanyId())],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['required', 'date'],
            'date_estimated' => ['boolean'],
            'amount_mode' => ['required', Rule::in(['net', 'gross'])],
            'amount' => ['required', 'decimal:0,2', 'min:0.01'],
            'vat_rate' => ['required', 'decimal:0,2', 'between:0,100'],
            'reverse_charge' => ['boolean'],
            'cost_type_id' => ['required', Rule::exists('cost_types', 'id')->where('company_id', $this->activeCompanyId())],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $this->activeCompanyId())],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_due_on' => ['nullable', 'date'],
            'skonto_amount' => ['nullable', 'decimal:0,2', 'min:0'],
            'skonto_until' => ['nullable', 'date', 'required_with:skonto_amount'],
            'subject' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'Lieferant',
            'supplier_invoice_no' => 'Rechnungsnummer des Lieferanten',
            'invoice_date' => 'Rechnungsdatum',
            'amount' => 'Betrag',
            'vat_rate' => 'USt-Satz',
            'cost_type_id' => 'Kostenart',
            'project_id' => 'Projekt',
            'payment_method' => 'Zahlungsart',
            'payment_due_on' => 'Zahlbar bis',
            'skonto_amount' => 'Skontobetrag',
            'skonto_until' => 'Skonto bis',
            'subject' => 'Betreff',
        ];
    }
}

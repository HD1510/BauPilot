<?php

namespace App\Http\Requests\Invoicing;

use App\Enums\ZeroRateReason;
use App\Http\Requests\MasterData\MasterDataRequest;
use App\Models\OutgoingInvoice;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OutgoingInvoiceRequest extends MasterDataRequest
{
    protected string $modelClass = OutgoingInvoice::class;

    protected string $routeParameter = 'outgoing_invoice';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $invoice = $this->route('outgoing_invoice');
        $invoiceId = $invoice instanceof OutgoingInvoice ? $invoice->id : null;

        return [
            'doc_type' => ['required', Rule::in(['invoice', 'partial', 'final'])],
            'number' => [
                'required', 'string', 'max:50',
                Rule::unique('outgoing_invoices', 'number')
                    ->where('company_id', $this->activeCompanyId())
                    ->ignore($invoiceId),
            ],
            'invoice_date' => ['required', 'date'],
            'due_on' => ['nullable', 'date'],
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $this->activeCompanyId())],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $this->activeCompanyId())],
            'amount_mode' => ['required', Rule::in(['net', 'gross'])],
            'amount' => ['required', 'decimal:0,2', 'min:0.01'],
            'vat_rate' => ['required', 'decimal:0,2', 'between:0,100'],
            'zero_rate_reason' => ['nullable', Rule::enum(ZeroRateReason::class)],
            'partial_ids' => ['nullable', 'array'],
            'partial_ids.*' => [Rule::exists('outgoing_invoices', 'id')->where('company_id', $this->activeCompanyId())],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            // §19-Fälle brauchen den strukturierten Grund (v1.1) — bei
            // Steuersatz 0 ist zero_rate_reason Pflicht.
            if ((float) $this->input('vat_rate', -1) === 0.0 && $this->input('zero_rate_reason') === null) {
                $validator->errors()->add('zero_rate_reason', 'Bei Steuersatz 0 % muss der Grund angegeben werden (z. B. §19 Bauleistung).');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'doc_type' => 'Belegart',
            'number' => 'Rechnungsnummer',
            'invoice_date' => 'Rechnungsdatum',
            'due_on' => 'Fällig am',
            'customer_id' => 'Kunde',
            'project_id' => 'Projekt',
            'amount' => 'Betrag',
            'vat_rate' => 'USt-Satz',
            'zero_rate_reason' => '0 %-Grund',
        ];
    }
}

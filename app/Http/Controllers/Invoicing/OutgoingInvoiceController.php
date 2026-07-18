<?php

namespace App\Http\Controllers\Invoicing;

use App\Enums\DocumentCategory;
use App\Enums\InvoiceDocType;
use App\Enums\ZeroRateReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\OutgoingInvoiceRequest;
use App\Models\Customer;
use App\Models\OutgoingInvoice;
use App\Models\Project;
use App\Support\InvoiceScan\InvoiceScanner;
use App\Support\InvoiceScan\ScannedFileAttacher;
use App\Support\Invoicing\InvoiceLedger;
use App\Support\Invoicing\OpenItemsQuery;
use App\Support\Money\MoneyHelper;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OutgoingInvoiceController extends Controller
{
    public function index(Request $request, OpenItemsQuery $query): Response
    {
        Gate::authorize('viewAny', OutgoingInvoice::class);

        $includeSettled = $request->boolean('all');

        $rows = $query->rows(includeSettled: $includeSettled)
            ->map(fn (array $row): array => [
                'id' => $row['invoice']->id,
                'number' => $row['invoice']->number,
                'doc_type_label' => $row['invoice']->doc_type->label(),
                'invoice_date' => $row['invoice']->invoice_date->toDateString(),
                'due_on' => $row['invoice']->due_on->toDateString(),
                'customer' => $row['invoice']->customer?->name,
                'project' => $row['invoice']->project?->title,
                'gross_effective' => $row['gross_effective'],
                'paid' => $row['paid'],
                'retained_open' => $row['retained_open'],
                'due_now' => $row['due_now'],
                'status' => $row['status']->value,
                'status_label' => $row['status']->label(),
            ]);

        return Inertia::render('outgoing-invoices/index', [
            'rows' => $rows,
            'totals' => $query->totals(),
            'filters' => ['all' => $includeSettled],
        ]);
    }

    public function create(Request $request, InvoiceScanner $scanner): Response
    {
        Gate::authorize('create', OutgoingInvoice::class);

        return Inertia::render('outgoing-invoices/create', [
            'scanImagesEnabled' => $scanner->enabled(),
            'preselectedProjectId' => $request->integer('project') ?: null,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'payment_target_days']),
            'projects' => Project::query()->orderBy('title')->get(['id', 'title', 'customer_id']),
            'partialInvoices' => OutgoingInvoice::query()
                ->where('doc_type', InvoiceDocType::Partial)
                ->whereNull('final_invoice_id')
                ->orderBy('number')
                ->get(['id', 'number', 'customer_id']),
            'zeroRateReasons' => array_map(
                fn (ZeroRateReason $reason): array => ['value' => $reason->value, 'label' => $reason->label()],
                ZeroRateReason::cases(),
            ),
        ]);
    }

    public function store(OutgoingInvoiceRequest $request, ScannedFileAttacher $attacher): RedirectResponse
    {
        $validated = $request->validated();

        $amounts = $validated['amount_mode'] === 'gross'
            ? MoneyHelper::fromGross($validated['amount'], $validated['vat_rate'])
            : MoneyHelper::fromNet($validated['amount'], $validated['vat_rate']);

        $customer = Customer::query()->whereKey($validated['customer_id'])->firstOrFail();

        $invoice = DB::transaction(function () use ($validated, $amounts, $customer): OutgoingInvoice {
            $invoice = OutgoingInvoice::create([
                'doc_type' => $validated['doc_type'],
                'number' => $validated['number'],
                'invoice_date' => $validated['invoice_date'],
                // due_on beim Anlegen aus dem Kunden-Zahlungsziel vorbefüllt,
                // je Rechnung überschreibbar (v1.1).
                'due_on' => $validated['due_on']
                    ?? CarbonImmutable::parse($validated['invoice_date'])->addDays($customer->payment_target_days)->toDateString(),
                'customer_id' => $validated['customer_id'],
                'project_id' => $validated['project_id'] ?? null,
                'net' => $amounts['net'],
                'vat_rate' => MoneyHelper::round($validated['vat_rate']),
                'vat' => $amounts['vat'],
                'gross' => $amounts['gross'],
                'zero_rate_reason' => $validated['zero_rate_reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Schlussrechnung fasst Teilrechnungen zusammen (v1.1).
            if ($invoice->doc_type === InvoiceDocType::Final && ! empty($validated['partial_ids'])) {
                OutgoingInvoice::query()
                    ->whereIn('id', $validated['partial_ids'])
                    ->where('doc_type', InvoiceDocType::Partial)
                    ->update(['final_invoice_id' => $invoice->id]);
            }

            return $invoice;
        });

        $token = $validated['scan_token'] ?? null;
        $attacher->attach($invoice, 'outgoing_invoice', is_string($token) ? $token : null, DocumentCategory::Invoice);

        return redirect()->route('outgoing-invoices.show', $invoice)
            ->with('success', "Rechnung {$invoice->number} wurde angelegt.");
    }

    public function show(OutgoingInvoice $outgoingInvoice, InvoiceLedger $ledger): Response|RedirectResponse
    {
        Gate::authorize('view', $outgoingInvoice);

        // Gutschriften/Storni öffnen die Sicht ihrer Originalrechnung.
        if ($outgoingInvoice->original_invoice_id !== null) {
            return redirect()->route('outgoing-invoices.show', $outgoingInvoice->original_invoice_id);
        }

        $summary = $ledger->summarize($outgoingInvoice);

        return Inertia::render('outgoing-invoices/show', [
            'invoice' => [
                'id' => $outgoingInvoice->id,
                'number' => $outgoingInvoice->number,
                'doc_type' => $outgoingInvoice->doc_type->value,
                'doc_type_label' => $outgoingInvoice->doc_type->label(),
                'invoice_date' => $outgoingInvoice->invoice_date->toDateString(),
                'due_on' => $outgoingInvoice->due_on->toDateString(),
                'customer' => $outgoingInvoice->customer?->name,
                'project' => $outgoingInvoice->project?->only(['id', 'title']),
                'net' => $outgoingInvoice->net,
                'vat_rate' => $outgoingInvoice->vat_rate,
                'vat' => $outgoingInvoice->vat,
                'gross' => $outgoingInvoice->gross,
                'zero_rate_reason_label' => $outgoingInvoice->zero_rate_reason?->label(),
                'status_label' => $outgoingInvoice->payment_status->label(),
                'status' => $outgoingInvoice->payment_status->value,
                'notes' => $outgoingInvoice->notes,
            ],
            'summary' => [
                'gross_effective' => $summary['gross_effective'],
                'paid' => $summary['paid'],
                'retained_open' => $summary['retained_open'],
                'due_now' => $summary['due_now'],
            ],
            'adjustments' => $outgoingInvoice->adjustments->map(fn (OutgoingInvoice $adjustment): array => [
                'id' => $adjustment->id,
                'number' => $adjustment->number,
                'doc_type_label' => $adjustment->doc_type->label(),
                'invoice_date' => $adjustment->invoice_date->toDateString(),
                'gross' => $adjustment->gross,
            ]),
            'partials' => $outgoingInvoice->partials()->get()->map(fn (OutgoingInvoice $partial): array => [
                'id' => $partial->id,
                'number' => $partial->number,
                'gross' => $partial->gross,
                'status_label' => $partial->payment_status->label(),
            ]),
            'payments' => $outgoingInvoice->payments()->with('retention')->orderBy('paid_on')->get()
                ->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'paid_on' => $payment->paid_on->toDateString(),
                    'amount' => $payment->amount,
                    'retention_kind' => $payment->retention?->kind->label(),
                ]),
            'retentions' => $outgoingInvoice->retentions()->orderBy('due_on')->get()
                ->map(fn ($retention): array => [
                    'id' => $retention->id,
                    'kind' => $retention->kind->value,
                    'kind_label' => $retention->kind->label(),
                    'percent' => $retention->percent,
                    'amount' => $retention->amount,
                    'due_on' => $retention->due_on->toDateString(),
                    'received_at' => $retention->received_at?->toIso8601String(),
                    'note' => $retention->note,
                ]),
            'documents' => $outgoingInvoice->documents()->orderByDesc('created_at')->get()
                ->map(fn ($document): array => [
                    'id' => $document->id,
                    'original_name' => $document->original_name,
                    'category_label' => $document->category->label(),
                    'size' => $document->size,
                ]),
            'canWrite' => Gate::allows('update', $outgoingInvoice),
        ]);
    }
}

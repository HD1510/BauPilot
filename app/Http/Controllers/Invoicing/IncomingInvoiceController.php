<?php

namespace App\Http\Controllers\Invoicing;

use App\Enums\IncomingPaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\IncomingInvoiceRequest;
use App\Models\CostType;
use App\Models\IncomingInvoice;
use App\Models\Project;
use App\Models\Supplier;
use App\Support\Money\MoneyHelper;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IncomingInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', IncomingInvoice::class);

        $q = trim((string) $request->query('q'));
        $onlyOpen = $request->boolean('open');
        $onlyUnchecked = $request->boolean('unchecked');

        $invoices = IncomingInvoice::query()
            ->with(['supplier:id,name', 'costType:id,name', 'project:id,title'])
            ->when($onlyOpen, fn ($query) => $query->where('payment_status', '!=', IncomingPaymentStatus::Paid->value))
            ->when($onlyUnchecked, fn ($query) => $query->where('checked', false))
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->whereLike('supplier_invoice_no', "%{$q}%")
                ->orWhereLike('subject', "%{$q}%")
                ->orWhereHas('supplier', fn ($supplier) => $supplier->whereLike('name', "%{$q}%"))))
            ->orderByDesc('invoice_date')
            ->get()
            ->map(fn (IncomingInvoice $invoice): array => [
                'id' => $invoice->id,
                'supplier' => $invoice->supplier?->name,
                'supplier_invoice_no' => $invoice->supplier_invoice_no,
                'invoice_date' => $invoice->invoice_date->toDateString(),
                'gross' => $invoice->gross,
                'reverse_charge' => $invoice->reverse_charge,
                'cost_type' => $invoice->costType?->name,
                'project' => $invoice->project?->title,
                'payment_due_on' => $invoice->payment_due_on?->toDateString(),
                'skonto_until' => $invoice->skonto_until?->toDateString(),
                'payment_status' => $invoice->payment_status->value,
                'payment_status_label' => $invoice->payment_status->label(),
                'checked' => $invoice->checked,
            ]);

        return Inertia::render('incoming-invoices/index', [
            'invoices' => $invoices,
            'filters' => ['q' => $q, 'open' => $onlyOpen, 'unchecked' => $onlyUnchecked],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', IncomingInvoice::class);

        return Inertia::render('incoming-invoices/create', $this->formOptions());
    }

    public function store(IncomingInvoiceRequest $request): RedirectResponse
    {
        $invoice = IncomingInvoice::create($this->payload($request));

        return redirect()->route('incoming-invoices.edit', $invoice)
            ->with('success', 'Eingangsrechnung wurde erfasst.');
    }

    public function edit(IncomingInvoice $incomingInvoice): Response
    {
        Gate::authorize('view', $incomingInvoice);

        return Inertia::render('incoming-invoices/edit', [
            ...$this->formOptions(),
            'invoice' => [
                'id' => $incomingInvoice->id,
                'supplier_id' => $incomingInvoice->supplier_id,
                'supplier_invoice_no' => $incomingInvoice->supplier_invoice_no,
                'invoice_date' => $incomingInvoice->invoice_date->toDateString(),
                'date_estimated' => $incomingInvoice->date_estimated,
                'net' => $incomingInvoice->net,
                'vat_rate' => $incomingInvoice->vat_rate,
                'gross' => $incomingInvoice->gross,
                'reverse_charge' => $incomingInvoice->reverse_charge,
                'cost_type_id' => $incomingInvoice->cost_type_id,
                'project_id' => $incomingInvoice->project_id,
                'payment_method' => $incomingInvoice->payment_method,
                'payment_due_on' => $incomingInvoice->payment_due_on?->toDateString(),
                'skonto_amount' => $incomingInvoice->skonto_amount,
                'skonto_until' => $incomingInvoice->skonto_until?->toDateString(),
                'payment_status' => $incomingInvoice->payment_status->value,
                'payment_status_label' => $incomingInvoice->payment_status->label(),
                'paid_on' => $incomingInvoice->paid_on?->toDateString(),
                'paid_amount' => $incomingInvoice->paid_amount,
                'checked' => $incomingInvoice->checked,
                'subject' => $incomingInvoice->subject,
                'notes' => $incomingInvoice->notes,
                'lock_version' => $incomingInvoice->lock_version,
            ],
            'documents' => $incomingInvoice->documents()->orderByDesc('created_at')->get()
                ->map(fn ($document): array => [
                    'id' => $document->id,
                    'original_name' => $document->original_name,
                    'category_label' => $document->category->label(),
                    'size' => $document->size,
                ]),
            'canWrite' => Gate::allows('update', $incomingInvoice),
        ]);
    }

    public function update(IncomingInvoiceRequest $request, IncomingInvoice $incomingInvoice): RedirectResponse
    {
        if ($incomingInvoice->isStale($request->integer('lock_version'))) {
            return back()->withErrors([
                'lock_version' => 'Die Eingangsrechnung wurde zwischenzeitlich geändert. Bitte Seite neu laden.',
            ]);
        }

        $incomingInvoice->update($this->payload($request));

        return back()->with('success', 'Änderungen gespeichert.');
    }

    /**
     * Zahlung buchen: paid_amount ist der tatsächlich gezahlte Betrag —
     * bei gezogenem Skonto kleiner als brutto (v1.1).
     */
    public function pay(Request $request, IncomingInvoice $incomingInvoice): RedirectResponse
    {
        Gate::authorize('update', $incomingInvoice);

        $validated = $request->validate([
            'paid_on' => ['required', 'date'],
            'paid_amount' => ['required', 'decimal:0,2', 'min:0.01'],
        ], [], ['paid_on' => 'Zahldatum', 'paid_amount' => 'gezahlter Betrag']);

        $paidTotal = (float) ($incomingInvoice->paid_amount ?? 0) + (float) $validated['paid_amount'];
        $gross = (float) $incomingInvoice->gross;
        $skonto = (float) ($incomingInvoice->skonto_amount ?? 0);

        // Innerhalb der Skontofrist gilt brutto minus Skonto als vollständig.
        $skontoValid = $incomingInvoice->skonto_until !== null
            && ! CarbonImmutable::parse($validated['paid_on'])->greaterThan($incomingInvoice->skonto_until);
        $settledThreshold = $skontoValid ? $gross - $skonto : $gross;

        $incomingInvoice->forceFill([
            'paid_on' => $validated['paid_on'],
            'paid_amount' => MoneyHelper::round($paidTotal),
            'payment_status' => $paidTotal >= $settledThreshold - 0.005
                ? IncomingPaymentStatus::Paid
                : IncomingPaymentStatus::Partial,
        ])->save();

        return back()->with('success', 'Zahlung gebucht.');
    }

    public function toggleChecked(IncomingInvoice $incomingInvoice): RedirectResponse
    {
        Gate::authorize('update', $incomingInvoice);

        $incomingInvoice->forceFill(['checked' => ! $incomingInvoice->checked])->save();

        return back()->with('success', $incomingInvoice->checked
            ? 'Rechnung als geprüft markiert.'
            : 'Prüfvermerk entfernt.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(IncomingInvoiceRequest $request): array
    {
        $validated = $request->validated();

        // Reverse charge: Satz 0, USt 0 (Architekturblatt Abschnitt 5).
        $vatRate = ! empty($validated['reverse_charge']) ? '0' : $validated['vat_rate'];

        $amounts = $validated['amount_mode'] === 'gross'
            ? MoneyHelper::fromGross($validated['amount'], $vatRate)
            : MoneyHelper::fromNet($validated['amount'], $vatRate);

        $supplier = Supplier::query()->whereKey($validated['supplier_id'])->firstOrFail();

        return [
            'supplier_id' => $validated['supplier_id'],
            'supplier_invoice_no' => $validated['supplier_invoice_no'] ?? null,
            'invoice_date' => $validated['invoice_date'],
            'date_estimated' => (bool) ($validated['date_estimated'] ?? false),
            'net' => $amounts['net'],
            'vat_rate' => MoneyHelper::round($vatRate),
            'vat' => $amounts['vat'],
            'gross' => $amounts['gross'],
            'reverse_charge' => (bool) ($validated['reverse_charge'] ?? false),
            'cost_type_id' => $validated['cost_type_id'],
            'project_id' => $validated['project_id'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
            'payment_due_on' => $validated['payment_due_on']
                ?? CarbonImmutable::parse($validated['invoice_date'])->addDays($supplier->payment_target_days)->toDateString(),
            'skonto_amount' => $validated['skonto_amount'] ?? null,
            'skonto_until' => $validated['skonto_until'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::query()->where('active', true)->orderBy('name')
                ->get(['id', 'name', 'payment_target_days', 'default_cost_type_id']),
            'costTypes' => CostType::query()->where('active', true)->orderBy('sort_order')->orderBy('name')
                ->get(['id', 'name']),
            'projects' => Project::query()->orderBy('title')->get(['id', 'title']),
        ];
    }
}

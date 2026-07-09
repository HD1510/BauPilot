<?php

namespace App\Http\Controllers\Invoicing;

use App\Enums\InvoiceDocType;
use App\Enums\RetentionKind;
use App\Http\Controllers\Controller;
use App\Models\OutgoingInvoice;
use App\Models\Retention;
use App\Support\Invoicing\InvoiceBookkeeper;
use App\Support\Money\MoneyHelper;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Saldowirksame Buchungen auf einer Ausgangsrechnung: Zahlung, Einbehalt,
 * Einbehalt-Freigabe, Gutschrift/Storno. Alles läuft über den
 * InvoiceBookkeeper — eine Transaktion, Status neu abgeleitet.
 */
class InvoiceActionController extends Controller
{
    public function storePayment(Request $request, OutgoingInvoice $outgoingInvoice, InvoiceBookkeeper $bookkeeper): RedirectResponse
    {
        Gate::authorize('update', $outgoingInvoice);

        $validated = $request->validate([
            'paid_on' => ['required', 'date'],
            'amount' => ['required', 'decimal:0,2', 'min:0.01'],
        ], [], ['paid_on' => 'Zahldatum', 'amount' => 'Betrag']);

        $bookkeeper->bookPayment($outgoingInvoice, CarbonImmutable::parse($validated['paid_on']), $validated['amount']);

        return back()->with('success', 'Zahlung gebucht.');
    }

    public function storeRetention(Request $request, OutgoingInvoice $outgoingInvoice, InvoiceBookkeeper $bookkeeper): RedirectResponse
    {
        Gate::authorize('update', $outgoingInvoice);

        $validated = $request->validate([
            'kind' => ['required', Rule::enum(RetentionKind::class)],
            'amount' => ['required', 'decimal:0,2', 'min:0.01'],
            'percent' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'due_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['kind' => 'Art', 'amount' => 'Betrag', 'percent' => 'Prozent', 'due_on' => 'Fällig am']);

        $bookkeeper->addRetention(
            $outgoingInvoice,
            RetentionKind::from($validated['kind']),
            $validated['amount'],
            CarbonImmutable::parse($validated['due_on']),
            $validated['percent'] ?? null,
            $validated['note'] ?? null,
        );

        return back()->with('success', 'Einbehalt erfasst.');
    }

    public function releaseRetention(Request $request, OutgoingInvoice $outgoingInvoice, Retention $retention, InvoiceBookkeeper $bookkeeper): RedirectResponse
    {
        Gate::authorize('update', $outgoingInvoice);
        abort_unless($retention->retainable_type === 'outgoing_invoice' && $retention->retainable_id === $outgoingInvoice->id, 404);

        $validated = $request->validate([
            'paid_on' => ['required', 'date'],
        ], [], ['paid_on' => 'Eingangsdatum']);

        if ($retention->isReceived()) {
            return back()->withErrors(['paid_on' => 'Dieser Einbehalt ist bereits eingegangen.']);
        }

        $bookkeeper->releaseRetention($retention, CarbonImmutable::parse($validated['paid_on']));

        return back()->with('success', 'Einbehalt als eingegangen gebucht.');
    }

    public function storeAdjustment(Request $request, OutgoingInvoice $outgoingInvoice, InvoiceBookkeeper $bookkeeper): RedirectResponse
    {
        Gate::authorize('update', $outgoingInvoice);

        $validated = $request->validate([
            'doc_type' => ['required', Rule::in(['credit_note', 'cancellation'])],
            'number' => [
                'required', 'string', 'max:50',
                Rule::unique('outgoing_invoices', 'number')->where('company_id', $outgoingInvoice->company_id),
            ],
            'invoice_date' => ['required', 'date'],
            'amount_mode' => ['required', Rule::in(['net', 'gross'])],
            'amount' => ['required', 'decimal:0,2', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], ['doc_type' => 'Belegart', 'number' => 'Belegnummer', 'invoice_date' => 'Belegdatum', 'amount' => 'Betrag']);

        if ($outgoingInvoice->original_invoice_id !== null) {
            return back()->withErrors(['doc_type' => 'Gutschrift/Storno kann nicht auf eine Gutschrift oder ein Storno gebucht werden.']);
        }

        $amounts = $validated['amount_mode'] === 'gross'
            ? MoneyHelper::fromGross($validated['amount'], $outgoingInvoice->vat_rate)
            : MoneyHelper::fromNet($validated['amount'], $outgoingInvoice->vat_rate);

        $bookkeeper->createAdjustment(
            $outgoingInvoice,
            InvoiceDocType::from($validated['doc_type']),
            $validated['number'],
            CarbonImmutable::parse($validated['invoice_date']),
            $amounts,
            (string) $outgoingInvoice->vat_rate,
            $validated['notes'] ?? null,
        );

        return back()->with('success', InvoiceDocType::from($validated['doc_type'])->label().' wurde gebucht.');
    }
}

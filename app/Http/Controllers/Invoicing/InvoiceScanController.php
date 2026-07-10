<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\IncomingInvoice;
use App\Models\Supplier;
use App\Support\InvoiceScan\InvoiceScanner;
use App\Support\InvoiceScan\ScannedInvoice;
use App\Support\InvoiceScan\SupplierMatcher;
use App\Support\Money\MoneyHelper;
use App\Support\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * KI-Scan für Eingangsrechnungen: PDF/Foto hochladen, Claude liest
 * Lieferant, Konditionen und Rechnungskopf aus. Bestehende Lieferanten
 * werden vorgeschlagen (DuplicateFinder), sonst kann direkt ein neuer
 * mit den erkannten Konditionen angelegt werden. Die Datei wartet unter
 * einem scan_token und wird beim Speichern als Beleg angehängt.
 */
class InvoiceScanController extends Controller
{
    public function store(
        Request $request,
        InvoiceScanner $scanner,
        SupplierMatcher $matcher,
        CompanyContext $context,
    ): JsonResponse {
        Gate::authorize('create', IncomingInvoice::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
        ], [], ['file' => 'Rechnung']);

        if (! $scanner->enabled()) {
            return response()->json([
                'message' => 'KI-Erkennung ist nicht konfiguriert (ANTHROPIC_API_KEY fehlt).',
            ], 422);
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            $scan = $scanner->scan($file);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $token = $this->stash($file, $context->requireId());

        return response()->json([
            'scan_token' => $token,
            'extraction' => $scan->toArray(),
            'matches' => $matcher->match($scan->supplierName),
            'supplier_proposal' => $this->supplierProposal($scan),
            'prefill' => $this->prefill($scan),
        ]);
    }

    /**
     * Neuen Lieferanten aus den erkannten Konditionen anlegen.
     */
    public function storeSupplier(Request $request): JsonResponse
    {
        Gate::authorize('create', Supplier::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'payment_target_days' => ['required', 'integer', 'between:0,365'],
            'skonto_percent' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'skonto_days' => ['nullable', 'integer', 'between:0,365'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], ['name' => 'Name', 'payment_target_days' => 'Zahlungsziel']);

        $supplier = Supplier::create([...$validated, 'active' => true]);

        return response()->json([
            'id' => $supplier->id,
            'name' => $supplier->name,
            'payment_target_days' => $supplier->payment_target_days,
            'default_cost_type_id' => $supplier->default_cost_type_id,
        ], 201);
    }

    /**
     * Datei bis zum Speichern der Rechnung beiseitelegen — der Pfad ist
     * über die Firmen-ID mandantenfest, das Token nicht erratbar.
     */
    private function stash(UploadedFile $file, int $companyId): string
    {
        $token = (string) Str::uuid();
        $safe = Str::limit(preg_replace('/[^\w.\-]+/', '_', $file->getClientOriginalName()) ?? 'rechnung', 100, '');

        Storage::disk('documents')->putFileAs(
            sprintf('%s/%d/scan/%s', app()->environment(), $companyId, $token),
            $file,
            $safe,
        );

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierProposal(ScannedInvoice $scan): array
    {
        $notes = array_filter([
            $scan->supplierUid !== null ? "UID: {$scan->supplierUid}" : null,
            $scan->supplierIban !== null ? "IBAN: {$scan->supplierIban}" : null,
        ]);

        return [
            'name' => $scan->supplierName,
            'payment_target_days' => $scan->paymentTargetDays ?? 30,
            'skonto_percent' => $scan->skontoPercent,
            'skonto_days' => $scan->skontoDays,
            'notes' => $notes === [] ? null : implode("\n", $notes),
        ];
    }

    /**
     * Formular-Vorbefüllung aus der Extraktion; gerechnet wird kaufmännisch
     * über den MoneyHelper, Skonto und Fälligkeit aus den Konditionen.
     *
     * @return array<string, mixed>
     */
    private function prefill(ScannedInvoice $scan): array
    {
        $vatRate = $scan->reverseCharge ? 0.0 : $scan->vatRate;

        // Nur Netto und Brutto erkannt: den Satz aus den Beträgen ableiten.
        if ($vatRate === null && $scan->net !== null && $scan->gross !== null && $scan->net > 0) {
            $vatRate = round(($scan->gross / $scan->net - 1) * 100, 2);
        }

        $amountMode = $scan->net !== null ? 'net' : 'gross';
        $amount = $scan->net ?? $scan->gross;

        $skontoAmount = null;

        if ($scan->skontoPercent !== null && $scan->gross !== null) {
            $skontoAmount = MoneyHelper::round($scan->gross * $scan->skontoPercent / 100);
        }

        $date = $scan->invoiceDate !== null ? CarbonImmutable::parse($scan->invoiceDate) : null;

        return [
            'supplier_invoice_no' => $scan->supplierInvoiceNo,
            'invoice_date' => $scan->invoiceDate,
            'amount_mode' => $amountMode,
            'amount' => $amount !== null ? MoneyHelper::round($amount) : null,
            'vat_rate' => $vatRate !== null ? MoneyHelper::round($vatRate) : null,
            'reverse_charge' => $scan->reverseCharge,
            'subject' => $scan->subject,
            'payment_due_on' => $date !== null && $scan->paymentTargetDays !== null
                ? $date->addDays($scan->paymentTargetDays)->toDateString()
                : null,
            'skonto_amount' => $skontoAmount,
            'skonto_until' => $date !== null && $scan->skontoDays !== null && $skontoAmount !== null
                ? $date->addDays($scan->skontoDays)->toDateString()
                : null,
        ];
    }
}

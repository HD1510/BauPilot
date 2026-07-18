<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\IncomingInvoice;
use App\Models\Offer;
use App\Models\OutgoingInvoice;
use App\Models\Supplier;
use App\Support\InvoiceScan\InvoiceScanPipeline;
use App\Support\InvoiceScan\PartnerMatcher;
use App\Support\InvoiceScan\ScanDocumentKind;
use App\Support\InvoiceScan\ScannedInvoice;
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
 * Beleg-Scan: PDF/Foto hochladen, die Scan-Leiter (E-Rechnung →
 * Textanalyse → KI) liest Partner, Konditionen und Belegkopf aus.
 * Bestehende Lieferanten bzw. Kunden werden vorgeschlagen
 * (DuplicateFinder), sonst kann direkt ein neuer mit den erkannten
 * Daten angelegt werden. Die Datei wartet unter einem scan_token und
 * wird beim Speichern als Beleg angehängt.
 */
class InvoiceScanController extends Controller
{
    public function __construct(
        private InvoiceScanPipeline $pipeline,
        private PartnerMatcher $matcher,
        private CompanyContext $context,
    ) {}

    public function incoming(Request $request): JsonResponse
    {
        Gate::authorize('create', IncomingInvoice::class);

        return $this->scan($request, ScanDocumentKind::IncomingInvoice);
    }

    public function outgoing(Request $request): JsonResponse
    {
        Gate::authorize('create', OutgoingInvoice::class);

        return $this->scan($request, ScanDocumentKind::OutgoingInvoice);
    }

    public function offer(Request $request): JsonResponse
    {
        Gate::authorize('create', Offer::class);

        return $this->scan($request, ScanDocumentKind::Offer);
    }

    /**
     * Neuen Lieferanten aus den erkannten Konditionen anlegen.
     */
    public function storeSupplier(Request $request): JsonResponse
    {
        Gate::authorize('create', Supplier::class);

        // Gleiche Grenzen wie im Lieferantenstamm (SupplierRequest).
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
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
     * Neuen Kunden aus den erkannten Daten anlegen.
     */
    public function storeCustomer(Request $request): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        // Gleiche Grenzen wie im Kundenstamm (CustomerRequest) — der
        // Scan-Weg darf nicht strenger sein als die Stammdatenpflege.
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'payment_target_days' => ['required', 'integer', 'between:0,365'],
            'vat_id' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], ['name' => 'Name', 'payment_target_days' => 'Zahlungsziel']);

        $customer = Customer::create($validated);

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'payment_target_days' => $customer->payment_target_days,
            'default_cost_type_id' => null,
        ], 201);
    }

    private function scan(Request $request, ScanDocumentKind $kind): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:20480'],
        ], [], ['file' => 'Beleg']);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            ['invoice' => $scan, 'source' => $source] = $this->pipeline->run($file, $kind);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $token = $this->stash($file, $this->context->requireId());

        return response()->json([
            'scan_token' => $token,
            'source' => $source,
            'extraction' => $scan->toArray(),
            'matches' => $this->matcher->match($kind->partnerModel(), $scan->partnerName),
            'partner_proposal' => $this->partnerProposal($scan, $kind),
            'prefill' => $this->prefill($scan, $kind),
        ]);
    }

    /**
     * Datei bis zum Speichern des Belegs beiseitelegen — der Pfad ist
     * über die Firmen-ID mandantenfest, das Token nicht erratbar.
     */
    private function stash(UploadedFile $file, int $companyId): string
    {
        $token = (string) Str::uuid();
        $safe = Str::limit(preg_replace('/[^\w.\-]+/', '_', $file->getClientOriginalName()) ?? 'beleg', 100, '');

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
    private function partnerProposal(ScannedInvoice $scan, ScanDocumentKind $kind): array
    {
        if ($kind->partnerIsSeller()) {
            $notes = array_filter([
                $scan->partnerUid !== null ? "UID: {$scan->partnerUid}" : null,
                $scan->partnerIban !== null ? "IBAN: {$scan->partnerIban}" : null,
            ]);

            return [
                'name' => $scan->partnerName,
                'payment_target_days' => $scan->paymentTargetDays ?? 30,
                'skonto_percent' => $scan->skontoPercent,
                'skonto_days' => $scan->skontoDays,
                'notes' => $notes === [] ? null : implode("\n", $notes),
            ];
        }

        return [
            'name' => $scan->partnerName,
            'payment_target_days' => $scan->paymentTargetDays ?? 14,
            'vat_id' => $scan->partnerUid,
            'notes' => null,
        ];
    }

    /**
     * Formular-Vorbefüllung je Belegart; gerechnet wird kaufmännisch
     * über den MoneyHelper, Skonto und Fälligkeit aus den Konditionen.
     *
     * @return array<string, mixed>
     */
    private function prefill(ScannedInvoice $scan, ScanDocumentKind $kind): array
    {
        $vatRate = $scan->reverseCharge ? 0.0 : $scan->vatRate;

        // Nur Netto und Brutto erkannt: den Satz aus den Beträgen ableiten.
        if ($vatRate === null && $scan->net !== null && $scan->gross !== null && $scan->net > 0) {
            $vatRate = round(($scan->gross / $scan->net - 1) * 100, 2);
        }

        $amountMode = $scan->net !== null ? 'net' : 'gross';
        $amount = $scan->net ?? $scan->gross;
        $date = $scan->docDate !== null ? CarbonImmutable::parse($scan->docDate) : null;
        $dueOn = $date !== null && $scan->paymentTargetDays !== null
            ? $date->addDays($scan->paymentTargetDays)->toDateString()
            : null;

        $base = [
            'invoice_date' => $scan->docDate,
            'amount_mode' => $amountMode,
            'amount' => $amount !== null ? MoneyHelper::round($amount) : null,
            'vat_rate' => $vatRate !== null ? MoneyHelper::round($vatRate) : null,
            'reverse_charge' => $scan->reverseCharge,
            'subject' => $scan->subject,
        ];

        $skontoAmount = $scan->skontoPercent !== null && $scan->gross !== null
            ? MoneyHelper::round($scan->gross * $scan->skontoPercent / 100)
            : null;

        // Angebotssumme ist netto; nur Brutto erkannt → herausrechnen.
        $offerNet = $scan->net ?? ($scan->gross !== null
            ? (float) MoneyHelper::fromGross($scan->gross, $vatRate ?? 20.0)['net']
            : null);

        // Exhaustiv je Belegart — eine neue Art zwingt hier zur Entscheidung.
        return match ($kind) {
            ScanDocumentKind::Offer => [
                'offer_number' => $scan->docNumber,
                'offer_amount_net' => $offerNet !== null ? MoneyHelper::round($offerNet) : null,
                'description' => $scan->subject,
            ],
            ScanDocumentKind::OutgoingInvoice => [
                ...$base,
                'number' => $scan->docNumber,
                'due_on' => $dueOn,
            ],
            ScanDocumentKind::IncomingInvoice => [
                ...$base,
                'supplier_invoice_no' => $scan->docNumber,
                'payment_due_on' => $dueOn,
                'skonto_amount' => $skontoAmount,
                'skonto_until' => $date !== null && $scan->skontoDays !== null && $skontoAmount !== null
                    ? $date->addDays($scan->skontoDays)->toDateString()
                    : null,
            ],
        };
    }
}

<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Schnellanlage von Lieferanten und Kunden direkt aus einer Maske
 * (Beleg-Scan oder Rechnungs-/Angebotsformular) — ohne Seitenwechsel.
 * Grenzen wie in den Stammdaten-Requests; gepflegt wird später dort.
 */
class PartnerQuickCreateController extends Controller
{
    public function supplier(Request $request): JsonResponse
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

    public function customer(Request $request): JsonResponse
    {
        Gate::authorize('create', Customer::class);

        // Gleiche Grenzen wie im Kundenstamm (CustomerRequest).
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
}

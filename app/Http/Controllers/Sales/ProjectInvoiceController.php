<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\IncomingInvoice;
use App\Models\OutgoingInvoice;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Rechnungen direkt am Projekt zuordnen und wieder lösen: bestehende
 * Ein-/Ausgangsrechnungen bekommen die project_id des Projekts. Die
 * Zahlen (Deckungsbeitrag) rechnen sich daraus automatisch neu.
 */
class ProjectInvoiceController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('view', $project);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['incoming', 'outgoing'])],
            'invoice_id' => ['required', 'integer'],
        ], [], ['invoice_id' => 'Rechnung']);

        $invoice = $this->find($validated['type'], (int) $validated['invoice_id']);

        Gate::authorize('update', $invoice);

        $invoice->forceFill(['project_id' => $project->id])->save();

        return back()->with('success', 'Rechnung wurde dem Projekt zugeordnet.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('view', $project);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['incoming', 'outgoing'])],
            'invoice_id' => ['required', 'integer'],
        ]);

        $invoice = $this->find($validated['type'], (int) $validated['invoice_id']);

        Gate::authorize('update', $invoice);

        // Nur lösen, was wirklich an diesem Projekt hängt.
        if ($invoice->project_id === $project->id) {
            $invoice->forceFill(['project_id' => null])->save();
        }

        return back()->with('success', 'Zuordnung wurde entfernt.');
    }

    private function find(string $type, int $id): IncomingInvoice|OutgoingInvoice
    {
        // Global Scope: fremde Firmen sind gar nie erreichbar.
        return $type === 'incoming'
            ? IncomingInvoice::query()->whereKey($id)->firstOrFail()
            : OutgoingInvoice::query()->whereKey($id)->firstOrFail();
    }
}

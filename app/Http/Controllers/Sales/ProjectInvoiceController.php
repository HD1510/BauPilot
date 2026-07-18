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

        // Umhängen nur bewusst: eine bereits zugeordnete Rechnung muss
        // erst gelöst werden — sonst verschieben sich Projektzahlen still.
        if ($invoice->project_id !== null && $invoice->project_id !== $project->id) {
            return back()->withErrors(['invoice_id' => 'Diese Rechnung ist bereits einem anderen Projekt zugeordnet. Bitte dort zuerst lösen.']);
        }

        // Gutschriften/Storni hängen an ihrer Originalrechnung und werden
        // nicht einzeln zugeordnet.
        if ($invoice instanceof OutgoingInvoice && $invoice->original_invoice_id !== null) {
            return back()->withErrors(['invoice_id' => 'Gutschriften und Storni folgen ihrer Originalrechnung.']);
        }

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

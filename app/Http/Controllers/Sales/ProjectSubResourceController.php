<?php

namespace App\Http\Controllers\Sales;

use App\Enums\ChangeOrderStatus;
use App\Enums\ExternalOfferStatus;
use App\Http\Controllers\Controller;
use App\Models\ChangeOrder;
use App\Models\ExternalOffer;
use App\Models\Project;
use App\Models\ProjectAppointment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Termine, Nachträge und Fremdangebote hängen am Projekt-Hub und werden
 * dort inline verwaltet.
 */
class ProjectSubResourceController extends Controller
{
    public function storeAppointment(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'on_date' => ['required', 'date'],
            'label' => ['required', 'string', 'max:255'],
        ], [], ['on_date' => 'Datum', 'label' => 'Bezeichnung']);

        $project->appointments()->create($validated);

        return back()->with('success', 'Termin hinzugefügt.');
    }

    public function destroyAppointment(Project $project, ProjectAppointment $appointment): RedirectResponse
    {
        Gate::authorize('update', $project);
        abort_unless($appointment->project_id === $project->id, 404);

        $appointment->delete();

        return back()->with('success', 'Termin entfernt.');
    }

    public function storeChangeOrder(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);
        Gate::authorize('create', ChangeOrder::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount_net' => ['nullable', 'decimal:0,2', 'min:0'],
            'status' => ['required', Rule::enum(ChangeOrderStatus::class)],
        ], [], ['title' => 'Titel', 'amount_net' => 'Betrag netto', 'status' => 'Status']);

        $project->changeOrders()->create($validated);

        return back()->with('success', 'Nachtrag angelegt.');
    }

    public function updateChangeOrder(Request $request, Project $project, ChangeOrder $changeOrder): RedirectResponse
    {
        Gate::authorize('update', $changeOrder);
        abort_unless($changeOrder->project_id === $project->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ChangeOrderStatus::class)],
            'amount_net' => ['nullable', 'decimal:0,2', 'min:0'],
        ], [], ['status' => 'Status', 'amount_net' => 'Betrag netto']);

        $changeOrder->update($validated);

        return back()->with('success', 'Nachtrag aktualisiert.');
    }

    public function storeExternalOffer(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);
        Gate::authorize('create', ExternalOffer::class);

        $validated = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $project->company_id)],
            'title' => ['required', 'string', 'max:255'],
            'amount_net' => ['nullable', 'decimal:0,2', 'min:0'],
            'received_on' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
        ], [], ['supplier_id' => 'Lieferant', 'title' => 'Titel', 'amount_net' => 'Betrag netto']);

        $project->externalOffers()->create($validated);

        return back()->with('success', 'Fremdangebot erfasst.');
    }

    public function updateExternalOffer(Request $request, Project $project, ExternalOffer $externalOffer): RedirectResponse
    {
        Gate::authorize('update', $externalOffer);
        abort_unless($externalOffer->project_id === $project->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ExternalOfferStatus::class)],
        ], [], ['status' => 'Status']);

        $externalOffer->update($validated);

        return back()->with('success', 'Fremdangebot aktualisiert.');
    }
}

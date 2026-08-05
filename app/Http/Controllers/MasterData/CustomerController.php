<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\CustomerRequest;
use App\Models\Customer;
use App\Models\Offer;
use App\Models\OutgoingInvoice;
use App\Models\Project;
use App\Support\Duplicates\DuplicateFinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Customer::class);

        $q = trim((string) $request->query('q'));
        $archived = $request->boolean('archived');

        $customers = Customer::query()
            ->when($archived, fn ($query) => $query->onlyTrashed())
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->whereLike('name', "%{$q}%")
                ->orWhereLike('address', "%{$q}%")
                ->orWhereLike('email', "%{$q}%")))
            ->withCount('contacts')
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'address' => $customer->address,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'payment_target_days' => $customer->payment_target_days,
                'contacts_count' => $customer->contacts_count,
                'archived' => $customer->trashed(),
            ]);

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'filters' => ['q' => $q, 'archived' => $archived],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Customer::class);

        return Inertia::render('customers/create');
    }

    public function store(CustomerRequest $request, DuplicateFinder $finder): RedirectResponse
    {
        $validated = $request->validated();

        // Dubletten-Vorschlag bei Anlage (Architekturblatt Abschnitt 5):
        // angelegt wird erst nach ausdrücklicher Bestätigung (force).
        if (! $request->boolean('force')) {
            $duplicates = $finder->findSimilar(Customer::class, $validated['name']);

            if ($duplicates->isNotEmpty()) {
                return back()
                    ->with('duplicates', $duplicates->all())
                    ->withErrors(['name' => 'Es gibt ähnliche Kunden — bitte prüfen, ob der Kunde schon existiert.']);
            }
        }

        $customer = Customer::create($validated);

        return redirect()->route('customers.edit', $customer)
            ->with('success', "Kunde „{$customer->name}“ wurde angelegt.");
    }

    public function edit(Customer $customer): Response
    {
        Gate::authorize('view', $customer);

        return Inertia::render('customers/edit', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'address' => $customer->address,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'vat_id' => $customer->vat_id,
                'payment_target_days' => $customer->payment_target_days,
                'external_ref' => $customer->external_ref,
                'notes' => $customer->notes,
                'archived' => $customer->trashed(),
                'lock_version' => $customer->lock_version,
            ],
            'contacts' => $customer->contacts()->orderBy('name')->get()
                ->map(fn ($contact): array => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                ]),
            'canWrite' => Gate::allows('update', $customer),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        if ($customer->isStale($request->integer('lock_version'))) {
            return back()->withErrors([
                'lock_version' => 'Der Kunde wurde zwischenzeitlich von jemand anderem geändert. Bitte Seite neu laden.',
            ]);
        }

        $customer->update($request->validated());

        return back()->with('success', 'Änderungen gespeichert.');
    }

    public function archive(Customer $customer): RedirectResponse
    {
        Gate::authorize('archive', $customer);

        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', "Kunde „{$customer->name}“ wurde archiviert.");
    }

    public function restore(int $customerId): RedirectResponse
    {
        $customer = Customer::onlyTrashed()->findOrFail($customerId);

        Gate::authorize('archive', $customer);

        $customer->restore();

        return back()->with('success', "Kunde „{$customer->name}“ ist wieder aktiv.");
    }

    /**
     * Endgültig löschen — nur, wenn nichts am Kunden hängt; sonst ist
     * Archivieren der richtige Weg (Belege bleiben nachvollziehbar).
     */
    public function destroy(int $customerId): RedirectResponse
    {
        $customer = Customer::withTrashed()->findOrFail($customerId);

        Gate::authorize('delete', $customer);

        $inUse = Offer::query()->where('customer_id', $customer->id)->exists()
            || Project::query()->where('customer_id', $customer->id)->exists()
            || OutgoingInvoice::query()->where('customer_id', $customer->id)->exists();

        if ($inUse) {
            return back()->with('error', "Kunde „{$customer->name}“ hat Angebote, Projekte oder Rechnungen und kann nicht gelöscht werden — bitte archivieren.");
        }

        $customer->forceDelete();

        return redirect()->route('customers.index')
            ->with('success', "Kunde „{$customer->name}“ wurde gelöscht.");
    }
}

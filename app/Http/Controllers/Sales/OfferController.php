<?php

namespace App\Http\Controllers\Sales;

use App\Enums\OfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\OfferRequest;
use App\Models\Customer;
use App\Models\Offer;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OfferController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Offer::class);

        $q = trim((string) $request->query('q'));
        $status = (string) $request->query('status');

        $offers = Offer::query()
            ->with('customer:id,name')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->whereLike('location', "%{$q}%")
                ->orWhereLike('description', "%{$q}%")
                ->orWhereLike('offer_number', "%{$q}%")
                ->orWhereHas('customer', fn ($customer) => $customer->whereLike('name', "%{$q}%"))))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Offer $offer): array => [
                'id' => $offer->id,
                'customer' => $offer->customer?->name,
                'location' => $offer->location,
                'status' => $offer->status->value,
                'status_label' => $offer->status->label(),
                'viewing_on' => $offer->viewing_on?->toDateString(),
                'follow_up_on' => $offer->follow_up_on?->toDateString(),
                'offer_number' => $offer->offer_number,
                'offer_amount_net' => $offer->offer_amount_net,
                'project_id' => $offer->project_id,
            ]);

        return Inertia::render('offers/index', [
            'offers' => $offers,
            'filters' => ['q' => $q, 'status' => $status],
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Offer::class);

        return Inertia::render('offers/create', [
            'customers' => $this->customerOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function store(OfferRequest $request): RedirectResponse
    {
        $offer = Offer::create($request->validated());

        return redirect()->route('offers.edit', $offer)
            ->with('success', 'Angebot wurde angelegt.');
    }

    public function edit(Offer $offer): Response
    {
        Gate::authorize('view', $offer);

        return Inertia::render('offers/edit', [
            'offer' => [
                'id' => $offer->id,
                'customer_id' => $offer->customer_id,
                'location' => $offer->location,
                'description' => $offer->description,
                'status' => $offer->status->value,
                'viewing_on' => $offer->viewing_on?->toDateString(),
                'follow_up_on' => $offer->follow_up_on?->toDateString(),
                'offer_number' => $offer->offer_number,
                'offer_amount_net' => $offer->offer_amount_net,
                'project_id' => $offer->project_id,
                'notes' => $offer->notes,
                'lock_version' => $offer->lock_version,
            ],
            'customers' => $this->customerOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function update(OfferRequest $request, Offer $offer): RedirectResponse
    {
        if ($offer->isStale($request->integer('lock_version'))) {
            return back()->withErrors([
                'lock_version' => 'Das Angebot wurde zwischenzeitlich geändert. Bitte Seite neu laden.',
            ]);
        }

        $offer->update($request->validated());

        return back()->with('success', 'Änderungen gespeichert.');
    }

    /**
     * Übernahme ins Projekt (Architekturblatt/M3): Ein angenommenes Angebot
     * wird zur Drehscheibe Projekt; das Angebot bleibt verknüpft erhalten.
     */
    public function convert(Request $request, Offer $offer): RedirectResponse
    {
        Gate::authorize('update', $offer);
        Gate::authorize('create', Project::class);

        if ($offer->project_id !== null) {
            return back()->withErrors(['status' => 'Dieses Angebot wurde bereits in ein Projekt übernommen.']);
        }

        $project = DB::transaction(function () use ($offer): Project {
            $project = Project::create([
                'customer_id' => $offer->customer_id,
                'title' => $offer->location !== null && $offer->location !== ''
                    ? 'BV '.$offer->location
                    : 'Projekt aus Angebot '.($offer->offer_number ?? $offer->id),
                'site_address' => $offer->location,
                'description' => $offer->description,
                'commissioned_on' => now()->toDateString(),
                'status' => 'open',
            ]);

            $offer->status = OfferStatus::Accepted;
            $offer->project_id = $project->id;
            $offer->save();

            return $project;
        });

        return redirect()->route('projects.show', $project)
            ->with('success', 'Angebot wurde ins Projekt übernommen.');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function customerOptions(): array
    {
        return Customer::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Customer $customer): array => ['id' => $customer->id, 'name' => $customer->name])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (OfferStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            OfferStatus::cases(),
        );
    }
}

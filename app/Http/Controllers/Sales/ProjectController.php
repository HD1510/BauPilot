<?php

namespace App\Http\Controllers\Sales;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ProjectRequest;
use App\Models\ChangeOrder;
use App\Models\Customer;
use App\Models\Document;
use App\Models\ExternalOffer;
use App\Models\IncomingInvoice;
use App\Models\OutgoingInvoice;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Support\Invoicing\OpenItemsQuery;
use App\Support\Projects\ProjectFigures;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        $q = trim((string) $request->query('q'));
        $status = (string) $request->query('status');

        $projects = Project::query()
            ->with('customer:id,name')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($q !== '', fn ($query) => $query->where(fn ($where) => $where
                ->whereLike('title', "%{$q}%")
                ->orWhereLike('site_address', "%{$q}%")
                ->orWhereHas('customer', fn ($customer) => $customer->whereLike('name', "%{$q}%"))))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'title' => $project->title,
                'customer' => $project->customer?->name,
                'site_address' => $project->site_address,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'planned_finish_on' => $project->planned_finish_on?->toDateString(),
            ]);

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'filters' => ['q' => $q, 'status' => $status],
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Project::class);

        return Inertia::render('projects/create', [
            'customers' => $this->customerOptions(),
            'users' => $this->userOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $project = Project::create($request->validated());

        return redirect()->route('projects.show', $project)
            ->with('success', "Projekt „{$project->title}“ wurde angelegt.");
    }

    public function show(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        /** @var User $user */
        $user = $request->user();
        $financials = $user->can('view-financials');

        $project->load(['customer:id,name', 'responsibleUser:id,name', 'appointments' => fn ($query) => $query->orderBy('on_date')]);

        return Inertia::render('projects/show', [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'customer' => $project->customer?->name,
                'customer_id' => $project->customer_id,
                'site_address' => $project->site_address,
                'description' => $project->description,
                'responsible' => $project->responsibleUser?->name,
                'commissioned_on' => $project->commissioned_on?->toDateString(),
                'started_on' => $project->started_on?->toDateString(),
                'planned_finish_on' => $project->planned_finish_on?->toDateString(),
                'finished_on' => $project->finished_on?->toDateString(),
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'warranty_until' => $project->warranty_until?->toDateString(),
                'notes' => $project->notes,
            ],
            'appointments' => $project->appointments->map(fn ($appointment): array => [
                'id' => $appointment->id,
                'on_date' => $appointment->on_date->toDateString(),
                'label' => $appointment->label,
            ]),
            'changeOrders' => $project->changeOrders()->orderByDesc('created_at')->get()
                ->map(fn (ChangeOrder $changeOrder): array => [
                    'id' => $changeOrder->id,
                    'title' => $changeOrder->title,
                    'status' => $changeOrder->status->value,
                    'status_label' => $changeOrder->status->label(),
                    // Nachtragsbeträge sieht die Rolle Baustelle nicht (Spec).
                    'amount_net' => $financials ? $changeOrder->amount_net : null,
                ]),
            'externalOffers' => $financials
                ? $project->externalOffers()->with('supplier:id,name')->orderByDesc('created_at')->get()
                    ->map(fn (ExternalOffer $externalOffer): array => [
                        'id' => $externalOffer->id,
                        'title' => $externalOffer->title,
                        'supplier' => $externalOffer->supplier?->name,
                        'amount_net' => $externalOffer->amount_net,
                        'status' => $externalOffer->status->value,
                        'status_label' => $externalOffer->status->label(),
                        'valid_until' => $externalOffer->valid_until?->toDateString(),
                    ])
                : null,
            // Projektzahlen mit Deckungsbeitrag (M8) — nur Finanzrollen.
            'figures' => $financials ? app(ProjectFigures::class)->forProject($project) : null,
            'openItems' => $financials
                ? app(OpenItemsQuery::class)->rows(includeSettled: true, projectId: $project->id)
                    ->map(fn (array $row): array => [
                        'id' => $row['invoice']->id,
                        'number' => $row['invoice']->number,
                        'doc_type_label' => $row['invoice']->doc_type->label(),
                        'gross_effective' => $row['gross_effective'],
                        'due_now' => $row['due_now'],
                        'retained_open' => $row['retained_open'],
                        'status' => $row['status']->value,
                        'status_label' => $row['status']->label(),
                    ])
                : null,
            'incomingInvoices' => $financials
                ? $project->incomingInvoices()->with('supplier:id,name')->orderByDesc('invoice_date')->get()
                    ->map(fn ($invoice): array => [
                        'id' => $invoice->id,
                        'supplier' => $invoice->supplier?->name,
                        'supplier_invoice_no' => $invoice->supplier_invoice_no,
                        'gross' => $invoice->gross,
                        'payment_status_label' => $invoice->payment_status->label(),
                    ])
                : null,
            'documents' => $project->documents()->orderByDesc('created_at')->get()
                ->map(fn (Document $document): array => [
                    'id' => $document->id,
                    'original_name' => $document->original_name,
                    'category' => $document->category->value,
                    'category_label' => $document->category->label(),
                    'size' => $document->size,
                    'is_image' => str_starts_with($document->mime, 'image/'),
                ]),
            'tasks' => $project->tasks()->with('assignee:id,name')
                ->orderByRaw('done_at IS NOT NULL, due_on ASC NULLS LAST, id DESC')->get()
                ->map(fn (Task $task): array => [
                    'id' => $task->id,
                    'kind' => $task->kind->value,
                    'kind_label' => $task->kind->label(),
                    'title' => $task->title,
                    'description' => $task->description,
                    'due_on' => $task->due_on?->toDateString(),
                    'assignee' => $task->assignee?->name,
                    'done_at' => $task->done_at?->toIso8601String(),
                ]),
            'projectNotes' => $project->projectNotes()->with('author:id,name')->orderByDesc('created_at')->get()
                ->map(fn (ProjectNote $note): array => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'author' => $note->author?->name,
                    'created_at' => $note->created_at?->toIso8601String(),
                ]),
            'members' => $this->userOptions(),
            'suppliers' => $financials ? $this->supplierOptions() : [],
            // Noch keinem Projekt zugeordnete Rechnungen — für die
            // Zuordnung direkt auf der Projektseite.
            'assignableIncoming' => $financials
                ? IncomingInvoice::query()->whereNull('project_id')
                    ->with('supplier:id,name')->orderByDesc('invoice_date')->limit(100)->get()
                    ->map(fn (IncomingInvoice $invoice): array => [
                        'id' => $invoice->id,
                        'label' => trim($invoice->supplier->name.' '.($invoice->supplier_invoice_no ?? '')).' — '.number_format((float) $invoice->gross, 2, ',', '.').' €',
                    ])
                : [],
            'assignableOutgoing' => $financials
                ? OutgoingInvoice::query()->whereNull('project_id')->whereNull('original_invoice_id')
                    ->with('customer:id,name')->orderByDesc('invoice_date')->limit(100)->get()
                    ->map(fn (OutgoingInvoice $invoice): array => [
                        'id' => $invoice->id,
                        'label' => $invoice->number.' '.$invoice->customer->name.' — '.number_format((float) $invoice->gross, 2, ',', '.').' €',
                    ])
                : [],
            'canWrite' => Gate::allows('update', $project),
            'canAttach' => Gate::allows('attach', $project),
            'canViewFinancials' => $financials,
            'canManageInvoices' => $financials && Gate::allows('create', IncomingInvoice::class),
        ]);
    }

    public function edit(Project $project): Response
    {
        Gate::authorize('update', $project);

        return Inertia::render('projects/edit', [
            'project' => [
                'id' => $project->id,
                'customer_id' => $project->customer_id,
                'title' => $project->title,
                'site_address' => $project->site_address,
                'description' => $project->description,
                'responsible_user_id' => $project->responsible_user_id,
                'commissioned_on' => $project->commissioned_on?->toDateString(),
                'started_on' => $project->started_on?->toDateString(),
                'planned_finish_on' => $project->planned_finish_on?->toDateString(),
                'finished_on' => $project->finished_on?->toDateString(),
                'status' => $project->status->value,
                'warranty_until' => $project->warranty_until?->toDateString(),
                'notes' => $project->notes,
                'lock_version' => $project->lock_version,
            ],
            'customers' => $this->customerOptions(),
            'users' => $this->userOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        if ($project->isStale($request->integer('lock_version'))) {
            return back()->withErrors([
                'lock_version' => 'Das Projekt wurde zwischenzeitlich geändert. Bitte Seite neu laden.',
            ]);
        }

        $project->update($request->validated());

        return redirect()->route('projects.show', $project)
            ->with('success', 'Änderungen gespeichert.');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function customerOptions(): array
    {
        return Customer::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Customer $customer): array => ['id' => $customer->id, 'name' => $customer->name])
            ->values()->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function supplierOptions(): array
    {
        return Supplier::query()->where('active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn (Supplier $supplier): array => ['id' => $supplier->id, 'name' => $supplier->name])
            ->values()->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function userOptions(): array
    {
        return app(CompanyContext::class)->requireCompany()->users()->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->values()->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (ProjectStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            ProjectStatus::cases(),
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\IncomingPaymentStatus;
use App\Enums\ProjectStatus;
use App\Models\IncomingInvoice;
use App\Models\Project;
use App\Models\User;
use App\Support\Deadlines\DeadlineKind;
use App\Support\Deadlines\DeadlineService;
use App\Support\Invoicing\NumberGapService;
use App\Support\Invoicing\OpenItemsQuery;
use App\Support\Tenancy\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        CompanyContext $context,
        DeadlineService $deadlineService,
        OpenItemsQuery $openItems,
        NumberGapService $numberGaps,
    ): Response {
        /** @var User $user */
        $user = $request->user();

        if ($context->current() === null) {
            return Inertia::render('dashboard', ['hasCompany' => false]);
        }

        $financials = $user->can('view-financials');
        $today = CarbonImmutable::parse(Date::today());

        $all = $deadlineService->upcoming(includeFinancials: $financials);

        // Termine (Kalender: Projekttermine) getrennt von Fristen
        // (Zahlungsziele, Skonto, Aufgaben, Fahrzeuge, ...).
        [$appointments, $deadlines] = $all->partition(
            fn ($deadline) => $deadline->kind === DeadlineKind::Appointment,
        );

        $props = [
            'hasCompany' => true,
            'canViewFinancials' => $financials,
            'activeProjects' => Project::query()->where('status', ProjectStatus::Active->value)->count()
                + Project::query()->where('status', ProjectStatus::Open->value)->count(),
            'deadlines' => $deadlines->take(12)->map(fn ($deadline) => $deadline->toArray($today))->values(),
            'appointments' => $appointments->take(8)->map(fn ($deadline) => $deadline->toArray($today))->values(),
            'overdueDeadlines' => $deadlines->filter(fn ($deadline) => $deadline->isOverdue($today))->count(),
        ];

        if ($financials) {
            $rows = $openItems->rows();

            $props += [
                'openItems' => [
                    'due_now' => round($rows->sum('due_now'), 2),
                    'retained_open' => round($rows->sum('retained_open'), 2),
                    'count' => $rows->count(),
                ],
                // Mahn-Hinweise: überfällige Ausgangsrechnungen mit fälligem Betrag
                'overdueInvoices' => $rows
                    ->filter(fn (array $row): bool => $row['due_now'] > 0.005 && $row['invoice']->due_on->lessThan($today))
                    ->take(8)
                    ->map(fn (array $row): array => [
                        'id' => $row['invoice']->id,
                        'number' => $row['invoice']->number,
                        'customer' => $row['invoice']->customer?->name,
                        'due_on' => $row['invoice']->due_on->toDateString(),
                        'due_now' => $row['due_now'],
                        'days_overdue' => (int) $row['invoice']->due_on->diffInDays($today),
                    ])
                    ->values(),
                'numberGaps' => $numberGaps->checkCurrentFiscalYear(),
                'uncheckedIncoming' => IncomingInvoice::query()->where('checked', false)->count(),
                'openIncoming' => IncomingInvoice::query()
                    ->where('payment_status', '!=', IncomingPaymentStatus::Paid->value)
                    ->count(),
            ];
        }

        return Inertia::render('dashboard', $props);
    }
}

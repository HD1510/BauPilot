<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Deadlines\DeadlineKind;
use App\Support\Deadlines\DeadlineService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class DeadlineController extends Controller
{
    public function __invoke(Request $request, DeadlineService $deadlineService): Response
    {
        /** @var User $user */
        $user = $request->user();
        $today = CarbonImmutable::parse(Date::today());

        $all = $deadlineService->upcoming(includeFinancials: $user->can('view-financials'));

        // Termine getrennt von Fristen — dieselbe Regel wie am Dashboard,
        // an EINER Stelle (serverseitig) entschieden.
        [$appointments, $deadlines] = $all->partition(
            fn ($deadline) => $deadline->kind === DeadlineKind::Appointment,
        );

        return Inertia::render('deadlines/index', [
            'appointments' => $appointments->map(fn ($deadline) => $deadline->toArray($today))->values(),
            'deadlines' => $deadlines->map(fn ($deadline) => $deadline->toArray($today))->values(),
            'horizonDays' => DeadlineService::HORIZON_DAYS,
        ]);
    }
}

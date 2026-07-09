<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        $deadlines = $deadlineService
            ->upcoming(includeFinancials: $user->can('view-financials'))
            ->map(fn ($deadline) => $deadline->toArray($today))
            ->values();

        return Inertia::render('deadlines/index', [
            'deadlines' => $deadlines,
            'horizonDays' => DeadlineService::HORIZON_DAYS,
        ]);
    }
}

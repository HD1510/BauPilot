<?php

namespace App\Http\Controllers;

use App\Support\Ops\SystemHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Erweiterter Health-Endpunkt für den externen Uptime-Check
 * (Architekturblatt Abschnitt 10). /up bleibt der einfache Ping;
 * /up/details liefert Platte, Queue und Scheduler — bei Problemen
 * mit Status 503, damit der Uptime-Check ohne JSON-Parsen alarmiert.
 */
class HealthController extends Controller
{
    public function __invoke(Request $request, SystemHealth $health): JsonResponse
    {
        $token = config('monitoring.health_token');

        if (is_string($token) && $token !== '') {
            abort_unless(hash_equals($token, (string) $request->query('token', '')), 403);
        }

        $report = $health->report();

        return response()->json($report, $report['ok'] ? 200 : 503);
    }
}

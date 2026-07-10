<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SiteReport;
use App\Support\SiteReports\SiteReportRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Regieberichts-Endpunkt (Architekturblatt Abschnitte 9 und M9): EIN
 * Request trägt den ganzen Bericht — Texte, Stunden-Zeilen und optional
 * die Unterschrift. So synchronisiert der Funkloch-Fall mit einer
 * einzigen gepufferten Anfrage nach; client_uuid ist Pflicht.
 */
class SiteReportController extends Controller
{
    public function store(Request $request, SiteReportRecorder $recorder): JsonResponse
    {
        Gate::authorize('create', SiteReport::class);

        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'report_date' => ['required', 'date'],
            'body_text' => ['nullable', 'string', 'max:10000'],
            'material_text' => ['nullable', 'string', 'max:10000'],
            'entries' => ['array'],
            'entries.*.employee_id' => ['required', 'integer'],
            'entries.*.hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'signature' => ['nullable', 'string', 'max:3000000'],
            'client_uuid' => ['required', 'uuid'],
        ]);

        $existing = $recorder->findExisting($validated['client_uuid']);

        if ($existing !== null) {
            return response()->json([
                'id' => $existing->id,
                'number' => $existing->number,
                'status' => 'exists',
            ], 200);
        }

        $project = Project::query()->whereKey((int) $validated['project_id'])->firstOrFail();

        try {
            // Ganz oder gar nicht: scheitert die Unterschrift, bleibt
            // auch kein halbfertiger Bericht übrig.
            $report = DB::transaction(function () use ($recorder, $project, $validated, $request): SiteReport {
                $report = $recorder->create(
                    $project,
                    $validated['report_date'],
                    $validated['body_text'] ?? null,
                    $validated['material_text'] ?? null,
                    $validated['entries'] ?? [],
                    $validated['client_uuid'],
                );

                if (! empty($validated['signature'])) {
                    $recorder->sign($report, $validated['signature'], $request->user());
                }

                return $report;
            });
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'id' => $report->id,
            'number' => $report->number,
            'status' => 'created',
            'report_status' => $report->status->value,
        ], 201);
    }
}

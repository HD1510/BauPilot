<?php

namespace App\Http\Controllers\SiteReports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\SiteReport;
use App\Models\SiteReportEntry;
use App\Support\SiteReports\SiteReportRecorder;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Regieberichte (M9): erfassen (offlinefähig über die Warteschlange),
 * anzeigen, unterschreiben — danach gesperrt.
 */
class SiteReportController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', SiteReport::class);

        return Inertia::render('site-reports/index', [
            'reports' => SiteReport::query()
                ->with('project:id,title')
                ->orderByDesc('number')
                ->get()
                ->map(fn (SiteReport $report): array => [
                    'id' => $report->id,
                    'number' => $report->number,
                    'report_date' => $report->report_date->toDateString(),
                    'project' => $report->project?->title,
                    'status' => $report->status->value,
                    'status_label' => $report->status->label(),
                ]),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', SiteReport::class);

        return Inertia::render('site-reports/create', [
            'projects' => Project::query()->orderBy('title')->get(['id', 'title'])
                ->map(fn (Project $project): array => ['id' => $project->id, 'title' => $project->title]),
            'employees' => Employee::query()->where('active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Employee $employee): array => ['id' => $employee->id, 'name' => $employee->name]),
            // Die Erfassung schickt die Ziel-Firma EXPLIZIT mit (Abschnitt 9) —
            // eingefroren beim Laden der Seite, nicht beim Absenden.
            'companyId' => app(CompanyContext::class)->requireId(),
        ]);
    }

    public function show(SiteReport $siteReport): Response
    {
        Gate::authorize('view', $siteReport);

        $siteReport->load(['project:id,title', 'entries.employee:id,name']);

        return Inertia::render('site-reports/show', [
            'report' => [
                'id' => $siteReport->id,
                'number' => $siteReport->number,
                'report_date' => $siteReport->report_date->toDateString(),
                'project' => $siteReport->project?->title,
                'status' => $siteReport->status->value,
                'status_label' => $siteReport->status->label(),
                'body_text' => $siteReport->body_text,
                'material_text' => $siteReport->material_text,
                'signed_at' => $siteReport->signed_at?->toIso8601String(),
                'has_signature' => $siteReport->signature_path !== null,
                'entries' => $siteReport->entries->map(fn (SiteReportEntry $entry): array => [
                    'employee' => $entry->employee?->name,
                    'hours' => (float) $entry->hours,
                ])->values(),
            ],
            'canSign' => ! $siteReport->isSigned() && Gate::allows('sign', $siteReport),
            'canDelete' => Gate::allows('delete', $siteReport),
        ]);
    }

    /**
     * Unterschrift anzeigen (nur nach Policy-Prüfung, wie Dokumente).
     */
    public function signature(SiteReport $siteReport): HttpResponse
    {
        Gate::authorize('view', $siteReport);

        abort_if($siteReport->signature_path === null, 404);

        $disk = Storage::disk('documents');

        if (config('filesystems.disks.documents.driver') === 's3') {
            return redirect()->away($disk->temporaryUrl($siteReport->signature_path, now()->addMinutes(15)));
        }

        return $disk->response($siteReport->signature_path, 'unterschrift.png', [
            'Content-Disposition' => 'inline',
        ]);
    }

    public function sign(Request $request, SiteReport $siteReport, SiteReportRecorder $recorder): RedirectResponse
    {
        Gate::authorize('sign', $siteReport);

        $validated = $request->validate([
            'signature' => ['required', 'string', 'max:3000000'],
        ], [], ['signature' => 'Unterschrift']);

        try {
            $recorder->sign($siteReport, $validated['signature'], $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['signature' => $e->getMessage()]);
        }

        return back()->with('success', "Regiebericht Nr. {$siteReport->number} unterschrieben — der Bericht ist jetzt gesperrt.");
    }

    public function destroy(SiteReport $siteReport): RedirectResponse
    {
        Gate::authorize('delete', $siteReport);

        $siteReport->delete();

        return redirect()->route('site-reports.index')
            ->with('success', 'Entwurf gelöscht.');
    }
}

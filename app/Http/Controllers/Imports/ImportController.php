<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Models\ImportFinding;
use App\Models\ImportRun;
use App\Support\Import\ExcelImporter;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ImportController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ImportRun::class);

        return Inertia::render('imports/index', [
            'runs' => ImportRun::query()->orderByDesc('created_at')->get()
                ->map(fn (ImportRun $run): array => [
                    'id' => $run->id,
                    'source_filename' => $run->source_filename,
                    'status' => $run->status,
                    'created_at' => $run->created_at?->toIso8601String(),
                    'stats' => $run->stats,
                ]),
        ]);
    }

    public function store(Request $request, ExcelImporter $importer, CompanyContext $context): RedirectResponse
    {
        Gate::authorize('create', ImportRun::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
        ], [], ['file' => 'Excel-Datei']);

        $file = $request->file('file');
        $path = sprintf('imports/%s/%d/%s.xlsx', app()->environment(), $context->requireId(), Str::uuid());

        Storage::disk('documents')->putFileAs(dirname($path), $file, basename($path));

        $run = ImportRun::create([
            'source_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'status' => 'dry_run',
            'stats' => [],
        ]);
        $run->forceFill(['created_by' => $request->user()?->id])->save();

        try {
            $importer->dryRun($run);
        } catch (Throwable $e) {
            $run->delete();
            Storage::disk('documents')->delete($path);

            return back()->withErrors(['file' => 'Die Datei konnte nicht gelesen werden: '.$e->getMessage()]);
        }

        return redirect()->route('imports.show', $run)
            ->with('success', 'Dry-Run abgeschlossen — bitte Prüfbericht kontrollieren.');
    }

    public function show(ImportRun $import): Response
    {
        Gate::authorize('view', $import);

        return Inertia::render('imports/show', [
            'run' => [
                'id' => $import->id,
                'source_filename' => $import->source_filename,
                'status' => $import->status,
                'stats' => $import->stats,
                'created_at' => $import->created_at?->toIso8601String(),
            ],
            'findings' => $import->findings()->orderBy('type')->get()
                ->map(fn (ImportFinding $finding): array => [
                    'id' => $finding->id,
                    'type' => $finding->type,
                    'message' => $finding->message,
                    'payload' => $finding->payload,
                    'decision' => $finding->decision,
                    'needs_decision' => $finding->needsDecision(),
                ]),
            'undecided' => $import->findings()->where('type', 'duplicate')->whereNull('decision')->count(),
        ]);
    }

    public function decide(Request $request, ImportRun $import, ImportFinding $finding): RedirectResponse
    {
        Gate::authorize('update', $import);
        abort_unless($finding->import_run_id === $import->id, 404);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['use_existing', 'create_new'])],
        ], [], ['decision' => 'Entscheidung']);

        $finding->update(['decision' => $validated['decision']]);

        return back()->with('success', 'Entscheidung gespeichert.');
    }

    public function commit(ImportRun $import, ExcelImporter $importer): RedirectResponse
    {
        Gate::authorize('update', $import);

        try {
            $result = $importer->commit($import);
        } catch (RuntimeException $e) {
            return back()->withErrors(['commit' => $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Import übernommen: %d Kunden, %d Lieferanten, %d Ausgangs- und %d Eingangsrechnungen, %d Zahlungen (%d übersprungen, bereits vorhanden).',
            $result['customers'],
            $result['suppliers'],
            $result['outgoing'],
            $result['incoming'],
            $result['payments'],
            $result['skipped'],
        ));
    }
}

<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Projekt-Notizen von Büro und Baustelle (M7).
 */
class ProjectNoteController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('create', ProjectNote::class);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ], [], ['body' => 'Notiz']);

        $project->projectNotes()->create($validated);

        return back()->with('success', 'Notiz gespeichert.');
    }

    public function destroy(ProjectNote $note): RedirectResponse
    {
        Gate::authorize('delete', $note);

        $note->delete();

        return back()->with('success', 'Notiz gelöscht.');
    }
}

<?php

namespace App\Http\Controllers\Sales;

use App\Enums\RoomMaterial;
use App\Enums\RoomShape;
use App\Http\Controllers\Controller;
use App\Models\Calculation;
use App\Models\CalculationRoom;
use App\Models\Project;
use App\Support\Calculation\RoomCalculator;
use App\Support\Calculation\RoomCsvImporter;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Baukalkulation: Räume mit Formen und Belägen erfassen, Gewerke-Mengen
 * und Kosten je Kalkulation — ganz ohne KI, reine Geometrie.
 */
class CalculationController extends Controller
{
    public function __construct(private RoomCalculator $calculator) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Calculation::class);

        $calculations = Calculation::query()
            ->withCount('rooms')
            ->with('project:id,title')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Calculation $calculation): array => [
                'id' => $calculation->id,
                'name' => $calculation->name,
                'rooms_count' => $calculation->rooms_count,
                'project' => $calculation->project?->title,
                'total' => $this->calculator->totals($calculation->rooms, $calculation)['cost'],
            ]);

        return Inertia::render('calculations/index', [
            'calculations' => $calculations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Calculation::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [], ['name' => 'Name']);

        $calculation = Calculation::create($validated);

        return redirect()->route('calculations.show', $calculation)
            ->with('success', "Kalkulation „{$calculation->name}“ wurde angelegt.");
    }

    public function show(Calculation $calculation): Response
    {
        Gate::authorize('view', $calculation);

        $rooms = $calculation->rooms()->orderBy('id')->get();

        return Inertia::render('calculations/show', [
            'calculation' => [
                'id' => $calculation->id,
                'name' => $calculation->name,
                'project_id' => $calculation->project_id,
                'waste_percent' => $calculation->waste_percent,
                'wall_tile_height' => $calculation->wall_tile_height,
                'price_parquet' => $calculation->price_parquet,
                'price_floor_tiles' => $calculation->price_floor_tiles,
                'price_wall_tiles' => $calculation->price_wall_tiles,
                'price_silicone' => $calculation->price_silicone,
                'price_skirting' => $calculation->price_skirting,
                'price_painting' => $calculation->price_painting,
                'notes' => $calculation->notes,
                'lock_version' => $calculation->lock_version,
            ],
            'rooms' => $rooms->map(fn (CalculationRoom $room): array => [
                'id' => $room->id,
                'name' => $room->name,
                'shape' => $room->shape->value,
                'shape_label' => $room->shape->label(),
                'material' => $room->material->value,
                'material_label' => $room->material->label(),
                'length' => $room->length,
                'width' => $room->width,
                'height' => $room->height,
                'length2' => $room->length2,
                'width2' => $room->width2,
                'depth' => $room->depth,
                'area_manual' => $room->area_manual,
                'perimeter_manual' => $room->perimeter_manual,
                'edges' => $room->edges,
                'door_width' => $room->door_width,
                'opening_area' => $room->opening_area,
                'estimated' => $room->estimated,
                'quantities' => $this->calculator->quantities($room, $calculation),
            ])->values(),
            'totals' => $this->calculator->totals($rooms, $calculation),
            'projects' => Project::query()->orderBy('title')->get(['id', 'title'])
                ->map(fn (Project $project): array => ['id' => $project->id, 'title' => $project->title])
                ->values(),
            'shapes' => collect(RoomShape::cases())
                ->map(fn (RoomShape $shape): array => ['value' => $shape->value, 'label' => $shape->label()])
                ->values(),
            'materials' => collect(RoomMaterial::cases())
                ->map(fn (RoomMaterial $material): array => ['value' => $material->value, 'label' => $material->label()])
                ->values(),
        ]);
    }

    public function update(Request $request, Calculation $calculation): RedirectResponse
    {
        Gate::authorize('update', $calculation);

        if ($calculation->isStale($request->integer('lock_version'))) {
            return back()->withErrors([
                'lock_version' => 'Die Kalkulation wurde zwischenzeitlich geändert. Bitte Seite neu laden.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'project_id' => [
                'nullable',
                Rule::exists('projects', 'id')->where('company_id', app(CompanyContext::class)->requireId()),
            ],
            'waste_percent' => ['required', 'numeric', 'between:0,100'],
            'wall_tile_height' => ['required', 'numeric', 'between:0.5,10'],
            'price_parquet' => ['required', 'numeric', 'between:0,10000'],
            'price_floor_tiles' => ['required', 'numeric', 'between:0,10000'],
            'price_wall_tiles' => ['required', 'numeric', 'between:0,10000'],
            'price_silicone' => ['required', 'numeric', 'between:0,10000'],
            'price_skirting' => ['required', 'numeric', 'between:0,10000'],
            'price_painting' => ['required', 'numeric', 'between:0,10000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lock_version' => ['required', 'integer'],
        ], [], [
            'name' => 'Name',
            'project_id' => 'Projekt',
            'waste_percent' => 'Verschnitt',
            'wall_tile_height' => 'Fliesenhöhe',
        ]);

        $calculation->update($validated);

        return back()->with('success', 'Kalkulation gespeichert.');
    }

    public function destroy(Calculation $calculation): RedirectResponse
    {
        Gate::authorize('delete', $calculation);

        $calculation->delete();

        return redirect()->route('calculations.index')
            ->with('success', "Kalkulation „{$calculation->name}“ wurde gelöscht.");
    }

    /**
     * CSV/TXT-Import: flexible Spaltennamen, ganz ohne KI.
     */
    public function import(Request $request, Calculation $calculation): RedirectResponse
    {
        Gate::authorize('update', $calculation);

        $validated = $request->validate([
            'csv' => ['required', 'string', 'max:200000'],
        ], [], ['csv' => 'CSV-Inhalt']);

        ['rows' => $rows, 'skipped' => $skipped] = RoomCsvImporter::parse($validated['csv']);

        foreach ($rows as $row) {
            $calculation->rooms()->create([
                ...$row,
                'company_id' => $calculation->company_id,
            ]);
        }

        if ($rows === []) {
            return back()->withErrors([
                'csv' => 'Keine Räume erkannt — erwartet werden Spalten wie Name, Länge, Breite, Höhe, Material.',
            ]);
        }

        $message = count($rows) === 1 ? '1 Raum importiert.' : count($rows).' Räume importiert.';

        if ($skipped > 0) {
            $message .= " {$skipped} Zeilen übersprungen (Maße fehlen).";
        }

        return back()->with('success', $message);
    }
}

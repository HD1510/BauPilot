<?php

use App\Enums\CompanyRole;
use App\Models\Calculation;
use App\Models\CalculationRoom;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\Testing\File;

/**
 * Baukalkulation: Räume, Preise, CSV-Import und Angebots-Übernahme —
 * Finanzdaten, daher nur für admin/büro.
 */
afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('kalkulation anlegen, raum erfassen, summe entsteht', function () {
    actingMember();

    $this->post('/calculations', ['name' => 'EFH Huber'])->assertRedirect();

    $calculation = Calculation::query()->firstOrFail();
    $calculation->update(['price_parquet' => 40, 'price_skirting' => 10, 'price_painting' => 12]);

    $this->post("/calculations/{$calculation->id}/rooms", [
        'name' => 'Wohnzimmer',
        'shape' => 'rectangle',
        'material' => 'parquet',
        'length' => 5,
        'width' => 4,
        'height' => 2.5,
    ])->assertSessionHasNoErrors();

    // 23 m² × 40 € + 18 lfm × 10 € + 65 m² × 12 € = 1.880 € (JSON
    // liefert ganzzahlige Werte als int).
    $this->get("/calculations/{$calculation->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rooms.0.quantities.parquet_area', 23)
            ->where('rooms.0.quantities.cost', 1880)
            ->where('totals.cost', 1880));
});

test('l-form verlangt die ausschnitt-maße', function () {
    [, $company] = actingMember();
    $calculation = Calculation::factory()->create(['company_id' => $company->id]);

    $this->post("/calculations/{$calculation->id}/rooms", [
        'name' => 'Flur',
        'shape' => 'l_shape',
        'material' => 'parquet',
        'length' => 5,
        'width' => 4,
        'height' => 2.5,
    ])->assertSessionHasErrors(['length2', 'width2']);
});

test('csv-import legt räume mit flexiblen spaltennamen an', function () {
    [, $company] = actingMember();
    $calculation = Calculation::factory()->create(['company_id' => $company->id]);

    $this->post("/calculations/{$calculation->id}/import", [
        'csv' => implode("\n", [
            'Raum;Länge;Breite;Höhe;Belag;Kanten',
            'Wohnzimmer;5,2;4,1;2,5;Parkett;0',
            'Bad;2,4;2,0;2,5;Wandfliesen;2',
            'Kaputt;;2,0;2,5;Parkett;0',
        ]),
    ])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);

    $rooms = CalculationRoom::query()->orderBy('id')->get();

    expect($rooms)->toHaveCount(2)
        ->and($rooms[0]->name)->toBe('Wohnzimmer')
        ->and((float) $rooms[0]->length)->toBe(5.2)
        ->and($rooms[1]->material->value)->toBe('wall_tiles')
        ->and($rooms[1]->edges)->toBe(2);
});

test('angebots-übernahme befüllt summe und beschreibung vor', function () {
    [, $company] = actingMember();
    $calculation = Calculation::factory()->create([
        'company_id' => $company->id,
        'name' => 'EFH Huber',
        'price_parquet' => 40,
    ]);
    CalculationRoom::factory()->create([
        'company_id' => $company->id,
        'calculation_id' => $calculation->id,
        'length' => 5,
        'width' => 4,
        'height' => 2.5,
    ]);

    $this->get("/offers/create?calculation={$calculation->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('prefill.offer_amount_net', '920.00') // 23 m² × 40 €
            ->where('prefill.description', 'Laut Baukalkulation „EFH Huber“'));
});

test('einreichplan-pdf liefert raumstempel und der import legt räume an', function () {
    [, $company] = actingMember();
    $calculation = Calculation::factory()->create(['company_id' => $company->id]);

    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 10);

    foreach ([
        'GARDEROBE4,04 m2RH 2.30mFLIESEN',
        'KINDERZIMMER14,16 m2RH 2.65mHOLZBODEN',
        'GEBAEUDEFRONT 225,23 m2',
    ] as $line) {
        $pdf->Cell(0, 8, $line, 0, 1);
    }

    // FPDF kann kein ² — der Parser versteht auch "m2".
    $response = $this->post("/calculations/{$calculation->id}/plan-scan", [
        'file' => File::createWithContent('plan.pdf', $pdf->Output('S')),
    ])->assertOk();

    expect($response->json('rooms'))->toHaveCount(2)
        ->and($response->json('rooms.0.name'))->toBe('Garderobe')
        ->and($response->json('rooms.0.height'))->toBe(2.3)
        ->and($response->json('rooms.1.material'))->toBe('parquet');

    $this->post("/calculations/{$calculation->id}/plan-import", [
        'rooms' => [
            ['name' => 'Garderobe', 'area' => 4.04, 'height' => 2.3, 'perimeter' => 8.2, 'material' => 'floor_tiles'],
            ['name' => 'Kinderzimmer', 'area' => 14.16, 'height' => 2.65, 'perimeter' => 15.4, 'material' => 'parquet'],
        ],
    ])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);

    $rooms = CalculationRoom::query()->orderBy('id')->get();

    expect($rooms)->toHaveCount(2)
        ->and($rooms[0]->shape->value)->toBe('manual')
        ->and((float) $rooms[0]->area_manual)->toBe(4.04)
        ->and((float) $rooms[0]->perimeter_manual)->toBe(8.2)
        ->and($rooms[0]->estimated)->toBeTrue()
        ->and($rooms[1]->material->value)->toBe('parquet');
});

test('rolle baustelle sieht keine kalkulationen', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $calculation = Calculation::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->get('/calculations')->assertForbidden();
    $this->get("/calculations/{$calculation->id}")->assertForbidden();
    $this->post("/calculations/{$calculation->id}/rooms", [
        'name' => 'Versuch',
        'shape' => 'rectangle',
        'material' => 'parquet',
        'length' => 3,
        'width' => 3,
        'height' => 2.5,
    ])->assertForbidden();
});

test('fremde kalkulation ist unsichtbar (404)', function () {
    $foreign = Calculation::factory()->create();

    actingMember();

    $this->get("/calculations/{$foreign->id}")->assertNotFound();
});

<?php

use App\Enums\CompanyRole;
use App\Models\Material;
use App\Models\Supplier;
use App\Support\Tenancy\CompanyContext;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Illuminate\Http\Testing\File as TestingFile;

/**
 * Material-Scan: Posten aus PDF erkennen (E-Rechnung, Text) und als
 * Artikel übernehmen — je Zeile entscheidet der Mensch.
 */
beforeEach(function () {
    config(['services.anthropic.key' => null]);
});

afterEach(function () {
    app(CompanyContext::class)->clear();
});

function materialListPdf(string ...$lines): string
{
    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 11);

    foreach ($lines as $line) {
        $pdf->Cell(0, 8, utf8_decode($line), 0, 1);
    }

    return $pdf->Output('S');
}

function materialZugferdPdf(): string
{
    $builder = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_EN16931)
        ->setDocumentInformation('RE-2026-0816', '380', new DateTime('2026-07-01'), 'EUR')
        ->setDocumentSeller('Baustoffhandel Nord GmbH')
        ->setDocumentBuyer('Bau GmbH')
        ->addDocumentTax('S', 'VAT', 1476.0, 295.2, 20.0)
        ->setDocumentSummation(1771.2, 1771.2, 1476.0, 0.0, 0.0, 1476.0, 295.2)
        ->addNewPosition('1')
        ->setDocumentPositionProductDetails('Zement CEM II 42,5', null, 'ZEM-425')
        ->setDocumentPositionNetPrice(4.90)
        ->setDocumentPositionQuantity(120, 'H87')
        ->addDocumentPositionTax('S', 'VAT', 20.0)
        ->setDocumentPositionLineSummation(588.0)
        ->addNewPosition('2')
        ->setDocumentPositionProductDetails('Estrichbeton E300', null, 'EST-300')
        ->setDocumentPositionNetPrice(88.80)
        ->setDocumentPositionQuantity(10, 'MTQ')
        ->addDocumentPositionTax('S', 'VAT', 20.0)
        ->setDocumentPositionLineSummation(888.0);

    return ZugferdDocumentPdfBuilder::fromPdfString($builder, materialListPdf('Rechnung RE-2026-0816'))
        ->generateDocument()
        ->downloadString();
}

test('text-pdf: posten werden erkannt, bestehende artikel markiert', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    Material::factory()->create(['company_id' => $company->id, 'name' => 'Ziegel 25er Hochloch']);
    app(CompanyContext::class)->clear();

    $response = $this->post('/materials/scan', [
        'file' => TestingFile::createWithContent('preisliste.pdf', materialListPdf(
            'Preisliste Baustoffhandel Nord',
            '104711 Zement CEM II 42,5 25 Sack 4,90 122,50',
            'ZK-25 Ziegel 25er Hochloch 480 Stk 1,85 888,00',
            'Summe netto 1.010,50',
        )),
    ])->assertOk();

    expect($response->json('source'))->toBe('text')
        ->and($response->json('items'))->toHaveCount(2)
        ->and($response->json('items.0.name'))->toBe('Zement CEM II 42,5')
        ->and($response->json('items.0.article_no'))->toBe('104711')
        ->and($response->json('items.0.package_unit'))->toBe('Sack')
        ->and($response->json('items.0.price_net'))->toBe(4.9)
        ->and($response->json('items.0.exists'))->toBeFalse()
        ->and($response->json('items.1.exists'))->toBeTrue();
});

test('e-rechnung: positionen kommen exakt aus dem xml', function () {
    actingMember();

    $response = $this->post('/materials/scan', [
        'file' => TestingFile::createWithContent('rechnung.pdf', materialZugferdPdf()),
    ])->assertOk();

    expect($response->json('source'))->toBe('e_rechnung')
        ->and($response->json('items'))->toHaveCount(2)
        ->and($response->json('items.0.name'))->toBe('Zement CEM II 42,5')
        ->and($response->json('items.0.article_no'))->toBe('ZEM-425')
        ->and($response->json('items.0.package_unit'))->toBe('Stk')
        ->and($response->json('items.0.price_net'))->toBe(4.9)
        ->and($response->json('items.1.package_unit'))->toBe('m³')
        ->and($response->json('items.1.price_net'))->toBe(88.8);
});

test('foto ohne ki-schlüssel wird verständlich abgelehnt', function () {
    actingMember();

    $this->post('/materials/scan', [
        'file' => TestingFile::image('preisliste.jpg'),
    ])->assertStatus(422);
});

test('import legt gewählte artikel an und überspringt vorhandene', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);
    Material::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'name' => 'Zement CEM II 42,5']);
    app(CompanyContext::class)->clear();

    $this->post('/materials/import', [
        'supplier_id' => $supplier->id,
        'items' => [
            ['name' => 'Zement CEM II 42,5', 'article_no' => '104711', 'package_unit' => 'Sack', 'price_net' => 4.9],
            ['name' => 'Ziegel 25er Hochloch', 'article_no' => 'ZK-25', 'package_unit' => 'Stk', 'price_net' => 1.85],
        ],
    ])->assertSessionHasNoErrors();

    app(CompanyContext::class)->set($company);

    expect(Material::query()->count())->toBe(2);

    $created = Material::query()->where('name', 'Ziegel 25er Hochloch')->firstOrFail();

    expect($created->supplier_id)->toBe($supplier->id)
        ->and($created->article_no)->toBe('ZK-25')
        ->and($created->package_unit)->toBe('Stk')
        ->and((float) $created->price_net)->toBe(1.85);
});

test('rolle baustelle darf weder scannen noch importieren', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $supplier = Supplier::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->post('/materials/scan', [
        'file' => TestingFile::createWithContent('liste.pdf', materialListPdf('Zement 4,90')),
    ])->assertForbidden();

    $this->post('/materials/import', [
        'supplier_id' => $supplier->id,
        'items' => [['name' => 'Zement', 'price_net' => 4.9]],
    ])->assertForbidden();
});

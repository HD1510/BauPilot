<?php

use App\Jobs\ResizeDocumentImage;
use App\Models\Company;
use App\Models\Document;
use App\Models\Project;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Facades\Storage;

afterEach(function () {
    app(CompanyContext::class)->clear();
});

function makeJpeg(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($image, null, 80);
    imagedestroy($image);

    return (string) ob_get_clean();
}

test('der queue-job verkleinert grosse fotos auf 2560 pixel kantenlänge', function () {
    Storage::fake('documents');

    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'documentable_type' => 'project',
        'documentable_id' => $project->id,
        'mime' => 'image/jpeg',
    ]);

    Storage::disk('documents')->put($document->path, makeJpeg(4000, 3000));
    app(CompanyContext::class)->clear();

    (new ResizeDocumentImage($company->id, $document->id))->handle(app(CompanyContext::class));

    $resized = imagecreatefromstring((string) Storage::disk('documents')->get($document->path));

    expect(imagesx($resized))->toBe(ResizeDocumentImage::MAX_EDGE)
        ->and(imagesy($resized))->toBe(1920)
        ->and($document->refresh()->size)->toBeGreaterThan(0);
});

test('kleine bilder und pdfs bleiben unangetastet', function () {
    Storage::fake('documents');

    $company = Company::factory()->create();
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);

    $small = Document::factory()->create([
        'company_id' => $company->id,
        'documentable_type' => 'project',
        'documentable_id' => $project->id,
        'mime' => 'image/jpeg',
    ]);
    $pdf = Document::factory()->create([
        'company_id' => $company->id,
        'documentable_type' => 'project',
        'documentable_id' => $project->id,
        'mime' => 'application/pdf',
    ]);

    $smallContents = makeJpeg(800, 600);
    Storage::disk('documents')->put($small->path, $smallContents);
    Storage::disk('documents')->put($pdf->path, '%PDF-1.7 unangetastet');
    app(CompanyContext::class)->clear();

    (new ResizeDocumentImage($company->id, $small->id))->handle(app(CompanyContext::class));
    app(CompanyContext::class)->clear();
    (new ResizeDocumentImage($company->id, $pdf->id))->handle(app(CompanyContext::class));

    expect(Storage::disk('documents')->get($small->path))->toBe($smallContents)
        ->and(Storage::disk('documents')->get($pdf->path))->toBe('%PDF-1.7 unangetastet');
});

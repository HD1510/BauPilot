<?php

use App\Enums\CompanyRole;
use App\Models\Document;
use App\Models\Project;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Die Vorschau zeigt nur inline, was der Browser nicht als Seite
 * ausführt. Hochgeladenes HTML oder SVG darf nie in der Sitzung des
 * Betrachters laufen — es geht als Download hinaus.
 */
beforeEach(function () {
    Storage::fake('documents');
});

afterEach(function () {
    app(CompanyContext::class)->clear();
});

function uploadForPreview(UploadedFile $file): Document
{
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $project = Project::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    test()->post('/documents', [
        'file' => $file,
        'documentable_type' => 'project',
        'documentable_id' => $project->id,
        'category' => 'other',
    ])->assertSessionHasNoErrors();

    return Document::withoutGlobalScopes()->sole();
}

test('hochgeladenes html wird nie inline ausgeliefert', function () {
    $document = uploadForPreview(
        UploadedFile::fake()->createWithContent('bericht.html', '<html><script>alert(1)</script></html>'),
    );

    $headers = $this->get("/documents/{$document->id}/preview")->baseResponse->headers;

    expect($headers->get('content-disposition'))->toStartWith('attachment')
        ->and($headers->get('x-content-type-options'))->toBe('nosniff');
});

test('svg mit skript geht ebenfalls nur als download hinaus', function () {
    $document = uploadForPreview(
        UploadedFile::fake()->createWithContent(
            'plan.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        ),
    );

    $headers = $this->get("/documents/{$document->id}/preview")->baseResponse->headers;

    expect($headers->get('content-disposition'))->toStartWith('attachment');
});

test('als bild getarntes html wird am inhalt erkannt', function () {
    $document = uploadForPreview(
        UploadedFile::fake()->createWithContent('foto.png', '<html><script>alert(1)</script></html>'),
    );

    $headers = $this->get("/documents/{$document->id}/preview")->baseResponse->headers;

    expect($headers->get('content-disposition'))->toStartWith('attachment');
});

test('echte bilder bleiben in der vorschau inline', function () {
    $document = uploadForPreview(UploadedFile::fake()->image('baustelle.jpg', 40, 30));

    $headers = $this->get("/documents/{$document->id}/preview")->baseResponse->headers;

    expect($headers->get('content-disposition'))->toBe('inline')
        ->and($headers->get('content-type'))->toBe('image/jpeg')
        ->and($headers->get('x-content-type-options'))->toBe('nosniff');
});

test('jede seite trägt die schutz-header', function () {
    actingMember();

    $this->get('/dashboard')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

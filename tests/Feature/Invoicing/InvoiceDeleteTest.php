<?php

use App\Enums\CompanyRole;
use App\Enums\DocumentCategory;
use App\Models\Document;
use App\Models\IncomingInvoice;
use App\Models\OutgoingInvoice;
use App\Models\Payment;
use App\Support\Tenancy\CompanyContext;
use Illuminate\Support\Facades\Storage;

/**
 * Löschen von Ein- und Ausgangsrechnungen: Fehleingaben verschwinden
 * samt Belegen; sobald auf einer Ausgangsrechnung etwas gebucht ist,
 * bleibt nur der Storno-Weg.
 */
beforeEach(function () {
    Storage::fake('documents');
});

afterEach(function () {
    app(CompanyContext::class)->clear();
});

test('eingangsrechnung löschen entfernt auch die belege', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $invoice = IncomingInvoice::factory()->create(['company_id' => $company->id]);
    Storage::disk('documents')->put('testing/beleg.pdf', '%PDF-1.4');
    $document = $invoice->documents()->create([
        'category' => DocumentCategory::Invoice,
        'original_name' => 'beleg.pdf',
        'path' => 'testing/beleg.pdf',
        'size' => 8,
        'mime' => 'application/pdf',
    ]);
    app(CompanyContext::class)->clear();

    $this->delete("/incoming-invoices/{$invoice->id}")
        ->assertRedirect('/incoming-invoices');

    expect(IncomingInvoice::withoutGlobalScopes()->count())->toBe(0)
        ->and(Document::withoutGlobalScopes()->whereKey($document->id)->exists())->toBeFalse();

    Storage::disk('documents')->assertMissing('testing/beleg.pdf');
});

test('ausgangsrechnung ohne buchungen ist löschbar, teilrechnungen werden frei', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $partial = OutgoingInvoice::factory()->create(['company_id' => $company->id, 'doc_type' => 'partial']);
    $final = OutgoingInvoice::factory()->create(['company_id' => $company->id, 'doc_type' => 'final']);
    $partial->forceFill(['final_invoice_id' => $final->id])->save();
    app(CompanyContext::class)->clear();

    $this->delete("/outgoing-invoices/{$final->id}")
        ->assertRedirect('/outgoing-invoices');

    expect(OutgoingInvoice::withoutGlobalScopes()->whereKey($final->id)->exists())->toBeFalse()
        ->and($partial->refresh()->final_invoice_id)->toBeNull();
});

test('ausgangsrechnung mit gebuchter zahlung ist gesperrt — storno ist der weg', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $invoice = OutgoingInvoice::factory()->create(['company_id' => $company->id]);
    Payment::factory()->create([
        'company_id' => $company->id,
        'outgoing_invoice_id' => $invoice->id,
    ]);
    app(CompanyContext::class)->clear();

    $this->delete("/outgoing-invoices/{$invoice->id}")
        ->assertSessionHasErrors('delete');

    expect(OutgoingInvoice::withoutGlobalScopes()->whereKey($invoice->id)->exists())->toBeTrue();
});

test('gutschrift ist löschbar und führt zurück zur originalrechnung', function () {
    [, $company] = actingMember();
    app(CompanyContext::class)->set($company);
    $original = OutgoingInvoice::factory()->create(['company_id' => $company->id]);
    $credit = OutgoingInvoice::factory()->create([
        'company_id' => $company->id,
        'original_invoice_id' => $original->id,
    ]);
    app(CompanyContext::class)->clear();

    $this->delete("/outgoing-invoices/{$credit->id}")
        ->assertRedirect("/outgoing-invoices/{$original->id}");

    expect(OutgoingInvoice::withoutGlobalScopes()->whereKey($credit->id)->exists())->toBeFalse();
});

test('rolle site darf keine rechnungen löschen', function () {
    [, $company] = actingMember(CompanyRole::Site);
    app(CompanyContext::class)->set($company);
    $incoming = IncomingInvoice::factory()->create(['company_id' => $company->id]);
    $outgoing = OutgoingInvoice::factory()->create(['company_id' => $company->id]);
    app(CompanyContext::class)->clear();

    $this->delete("/incoming-invoices/{$incoming->id}")->assertForbidden();
    app(CompanyContext::class)->clear();
    $this->delete("/outgoing-invoices/{$outgoing->id}")->assertForbidden();

    expect(IncomingInvoice::withoutGlobalScopes()->count())->toBe(1)
        ->and(OutgoingInvoice::withoutGlobalScopes()->count())->toBe(1);
});

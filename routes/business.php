<?php

use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Invoicing\IncomingInvoiceController;
use App\Http\Controllers\Invoicing\InvoiceActionController;
use App\Http\Controllers\Invoicing\OutgoingInvoiceController;
use App\Http\Controllers\Projects\ProjectNoteController;
use App\Http\Controllers\Projects\TaskController;
use App\Http\Controllers\Sales\OfferController;
use App\Http\Controllers\Sales\ProjectController;
use App\Http\Controllers\Sales\ProjectSubResourceController;
use App\Http\Controllers\Times\TimeEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Angebote mit Statuslauf und Übernahme ins Projekt
    Route::get('offers', [OfferController::class, 'index'])->name('offers.index');
    Route::get('offers/create', [OfferController::class, 'create'])->name('offers.create');
    Route::post('offers', [OfferController::class, 'store'])->name('offers.store');
    Route::get('offers/{offer}/edit', [OfferController::class, 'edit'])->name('offers.edit');
    Route::patch('offers/{offer}', [OfferController::class, 'update'])->name('offers.update');
    Route::post('offers/{offer}/convert', [OfferController::class, 'convert'])->name('offers.convert');

    // Projekte als Drehscheibe
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');

    // Aufgaben & Mängel und Notizen (M7)
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('projects.tasks.store');
    Route::post('tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::post('tasks/{task}/reopen', [TaskController::class, 'reopen'])->name('tasks.reopen');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('projects/{project}/notes', [ProjectNoteController::class, 'store'])->name('projects.notes.store');
    Route::delete('notes/{note}', [ProjectNoteController::class, 'destroy'])->name('notes.destroy');

    Route::post('projects/{project}/appointments', [ProjectSubResourceController::class, 'storeAppointment'])->name('projects.appointments.store');
    Route::delete('projects/{project}/appointments/{appointment}', [ProjectSubResourceController::class, 'destroyAppointment'])->name('projects.appointments.destroy');
    Route::post('projects/{project}/change-orders', [ProjectSubResourceController::class, 'storeChangeOrder'])->name('projects.change-orders.store');
    Route::patch('projects/{project}/change-orders/{changeOrder}', [ProjectSubResourceController::class, 'updateChangeOrder'])->name('projects.change-orders.update');
    Route::post('projects/{project}/external-offers', [ProjectSubResourceController::class, 'storeExternalOffer'])->name('projects.external-offers.store');
    Route::patch('projects/{project}/external-offers/{externalOffer}', [ProjectSubResourceController::class, 'updateExternalOffer'])->name('projects.external-offers.update');

    // Ausgangsrechnungen: offene Posten, Belegarten, Buchungen
    Route::get('outgoing-invoices', [OutgoingInvoiceController::class, 'index'])->name('outgoing-invoices.index');
    Route::get('outgoing-invoices/create', [OutgoingInvoiceController::class, 'create'])->name('outgoing-invoices.create');
    Route::post('outgoing-invoices', [OutgoingInvoiceController::class, 'store'])->name('outgoing-invoices.store');
    Route::get('outgoing-invoices/{outgoing_invoice}', [OutgoingInvoiceController::class, 'show'])->name('outgoing-invoices.show');
    Route::post('outgoing-invoices/{outgoing_invoice}/payments', [InvoiceActionController::class, 'storePayment'])->name('outgoing-invoices.payments.store');
    Route::post('outgoing-invoices/{outgoing_invoice}/retentions', [InvoiceActionController::class, 'storeRetention'])->name('outgoing-invoices.retentions.store');
    Route::post('outgoing-invoices/{outgoing_invoice}/retentions/{retention}/release', [InvoiceActionController::class, 'releaseRetention'])->name('outgoing-invoices.retentions.release');
    Route::post('outgoing-invoices/{outgoing_invoice}/adjustments', [InvoiceActionController::class, 'storeAdjustment'])->name('outgoing-invoices.adjustments.store');

    // Eingangsrechnungen
    Route::get('incoming-invoices', [IncomingInvoiceController::class, 'index'])->name('incoming-invoices.index');
    Route::get('incoming-invoices/create', [IncomingInvoiceController::class, 'create'])->name('incoming-invoices.create');
    Route::post('incoming-invoices', [IncomingInvoiceController::class, 'store'])->name('incoming-invoices.store');
    Route::get('incoming-invoices/{incoming_invoice}/edit', [IncomingInvoiceController::class, 'edit'])->name('incoming-invoices.edit');
    Route::patch('incoming-invoices/{incoming_invoice}', [IncomingInvoiceController::class, 'update'])->name('incoming-invoices.update');
    Route::post('incoming-invoices/{incoming_invoice}/pay', [IncomingInvoiceController::class, 'pay'])->name('incoming-invoices.pay');
    Route::patch('incoming-invoices/{incoming_invoice}/checked', [IncomingInvoiceController::class, 'toggleChecked'])->name('incoming-invoices.checked');

    // Zeiterfassung (M8): Schnellerfassung je Projekt
    Route::get('time-entries', [TimeEntryController::class, 'index'])->name('time-entries.index');
    Route::post('time-entries', [TimeEntryController::class, 'store'])->name('time-entries.store');
    Route::delete('time-entries/{time_entry}', [TimeEntryController::class, 'destroy'])->name('time-entries.destroy');

    // Datei-Anhänge (polymorph, privat)
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
});

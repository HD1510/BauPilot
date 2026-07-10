<?php

use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ProjectNoteController;
use App\Http\Controllers\Api\SiteReportController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TimeEntryController;
use App\Http\Middleware\SetCompanyFromRequest;
use Illuminate\Support\Facades\Route;

// JSON-Endpunkte der Baustellen-Funktionen (Architekturblatt Abschnitt 9):
// Session-Auth (Sanctum-Cookie-Modus), explizite company_id je Request,
// Idempotenz über client_uuid — die Andockstelle für den Offline-Puffer
// in M9 und für eine eventuelle spätere App.
Route::middleware(['auth', SetCompanyFromRequest::class])->prefix('api')->name('api.')->group(function () {
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::post('tasks/{task}/complete', [TaskController::class, 'complete'])->name('tasks.complete');
    Route::post('project-notes', [ProjectNoteController::class, 'store'])->name('project-notes.store');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::post('time-entries', [TimeEntryController::class, 'store'])->name('time-entries.store');
    Route::post('site-reports', [SiteReportController::class, 'store'])->name('site-reports.store');
});

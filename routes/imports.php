<?php

use App\Http\Controllers\Imports\ImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::post('imports', [ImportController::class, 'store'])->name('imports.store');
    Route::get('imports/{import}', [ImportController::class, 'show'])->name('imports.show');
    Route::patch('imports/{import}/findings/{finding}', [ImportController::class, 'decide'])->name('imports.decide');
    Route::post('imports/{import}/commit', [ImportController::class, 'commit'])->name('imports.commit');
});

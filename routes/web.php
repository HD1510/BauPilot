<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeadlineController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\NotificationSettingController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Betriebsüberwachung (Abschnitt 10): /up bleibt der einfache Ping des
// Frameworks, /up/details meldet Platte, Queue-Rückstau und Scheduler.
Route::get('up/details', HealthController::class)->name('health.details');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('deadlines', DeadlineController::class)->name('deadlines.index');
    Route::patch('settings/notifications', [NotificationSettingController::class, 'update'])->name('notifications.update');
});

require __DIR__.'/settings.php';
require __DIR__.'/companies.php';
require __DIR__.'/master-data.php';
require __DIR__.'/business.php';
require __DIR__.'/imports.php';

<?php

use App\Support\Ops\SystemHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Tägliche Zusammenfassung um 06:00 Europe/Vienna (Architekturblatt
// Abschnitt 7). Der Scheduler läuft als minütlicher Cron-Eintrag.
Schedule::command('baupilot:daily-digest')
    ->dailyAt('06:00')
    ->timezone('Europe/Vienna');

// Heartbeat für den Health-Endpunkt (Abschnitt 10): fällt der Cron aus,
// altert dieser Zeitstempel und /up/details meldet 503.
Schedule::call(function (): void {
    Cache::put(SystemHealth::SCHEDULER_CACHE_KEY, now()->toIso8601String());
})->everyMinute()->name('scheduler-heartbeat');

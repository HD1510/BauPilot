<?php

use Illuminate\Support\Facades\Schedule;

// Tägliche Zusammenfassung um 06:00 Europe/Vienna (Architekturblatt
// Abschnitt 7). Der Scheduler läuft als minütlicher Cron-Eintrag.
Schedule::command('baupilot:daily-digest')
    ->dailyAt('06:00')
    ->timezone('Europe/Vienna');

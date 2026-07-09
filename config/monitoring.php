<?php

// Betriebsüberwachung (Architekturblatt Abschnitt 10): Schwellwerte für
// den erweiterten Health-Endpunkt /up/details. Der externe Uptime-Check
// bekommt bei Überschreitung einen 503 und schlägt Alarm.
return [

    // Schützt /up/details vor neugierigen Augen: ist ein Token gesetzt,
    // muss es als ?token=… mitkommen. Leer = offen (nur lokal sinnvoll).
    'health_token' => env('HEALTH_CHECK_TOKEN'),

    // Ab diesem Füllstand der Platte (Prozent belegt) gilt der Server
    // als gefährdet — volle Platte heißt: keine Uploads, keine Backups.
    'disk_warn_used_percent' => (int) env('HEALTH_DISK_WARN_PERCENT', 90),

    // Wartet der älteste unerledigte Queue-Job länger als so viele
    // Minuten, hängt vermutlich der Worker.
    'queue_max_age_minutes' => (int) env('HEALTH_QUEUE_MAX_AGE_MINUTES', 15),

    // Liegt der letzte Scheduler-Lauf länger zurück, ist der Cron
    // ausgefallen — sonst fällt das erst auf, wenn der Digest ausbleibt.
    'scheduler_max_age_minutes' => (int) env('HEALTH_SCHEDULER_MAX_AGE_MINUTES', 10),

];

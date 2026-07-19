# Betrieb und Deployment

Vorlagen für den Serverbetrieb laut Architekturblatt Abschnitt 10
(eine Hetzner-Instanz, Nginx + PHP-FPM + PostgreSQL, Deploy über
Laravel Forge oder Ploi). Alles hier sind **Vorlagen** — Pfade,
Benutzer und Domain beim Einrichten anpassen.

## Dateien

| Datei | Zweck |
| --- | --- |
| `deploy.sh` | Deploy-Ablauf (Composer, npm build, Migrationen, Caches, Worker-Neustart) — als Forge-/Ploi-Deploy-Skript oder von Hand |
| `baupilot-worker.service` | systemd-Vorlage für den Queue-Worker (Datenbank-Treiber, automatischer Neustart) |

## Scheduler (Cron)

Der Laravel-Scheduler läuft als minütlicher Cron-Eintrag des
Web-Benutzers (Forge/Ploi richten das über ihre Scheduler-Funktion ein):

```cron
* * * * * cd /var/www/baupilot && php artisan schedule:run >> /dev/null 2>&1
```

Er verschickt den täglichen Digest (06:00 Europe/Vienna) und schreibt
minütlich den Heartbeat für die Betriebsüberwachung.

## Betriebsüberwachung

Zwei Endpunkte für den externen Uptime-Check:

- `GET /up` — einfacher Ping (Framework-Standard, immer 200 solange die App läuft).
- `GET /up/details?token=…` — meldet Plattenfüllstand, Queue-Rückstau
  (Alter des ältesten wartenden Jobs) und den Zeitstempel des letzten
  Scheduler-Laufs. Bei Überschreiten der Schwellwerte antwortet er mit
  **503**, der Uptime-Check schlägt also ohne JSON-Parsen Alarm.

Konfiguration in `config/monitoring.php` bzw. `.env`:
`HEALTH_CHECK_TOKEN` (Zugriffsschutz, in Produktion setzen),
`HEALTH_DISK_WARN_PERCENT` (Standard 90), `HEALTH_QUEUE_MAX_AGE_MINUTES`
(15), `HEALTH_SCHEDULER_MAX_AGE_MINUTES` (10).

Den Uptime-Check auf `/up/details` zeigen lassen — dann ist ein
hängender Worker oder ausgefallener Cron sofort sichtbar, nicht erst
beim ausbleibenden Digest.

## BauPilot als Programm am Arbeitsplatz (PWA)

BauPilot ist als PWA installierbar — Voraussetzung ist nur HTTPS
(lokal genügt `localhost`). Auf Windows-Rechnern: Seite in **Edge oder
Chrome** öffnen, dann Menü → **„BauPilot installieren“** (Edge:
„Apps → Diese Website als App installieren“). Danach läuft BauPilot
im eigenen Fenster mit Icon in Startmenü/Taskleiste; Updates kommen
automatisch mit jedem Deploy, es ist nichts zu verteilen. Am Handy
analog über „Zum Startbildschirm hinzufügen“ — darüber kommt später
auch Web-Push (M6).

## Backup (Erinnerung, außerhalb des Repos)

Nächtlicher `pg_dump`, asymmetrisch verschlüsselt (age/GPG, privater
Schlüssel offline), Write-only in einen eigenen Backup-Bucket, 30 Tage
Aufbewahrung, wöchentlicher Server-Snapshot. Der Backup-Job pingt nach
jedem erfolgreichen Upload einen Healthcheck-Dienst (Dead-Man-Switch).
Einmal im Monat testweise auf Staging zurückspielen. Details:
Architekturblatt Abschnitt 10.

## Datei-Uploads (Nginx/PHP-Limits)

Einreichpläne und gescannte Belege sind schnell größer als 1 MB — die
Standardwerte reichen dafür nicht. In Forge (bzw. am Server) setzen:

- Nginx-Site-Konfiguration: `client_max_body_size 50m;` (Standard 1m —
  darüber antwortet Nginx mit 413, bevor die Anwendung etwas sieht)
- PHP (FPM): `upload_max_filesize = 50M`, `post_max_size = 50M`
  (in Forge unter PHP → Max File Upload Size einstellbar)

Das PHP-`memory_limit` muss NICHT erhöht werden: Die Anwendung hebt es
für das Parsen großer CAD-PDFs selbst request-weise an
(`App\Support\Pdf\PdfTextExtractor`).

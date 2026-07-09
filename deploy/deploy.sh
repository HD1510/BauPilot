#!/usr/bin/env bash
#
# BauPilot — Deploy-Skript-Vorlage (Architekturblatt Abschnitt 10).
#
# Gedacht als Inhalt des Deploy-Skripts in Laravel Forge oder Ploi
# (dort läuft es nach dem Git-Pull im Release-Verzeichnis). Es
# funktioniert aber genauso von Hand auf dem Server.
#
# Voraussetzungen am Server: PHP 8.5 (FPM), Composer 2, Node 22 (nur
# für den Build), PostgreSQL, der Queue-Worker als systemd-Dienst
# (siehe baupilot-worker.service) und der Scheduler-Cron (siehe
# README.md in diesem Verzeichnis).

set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Abhängigkeiten (ohne Dev, optimierter Autoloader)"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "==> Frontend bauen"
npm ci --no-audit --no-fund
npm run build

echo "==> Wartungsmodus (kurz, nur für Migrationen)"
# Forge/Ploi-Zero-Downtime ersetzt diesen Block durch den Release-Wechsel;
# beim einfachen Deploy in place schützt er die Migration.
php artisan down --retry=15 || true

echo "==> Datenbank migrieren"
php artisan migrate --force

echo "==> Caches neu aufbauen"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Queue-Worker neu starten (lädt den neuen Code)"
php artisan queue:restart

php artisan up

echo "==> Fertig. Gegenprobe: curl -fsS \$APP_URL/up"

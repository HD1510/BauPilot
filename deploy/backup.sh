#!/usr/bin/env bash
#
# Nächtliches Backup: Datenbank-Dump und Dokumentenablage, verschlüsselt
# mit age (asymmetrisch — am Server liegt nur der ÖFFENTLICHE Schlüssel,
# zurücklesen kann nur, wer den privaten offline verwahrt).
#
# Einrichtung und Rückspielen: deploy/README.md, Abschnitt „Backup".
#
# Konfiguration über Umgebungsvariablen (z. B. aus ~/.baupilot-backup.env,
# NICHT im Repository):
#   BACKUP_AGE_RECIPIENT   öffentlicher age-Schlüssel (age1…)        Pflicht
#   BACKUP_DIR             Zielordner am Server      Standard ~/backups/baupilot
#   BACKUP_KEEP_DAYS       lokale Aufbewahrung in Tagen               Standard 30
#   BACKUP_RCLONE_REMOTE   rclone-Ziel für die Auslagerung, z. B.
#                          „hetzner:baupilot-backup" (leer = nur lokal)
#   BACKUP_HEALTHCHECK_URL wird nach Erfolg angepingt (Dead-Man-Switch)
#   APP_DIR                Anwendungsordner        Standard: Ordner über deploy/

set -euo pipefail
umask 077

CONFIG_FILE="${BACKUP_CONFIG:-$HOME/.baupilot-backup.env}"
if [ -f "$CONFIG_FILE" ]; then
    set -a
    # shellcheck disable=SC1090
    . "$CONFIG_FILE"
    set +a
fi

APP_DIR="${APP_DIR:-$(cd "$(dirname "$0")/.." && pwd)}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/baupilot}"
BACKUP_KEEP_DAYS="${BACKUP_KEEP_DAYS:-30}"

if [ -z "${BACKUP_AGE_RECIPIENT:-}" ]; then
    echo "BACKUP_AGE_RECIPIENT fehlt (öffentlicher age-Schlüssel)." >&2
    exit 1
fi

for tool in pg_dump age gzip tar; do
    command -v "$tool" >/dev/null || { echo "Werkzeug fehlt: $tool" >&2; exit 1; }
done

# Zugangsdaten der Datenbank aus der .env der Anwendung lesen.
env_value() {
    grep -E "^$1=" "$APP_DIR/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}

DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"
PGPASSWORD="$(env_value DB_PASSWORD)"
export PGPASSWORD

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

STAMP="$(date +%Y-%m-%d_%H%M)"
DB_FILE="$BACKUP_DIR/db-$STAMP.sql.gz.age"
DOCS_FILE="$BACKUP_DIR/dokumente-$STAMP.tar.gz.age"

# Unverschlüsselt berührt nichts die Platte: Dump und Archiv laufen
# direkt durch die Verschlüsselung.
pg_dump --host="${DB_HOST:-127.0.0.1}" --port="${DB_PORT:-5432}" \
    --username="$DB_USERNAME" --no-owner --no-privileges "$DB_DATABASE" \
    | gzip | age --recipient "$BACKUP_AGE_RECIPIENT" --output "$DB_FILE.tmp"
mv "$DB_FILE.tmp" "$DB_FILE"

DOCS_DIR="$APP_DIR/storage/app/documents"
if [ -d "$DOCS_DIR" ]; then
    tar --create --gzip --directory "$DOCS_DIR" . \
        | age --recipient "$BACKUP_AGE_RECIPIENT" --output "$DOCS_FILE.tmp"
    mv "$DOCS_FILE.tmp" "$DOCS_FILE"
fi

# Ein leerer Dump ist kein Backup.
if [ ! -s "$DB_FILE" ]; then
    echo "Datenbank-Dump ist leer." >&2
    exit 1
fi

if [ -n "${BACKUP_RCLONE_REMOTE:-}" ]; then
    command -v rclone >/dev/null || { echo "rclone fehlt." >&2; exit 1; }
    rclone copy "$DB_FILE" "$BACKUP_RCLONE_REMOTE"
    [ -f "$DOCS_FILE" ] && rclone copy "$DOCS_FILE" "$BACKUP_RCLONE_REMOTE"
fi

find "$BACKUP_DIR" -name '*.age' -type f -mtime "+$BACKUP_KEEP_DAYS" -delete

if [ -n "${BACKUP_HEALTHCHECK_URL:-}" ]; then
    curl --fail --silent --show-error --max-time 20 "$BACKUP_HEALTHCHECK_URL" >/dev/null
fi

echo "Backup fertig: $(basename "$DB_FILE")$([ -f "$DOCS_FILE" ] && echo ", $(basename "$DOCS_FILE")")"

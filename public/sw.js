// BauPilot Service Worker — bewusst minimal (Architekturblatt Abschnitt 9:
// die Offline-Warteschlange für Regieberichte kommt erst in M9).
//
// Heute macht er genau zwei Dinge:
//  1. Er macht die PWA installierbar (eigenes Fenster am Desktop/Handy).
//  2. Navigationen laufen strikt übers Netz (kein Caching von App-Seiten —
//     keine veralteten Inertia-Antworten); nur wenn das Netz weg ist,
//     erscheint die Offline-Seite.
//
// Push-Empfang (Web-Push, M6) wird hier später ergänzt.

const OFFLINE_CACHE = 'baupilot-offline-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(OFFLINE_CACHE)
            .then((cache) => cache.addAll([OFFLINE_URL]))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== OFFLINE_CACHE)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    // Nur Seiten-Navigationen abfangen; alles andere (Assets, XHR,
    // Uploads) geht unverändert durch.
    if (event.request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(event.request).catch(() =>
            caches
                .match(OFFLINE_URL)
                .then((cached) => cached ?? Response.error()),
        ),
    );
});

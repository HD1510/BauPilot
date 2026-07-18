/**
 * Offline-Warteschlange für die JSON-Endpunkte (Architekturblatt
 * Abschnitt 9, M9): fehlgeschlagene Schreib-Requests werden sichtbar in
 * IndexedDB gesammelt und bei Verbindung erneut gesendet.
 *
 * Replay-Protokoll (v1.1):
 *  - Vor dem Abarbeiten wird ein frisches CSRF-Token geholt, damit
 *    gepufferte Requests nicht mit 419 scheitern.
 *  - 401/419 sind KEIN endgültiger Fehler: die Warteschlange bleibt
 *    stehen, bis die Anmeldung wieder gilt.
 *  - Fachliche Ablehnungen (403/404/422) werden verworfen und gemeldet —
 *    erneut senden würde sie nicht besser machen.
 *  - Jede Payload trägt client_uuid und company_id: Wiederholungen sind
 *    idempotent und landen nie in der falschen Firma.
 */

const DB_NAME = 'baupilot-offline';
const STORE = 'queue';

export type QueuedRequest = {
    id?: number;
    url: string;
    payload: Record<string, unknown>;
    label: string;
    queuedAt: string;
};

type Listener = (pending: number) => void;

const listeners = new Set<Listener>();
let flushing = false;

function openDb(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains(STORE)) {
                request.result.createObjectStore(STORE, {
                    keyPath: 'id',
                    autoIncrement: true,
                });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function withStore<T>(
    mode: IDBTransactionMode,
    action: (store: IDBObjectStore) => IDBRequest<T>,
): Promise<T> {
    const db = await openDb();

    return new Promise<T>((resolve, reject) => {
        const tx = db.transaction(STORE, mode);
        const request = action(tx.objectStore(STORE));
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
        tx.oncomplete = () => db.close();
    });
}

export async function pendingRequests(): Promise<QueuedRequest[]> {
    return withStore(
        'readonly',
        (store) => store.getAll() as IDBRequest<QueuedRequest[]>,
    );
}

async function notify(): Promise<void> {
    const pending = await pendingRequests();
    listeners.forEach((listener) => listener(pending.length));
}

export function subscribePending(listener: Listener): () => void {
    listeners.add(listener);
    void notify();

    return () => listeners.delete(listener);
}

export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function refreshCsrf(): Promise<void> {
    // Jede Antwort einer Session-Route erneuert das XSRF-Cookie.
    await fetch('/', {
        credentials: 'same-origin',
        headers: { Accept: 'text/html' },
    });
}

async function send(
    url: string,
    payload: Record<string, unknown>,
): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(payload),
    });
}

export type SubmitResult =
    | { queued: false; ok: boolean; status: number; body: unknown }
    | { queued: true };

/**
 * Sofort senden; ohne Netz (oder bei Netzfehler) in die Warteschlange.
 */
export async function submitOrQueue(
    url: string,
    payload: Record<string, unknown>,
    label: string,
): Promise<SubmitResult> {
    try {
        const response = await send(url, payload);

        if (response.status === 419) {
            // Abgelaufenes Token: einmal auffrischen und wiederholen.
            await refreshCsrf();
            const retry = await send(url, payload);

            return {
                queued: false,
                ok: retry.ok,
                status: retry.status,
                body: await retry.json().catch(() => null),
            };
        }

        return {
            queued: false,
            ok: response.ok,
            status: response.status,
            body: await response.json().catch(() => null),
        };
    } catch {
        // Netzfehler → Funkloch: sichtbar puffern.
        await withStore('readwrite', (store) =>
            store.add({
                url,
                payload,
                label,
                queuedAt: new Date().toISOString(),
            }),
        );
        await notify();

        return { queued: true };
    }
}

export type FlushSummary = { sent: number; kept: number; dropped: string[] };

/**
 * Warteschlange abarbeiten — der Reihe nach, ältester Eintrag zuerst.
 */
export async function flushQueue(): Promise<FlushSummary> {
    const summary: FlushSummary = { sent: 0, kept: 0, dropped: [] };

    if (flushing || !navigator.onLine) {
        return summary;
    }

    flushing = true;

    try {
        const items = await pendingRequests();

        if (items.length === 0) {
            return summary;
        }

        await refreshCsrf();

        for (const item of items) {
            try {
                const response = await send(item.url, item.payload);

                if (response.status === 401 || response.status === 419) {
                    // Anmeldung abgelaufen: Queue anhalten, nichts verwerfen.
                    summary.kept +=
                        items.length - summary.sent - summary.dropped.length;
                    break;
                }

                if (response.ok) {
                    summary.sent++;
                } else {
                    // 403/404/422: endgültig — melden statt ewig wiederholen.
                    summary.dropped.push(item.label);
                }

                await withStore('readwrite', (store) =>
                    store.delete(item.id as number),
                );
            } catch {
                // Wieder offline: Rest bleibt stehen.
                summary.kept +=
                    items.length - summary.sent - summary.dropped.length;
                break;
            }
        }

        return summary;
    } finally {
        flushing = false;
        await notify();
    }
}

/**
 * Einmal beim App-Start aufrufen: synchronisiert bei Verbindung.
 */
export function initOfflineQueue(
    onFlushed?: (summary: FlushSummary) => void,
): void {
    const run = () => {
        void flushQueue().then((summary) => {
            if (summary.sent > 0 || summary.dropped.length > 0) {
                onFlushed?.(summary);
            }
        });
    };

    window.addEventListener('online', run);
    run();
}

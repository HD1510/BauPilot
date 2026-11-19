import { router } from '@inertiajs/react';

type DeleteOptions = Parameters<typeof router.delete>[1];

/**
 * Löschen nur nach Rückfrage — ein versehentlicher Tipp auf den
 * Papierkorb darf nichts endgültig entfernen.
 */
export function confirmDelete(
    url: string,
    question: string,
    options: DeleteOptions = { preserveScroll: true },
): void {
    if (window.confirm(question)) {
        router.delete(url, options);
    }
}

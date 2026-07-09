import { usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Duplicate = { id: number; name: string; similarity: number };

/**
 * Dubletten-Vorschlag bei der Anlage (Architekturblatt Abschnitt 5):
 * Der Server hat ähnliche Einträge gefunden; angelegt wird erst nach
 * ausdrücklicher Bestätigung.
 */
export function DuplicatesWarning({
    editPath,
    onForce,
    processing,
}: {
    editPath: (id: number) => string;
    onForce: () => void;
    processing: boolean;
}) {
    const { flash } = usePage().props;
    const duplicates = (flash?.duplicates ?? null) as Duplicate[] | null;

    if (!duplicates || duplicates.length === 0) {
        return null;
    }

    return (
        <div className="max-w-xl space-y-3 rounded-lg border border-amber-500/50 bg-amber-50 p-4 dark:bg-amber-950/30">
            <div className="flex items-center gap-2 font-medium text-amber-900 dark:text-amber-200">
                <AlertTriangle className="size-4" />
                Ähnliche Einträge gefunden
            </div>
            <ul className="space-y-1 text-sm">
                {duplicates.map((duplicate) => (
                    <li key={duplicate.id}>
                        <a
                            href={editPath(duplicate.id)}
                            className="underline underline-offset-2"
                        >
                            {duplicate.name}
                        </a>{' '}
                        <span className="text-muted-foreground">
                            ({Math.round(duplicate.similarity * 100)} % ähnlich)
                        </span>
                    </li>
                ))}
            </ul>
            <p className="text-sm text-muted-foreground">
                Bitte prüfen, ob der Eintrag schon existiert. Zusammengeführt
                wird nie automatisch.
            </p>
            <Button
                type="button"
                variant="outline"
                disabled={processing}
                onClick={onForce}
            >
                Trotzdem anlegen
            </Button>
        </div>
    );
}

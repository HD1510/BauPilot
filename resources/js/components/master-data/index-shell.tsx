import { Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type IndexFilters = { q: string; archived: boolean };

/**
 * Gemeinsames Gerüst der Stammdaten-Listen: Überschrift, Suche (entprellt),
 * Filter für archivierte Einträge, Anlegen-Knopf.
 */
export function IndexShell({
    title,
    description,
    basePath,
    createLabel,
    filters,
    archivedLabel = 'Archivierte anzeigen',
    children,
}: {
    title: string;
    description: string;
    basePath: string;
    createLabel?: string;
    filters: IndexFilters;
    archivedLabel?: string;
    children: React.ReactNode;
}) {
    const [q, setQ] = useState(filters.q);
    const first = useRef(true);

    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                basePath,
                { q: q || undefined, archived: filters.archived ? 1 : undefined },
                { preserveState: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <div className="flex items-center justify-between gap-4">
                <Heading title={title} description={description} />
                {createLabel && (
                    <Button asChild>
                        <Link href={`${basePath}/create`}>
                            <Plus className="size-4" />
                            {createLabel}
                        </Link>
                    </Button>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-4">
                <Input
                    value={q}
                    onChange={(event) => setQ(event.target.value)}
                    placeholder="Suchen …"
                    className="max-w-xs"
                    aria-label="Suchen"
                />
                <Label className="flex items-center gap-2 text-sm font-normal">
                    <Checkbox
                        checked={filters.archived}
                        onCheckedChange={(checked) =>
                            router.get(
                                basePath,
                                {
                                    q: q || undefined,
                                    archived: checked ? 1 : undefined,
                                },
                                { preserveState: true, replace: true },
                            )
                        }
                    />
                    {archivedLabel}
                </Label>
            </div>

            <div className="grid gap-2">{children}</div>
        </div>
    );
}

export function EmptyState({ archived }: { archived: boolean }) {
    return (
        <p className="py-8 text-center text-muted-foreground">
            {archived
                ? 'Keine archivierten Einträge.'
                : 'Noch keine Einträge — legen Sie den ersten an.'}
        </p>
    );
}

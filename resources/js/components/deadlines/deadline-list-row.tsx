import { Link, router, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { DueDate, dueHint } from '@/components/deadlines/due-date';

export type DeadlineRow = {
    kind: string;
    kind_label: string;
    due_on: string;
    title: string;
    subtitle: string | null;
    url: string;
    overdue: boolean;
    complete_url?: string | null;
};

/**
 * Zeile für Fristen und Termine: das Datum steht groß und gefärbt
 * vorne, daneben Titel und „in X Tagen" — die Dringlichkeit ist auf
 * einen Blick erfassbar. Wiederkehrende Fristen (Pickerl, Vignette,
 * Fahrzeug-Termine) lassen sich direkt per Häkchen erledigen.
 */
export function DeadlineListRow({ row }: { row: DeadlineRow }) {
    const { tenancy } = usePage().props;
    const canComplete = Boolean(row.complete_url) && tenancy.role !== 'site';

    return (
        <Link
            href={row.url}
            className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-2.5 text-sm hover:bg-accent/50 dark:border-sidebar-border"
        >
            <DueDate dueOn={row.due_on} overdue={row.overdue} />
            <div className="min-w-0 flex-1">
                <p className="truncate font-medium">{row.title}</p>
                <p className="truncate text-xs text-muted-foreground">
                    {[dueHint(row.due_on, row.overdue), row.subtitle]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
            </div>
            <span className="shrink-0 text-xs text-muted-foreground">
                {row.kind_label}
            </span>
            {canComplete && (
                <button
                    type="button"
                    title="Erledigt — Termin ein Jahr weiterschieben"
                    aria-label={`${row.title} erledigt`}
                    className="shrink-0 rounded-full border border-sidebar-border/70 p-1.5 text-muted-foreground hover:border-primary hover:bg-primary hover:text-primary-foreground dark:border-sidebar-border"
                    onClick={(event) => {
                        event.preventDefault();
                        event.stopPropagation();

                        if (
                            confirm(
                                `„${row.title}" als erledigt markieren? Der Termin wird ein Jahr weitergeschoben.`,
                            )
                        ) {
                            router.post(
                                row.complete_url as string,
                                {},
                                { preserveScroll: true },
                            );
                        }
                    }}
                >
                    <Check className="size-3.5" />
                </button>
            )}
        </Link>
    );
}

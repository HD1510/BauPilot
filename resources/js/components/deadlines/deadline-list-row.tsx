import { Link } from '@inertiajs/react';
import { DueDate, dueHint } from '@/components/deadlines/due-date';

export type DeadlineRow = {
    kind: string;
    kind_label: string;
    due_on: string;
    title: string;
    subtitle: string | null;
    url: string;
    overdue: boolean;
};

/**
 * Zeile für Fristen und Termine: das Datum steht groß und gefärbt
 * vorne, daneben Titel und „in X Tagen" — die Dringlichkeit ist auf
 * einen Blick erfassbar.
 */
export function DeadlineListRow({ row }: { row: DeadlineRow }) {
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
        </Link>
    );
}

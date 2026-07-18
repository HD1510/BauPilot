import { cn } from '@/lib/utils';

function daysFromToday(dueOn: string): number {
    const date = new Date(`${dueOn}T00:00:00`);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return Math.round((date.getTime() - today.getTime()) / 86400000);
}

/**
 * In wie vielen Tagen? Kurz und deutsch — „seit 3 Tagen überfällig",
 * „heute", „morgen", „in 12 Tagen".
 */
export function dueHint(dueOn: string, overdue: boolean): string {
    const days = daysFromToday(dueOn);

    if (overdue) {
        return days === -1
            ? 'seit gestern überfällig'
            : `seit ${Math.abs(days)} Tagen überfällig`;
    }

    if (days === 0) {
        return 'heute';
    }

    if (days === 1) {
        return 'morgen';
    }

    return `in ${days} Tagen`;
}

/**
 * Datum einer Frist bzw. eines Termins — groß und auf einen Blick:
 * Tag fett, Monat darunter, gefärbt nach Dringlichkeit (überfällig rot,
 * die nächsten 3 Tage orange, sonst neutral).
 */
export function DueDate({
    dueOn,
    overdue,
}: {
    dueOn: string;
    overdue: boolean;
}) {
    const date = new Date(`${dueOn}T00:00:00`);
    const days = daysFromToday(dueOn);
    const urgency = overdue ? 'overdue' : days <= 3 ? 'soon' : 'normal';

    return (
        <div
            className={cn(
                'flex w-12 shrink-0 flex-col items-center rounded-lg border py-1.5',
                urgency === 'overdue' &&
                    'border-red-300 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-400',
                urgency === 'soon' &&
                    'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-400',
                urgency === 'normal' &&
                    'border-sidebar-border/70 text-foreground dark:border-sidebar-border',
            )}
        >
            <span className="text-lg leading-none font-bold">
                {date.getDate()}
            </span>
            <span className="text-[10px] leading-tight uppercase">
                {date.toLocaleDateString('de-AT', { month: 'short' })}
            </span>
        </div>
    );
}

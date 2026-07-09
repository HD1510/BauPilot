import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/format';

type DeadlineRow = {
    kind: string;
    kind_label: string;
    due_on: string;
    title: string;
    subtitle: string | null;
    url: string;
    overdue: boolean;
};

export default function DeadlinesIndex({
    deadlines,
    horizonDays,
}: {
    deadlines: DeadlineRow[];
    horizonDays: number;
}) {
    const overdue = deadlines.filter((deadline) => deadline.overdue);
    const upcoming = deadlines.filter((deadline) => !deadline.overdue);

    return (
        <>
            <Head title="Fristen" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Fristen"
                    description={`Alle Termine und Zahlungsziele der nächsten ${horizonDays} Tage — erledigen heißt immer, die Ursache zu bearbeiten`}
                />

                {overdue.length > 0 && (
                    <section className="grid max-w-3xl gap-2">
                        <Heading variant="small" title={`Überfällig (${overdue.length})`} description="" />
                        {overdue.map((deadline, index) => (
                            <Row key={`o-${index}`} deadline={deadline} />
                        ))}
                    </section>
                )}

                <section className="grid max-w-3xl gap-2">
                    <Heading variant="small" title={`Demnächst (${upcoming.length})`} description="" />
                    {upcoming.map((deadline, index) => (
                        <Row key={`u-${index}`} deadline={deadline} />
                    ))}
                    {upcoming.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Nichts anstehend. 🎉
                        </p>
                    )}
                </section>
            </div>
        </>
    );
}

function Row({ deadline }: { deadline: DeadlineRow }) {
    return (
        <Link
            href={deadline.url}
            className={`flex items-center gap-3 rounded-lg border p-3 text-sm hover:bg-accent/50 ${
                deadline.overdue
                    ? 'border-red-300/60 bg-red-50/50 dark:border-red-900 dark:bg-red-950/30'
                    : 'border-sidebar-border/70 dark:border-sidebar-border'
            }`}
        >
            <Badge variant={deadline.overdue ? 'destructive' : 'outline'}>
                {formatDate(deadline.due_on)}
            </Badge>
            <span className="font-medium">{deadline.title}</span>
            <span className="text-muted-foreground">{deadline.subtitle}</span>
            <span className="flex-1" />
            <span className="text-xs text-muted-foreground">
                {deadline.kind_label}
            </span>
        </Link>
    );
}

DeadlinesIndex.layout = {
    breadcrumbs: [{ title: 'Fristen', href: '/deadlines' }],
};

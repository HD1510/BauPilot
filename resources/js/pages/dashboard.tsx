import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, CalendarClock, FolderKanban } from 'lucide-react';
import { DeadlineListRow } from '@/components/deadlines/deadline-list-row';
import type { DeadlineRow } from '@/components/deadlines/deadline-list-row';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate, formatEUR } from '@/lib/format';
import { dashboard } from '@/routes';

type OverdueInvoice = {
    id: number;
    number: string;
    customer: string | null;
    due_on: string;
    due_now: number;
    days_overdue: number;
};

type Props = {
    hasCompany: boolean;
    canViewFinancials?: boolean;
    activeProjects?: number;
    deadlines?: DeadlineRow[];
    appointments?: DeadlineRow[];
    overdueDeadlines?: number;
    openItems?: { due_now: number; retained_open: number; count: number };
    overdueInvoices?: OverdueInvoice[];
    numberGaps?: { fiscal_year_start: string; checked: number; gaps: number[] };
    uncheckedIncoming?: number;
    openIncoming?: number;
};

export default function Dashboard({
    hasCompany,
    canViewFinancials = false,
    activeProjects = 0,
    deadlines = [],
    appointments = [],
    overdueDeadlines = 0,
    openItems,
    overdueInvoices = [],
    numberGaps,
    uncheckedIncoming = 0,
    openIncoming = 0,
}: Props) {
    if (!hasCompany) {
        return (
            <>
                <Head title="Dashboard" />
                <div className="flex h-full flex-1 flex-col items-center justify-center gap-4 p-4">
                    <p className="text-muted-foreground">
                        Sie sind noch keiner Firma zugeordnet.
                    </p>
                    <Button asChild>
                        <Link href="/companies/create">Firma anlegen</Link>
                    </Button>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="grid gap-3 md:grid-cols-4">
                    {canViewFinancials && openItems && (
                        <>
                            <Tile
                                label={`Offene Posten (${openItems.count})`}
                                value={formatEUR(openItems.due_now)}
                                href="/outgoing-invoices"
                            />
                            <Tile
                                label="Einbehalten (Rücklässe)"
                                value={formatEUR(openItems.retained_open)}
                                href="/outgoing-invoices"
                            />
                        </>
                    )}
                    <Tile
                        label="Laufende Projekte"
                        value={String(activeProjects)}
                        href="/projects"
                        icon={<FolderKanban className="size-4" />}
                    />
                    <Tile
                        label={`Fristen (${overdueDeadlines} überfällig)`}
                        value={String(deadlines.length)}
                        href="/deadlines"
                        icon={<CalendarClock className="size-4" />}
                    />
                </div>

                {canViewFinancials && overdueInvoices.length > 0 && (
                    <section className="grid max-w-3xl gap-2">
                        <Heading
                            variant="small"
                            title="Mahn-Hinweise"
                            description="Überfällige Ausgangsrechnungen mit fälligem Betrag"
                        />
                        {overdueInvoices.map((invoice) => (
                            <Link
                                key={invoice.id}
                                href={`/outgoing-invoices/${invoice.id}`}
                                className="flex items-center gap-3 rounded-lg border border-red-300/60 bg-red-50/50 p-3 text-sm hover:bg-red-50 dark:border-red-900 dark:bg-red-950/30"
                            >
                                <AlertTriangle className="size-4 text-red-600" />
                                <span className="font-medium">
                                    Rechnung {invoice.number}
                                </span>
                                <span className="text-muted-foreground">
                                    {invoice.customer}
                                </span>
                                <span className="flex-1" />
                                <span>{formatEUR(invoice.due_now)}</span>
                                <Badge variant="destructive">
                                    {invoice.days_overdue} Tage überfällig
                                </Badge>
                            </Link>
                        ))}
                    </section>
                )}

                {canViewFinancials &&
                    numberGaps &&
                    numberGaps.gaps.length > 0 && (
                        <section className="max-w-3xl rounded-lg border border-amber-500/50 bg-amber-50 p-4 text-sm dark:bg-amber-950/30">
                            <div className="flex items-center gap-2 font-medium">
                                <AlertTriangle className="size-4" />
                                Lücken im Rechnungsnummernkreis (GJ ab{' '}
                                {formatDate(numberGaps.fiscal_year_start)})
                            </div>
                            <p className="mt-1 text-muted-foreground">
                                Fehlende Nummern: {numberGaps.gaps.join(', ')} —
                                geprüft wurden {numberGaps.checked} Belege.
                            </p>
                        </section>
                    )}

                <section className="grid max-w-3xl gap-2">
                    <Heading
                        variant="small"
                        title="Nächste Termine"
                        description="Projekttermine der nächsten 8 Wochen"
                    />
                    {appointments.map((appointment, index) => (
                        <DeadlineListRow
                            key={`${appointment.kind}-${index}`}
                            row={appointment}
                        />
                    ))}
                    {appointments.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Keine Termine in den nächsten 8 Wochen.
                        </p>
                    )}
                </section>

                <section className="grid max-w-3xl gap-2">
                    <Heading
                        variant="small"
                        title="Nächste Fristen"
                        description="Berechnet aus den Belegen — nie gespeichert"
                    />
                    {deadlines.map((deadline, index) => (
                        <DeadlineListRow
                            key={`${deadline.kind}-${index}`}
                            row={deadline}
                        />
                    ))}
                    {deadlines.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Keine Fristen in den nächsten 8 Wochen. 🎉
                        </p>
                    )}
                </section>

                {canViewFinancials &&
                    (uncheckedIncoming > 0 || openIncoming > 0) && (
                        <p className="text-sm text-muted-foreground">
                            Eingangsrechnungen:{' '}
                            <Link
                                href="/incoming-invoices?open=1"
                                className="underline underline-offset-2"
                            >
                                {openIncoming} unbezahlt
                            </Link>
                            {' · '}
                            <Link
                                href="/incoming-invoices?unchecked=1"
                                className="underline underline-offset-2"
                            >
                                {uncheckedIncoming} ungeprüft
                            </Link>
                        </p>
                    )}
            </div>
        </>
    );
}

function Tile({
    label,
    value,
    href,
    icon,
}: {
    label: string;
    value: string;
    href: string;
    icon?: React.ReactNode;
}) {
    return (
        <Link
            href={href}
            className="rounded-xl border border-sidebar-border/70 p-4 hover:bg-accent/50 dark:border-sidebar-border"
        >
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                {icon}
                {label}
            </div>
            <div className="mt-1 text-2xl font-semibold">{value}</div>
        </Link>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};

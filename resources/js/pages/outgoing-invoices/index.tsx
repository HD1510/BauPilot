import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { formatDate, formatEUR } from '@/lib/format';

type Row = {
    id: number;
    number: string;
    doc_type_label: string;
    invoice_date: string;
    due_on: string;
    customer: string | null;
    project: string | null;
    gross_effective: number;
    paid: number;
    retained_open: number;
    due_now: number;
    status: string;
    status_label: string;
};

export default function OutgoingInvoicesIndex({
    rows,
    totals,
    filters,
}: {
    rows: Row[];
    totals: { due_now: number; retained_open: number; count: number };
    filters: { all: boolean };
}) {
    return (
        <>
            <Head title="Ausgangsrechnungen" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Ausgangsrechnungen"
                        description={`Offene Posten: ${formatEUR(totals.due_now)} jetzt fällig · ${formatEUR(totals.retained_open)} einbehalten`}
                    />
                    <Button asChild>
                        <Link href="/outgoing-invoices/create">
                            <Plus className="size-4" />
                            Neue Rechnung
                        </Link>
                    </Button>
                </div>

                <Label className="flex w-fit items-center gap-2 text-sm font-normal">
                    <Checkbox
                        checked={filters.all}
                        onCheckedChange={(checked) =>
                            router.get(
                                '/outgoing-invoices',
                                checked ? { all: 1 } : {},
                                { preserveState: true, replace: true },
                            )
                        }
                    />
                    Auch bezahlte und stornierte anzeigen
                </Label>

                <div className="grid gap-2">
                    {rows.map((row) => (
                        <Link
                            key={row.id}
                            href={`/outgoing-invoices/${row.id}`}
                            className="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border border-sidebar-border/70 p-3 text-sm hover:bg-accent/50 dark:border-sidebar-border"
                        >
                            <span className="w-20 font-medium">{row.number}</span>
                            <Badge variant="outline">{row.doc_type_label}</Badge>
                            <span className="min-w-40 flex-1">
                                {row.customer}
                                {row.project && (
                                    <span className="text-muted-foreground"> · {row.project}</span>
                                )}
                            </span>
                            <span className="text-muted-foreground">
                                fällig am {formatDate(row.due_on)}
                            </span>
                            <span className="w-28 text-right">{formatEUR(row.gross_effective)}</span>
                            <span className="w-28 text-right font-medium">{formatEUR(row.due_now)}</span>
                            <Badge variant="secondary">{row.status_label}</Badge>
                        </Link>
                    ))}
                    {rows.length === 0 && (
                        <p className="py-8 text-center text-muted-foreground">
                            Keine offenen Posten. 🎉
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

OutgoingInvoicesIndex.layout = {
    breadcrumbs: [{ title: 'Ausgangsrechnungen', href: '/outgoing-invoices' }],
};

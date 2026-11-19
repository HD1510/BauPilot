import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Pager, SearchInput, SortSelect } from '@/components/list-controls';
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

type Filters = { all: boolean; q: string; sort: string };

const SORT_OPTIONS = [
    { value: 'due_asc', label: 'Fälligkeit (älteste zuerst)' },
    { value: 'due_desc', label: 'Fälligkeit (späteste zuerst)' },
    { value: 'date_desc', label: 'Neueste Rechnung zuerst' },
    { value: 'amount_desc', label: 'Höchster offener Betrag' },
    { value: 'customer_asc', label: 'Kunde A–Z' },
];

export default function OutgoingInvoicesIndex({
    rows,
    totals,
    summary,
    pagination,
    filters,
}: {
    rows: Row[];
    totals: { due_now: number; retained_open: number; count: number };
    summary: { count: number; due_now_sum: number; gross_sum: number };
    pagination: { page: number; last_page: number; total: number };
    filters: Filters;
}) {
    // Jede Änderung an Suche, Filter oder Sortierung beginnt auf Seite 1.
    const apply = (partial: Record<string, unknown>) =>
        router.get(
            '/outgoing-invoices',
            {
                q: filters.q || undefined,
                all: filters.all ? 1 : undefined,
                sort: filters.sort === 'due_asc' ? undefined : filters.sort,
                ...partial,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );

    return (
        <>
            <Head title="Ausgangsrechnungen" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
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

                <div className="flex flex-wrap items-center gap-x-4 gap-y-3">
                    <SearchInput
                        value={filters.q}
                        placeholder="Nummer, Kunde, Projekt …"
                        onSearch={(q) => apply({ q: q || undefined })}
                    />
                    <SortSelect
                        value={filters.sort}
                        options={SORT_OPTIONS}
                        onChange={(sort) =>
                            apply({
                                sort: sort === 'due_asc' ? undefined : sort,
                            })
                        }
                    />
                    <Label className="flex w-fit items-center gap-2 text-sm font-normal">
                        <Checkbox
                            checked={filters.all}
                            onCheckedChange={(checked) =>
                                apply({ all: checked ? 1 : undefined })
                            }
                        />
                        Auch bezahlte und stornierte anzeigen
                    </Label>
                </div>

                <p className="text-sm text-muted-foreground">
                    {summary.count}{' '}
                    {summary.count === 1 ? 'Rechnung' : 'Rechnungen'}
                    {filters.q !== '' && ' gefunden'} · Summe brutto{' '}
                    <span className="font-medium text-foreground">
                        {formatEUR(summary.gross_sum)}
                    </span>{' '}
                    · davon jetzt fällig{' '}
                    <span className="font-medium text-foreground">
                        {formatEUR(summary.due_now_sum)}
                    </span>
                </p>

                <div className="grid gap-2">
                    {rows.map((row) => (
                        <Link
                            key={row.id}
                            href={`/outgoing-invoices/${row.id}`}
                            className="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border border-sidebar-border/70 p-3 text-sm hover:bg-accent/50 dark:border-sidebar-border"
                        >
                            <span className="w-20 shrink-0 text-muted-foreground tabular-nums">
                                {formatDate(row.invoice_date)}
                            </span>
                            <span className="w-20 font-medium">
                                {row.number}
                            </span>
                            <Badge variant="outline">
                                {row.doc_type_label}
                            </Badge>
                            <span className="min-w-40 flex-1">
                                {row.customer}
                                {row.project && (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {row.project}
                                    </span>
                                )}
                            </span>
                            <span className="text-muted-foreground">
                                fällig am {formatDate(row.due_on)}
                            </span>
                            <span className="w-28 text-right tabular-nums">
                                {formatEUR(row.gross_effective)}
                            </span>
                            <span className="w-28 text-right font-medium tabular-nums">
                                {formatEUR(row.due_now)}
                            </span>
                            <Badge variant="secondary">
                                {row.status_label}
                            </Badge>
                        </Link>
                    ))}
                    {rows.length === 0 && (
                        <p className="py-8 text-center text-muted-foreground">
                            {filters.q !== ''
                                ? 'Keine Rechnungen gefunden.'
                                : 'Keine offenen Posten. 🎉'}
                        </p>
                    )}
                </div>

                <Pager
                    page={pagination.page}
                    lastPage={pagination.last_page}
                    onPage={(page) => apply({ page })}
                />
            </div>
        </>
    );
}

OutgoingInvoicesIndex.layout = {
    breadcrumbs: [{ title: 'Ausgangsrechnungen', href: '/outgoing-invoices' }],
};

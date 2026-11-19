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
    supplier: string | null;
    supplier_invoice_no: string | null;
    invoice_date: string;
    gross: string;
    reverse_charge: boolean;
    cost_type: string | null;
    project: string | null;
    payment_due_on: string | null;
    skonto_until: string | null;
    payment_status: string;
    payment_status_label: string;
    checked: boolean;
};

type Filters = { q: string; open: boolean; unchecked: boolean; sort: string };

const SORT_OPTIONS = [
    { value: 'date_desc', label: 'Neueste zuerst' },
    { value: 'date_asc', label: 'Älteste zuerst' },
    { value: 'due_asc', label: 'Fälligkeit' },
    { value: 'amount_desc', label: 'Höchster Betrag' },
    { value: 'amount_asc', label: 'Niedrigster Betrag' },
    { value: 'supplier_asc', label: 'Lieferant A–Z' },
];

export default function IncomingInvoicesIndex({
    invoices,
    summary,
    pagination,
    filters,
}: {
    invoices: Row[];
    summary: { count: number; gross_sum: string };
    pagination: { page: number; last_page: number; total: number };
    filters: Filters;
}) {
    // Jede Änderung an Suche, Filter oder Sortierung beginnt auf Seite 1.
    const apply = (partial: Record<string, unknown>) =>
        router.get(
            '/incoming-invoices',
            {
                q: filters.q || undefined,
                open: filters.open ? 1 : undefined,
                unchecked: filters.unchecked ? 1 : undefined,
                sort: filters.sort === 'date_desc' ? undefined : filters.sort,
                ...partial,
            },
            { preserveState: true, replace: true, preserveScroll: true },
        );

    const filtered = filters.q !== '' || filters.open || filters.unchecked;

    return (
        <>
            <Head title="Eingangsrechnungen" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Heading
                        title="Eingangsrechnungen"
                        description="Rechnungen von Lieferanten und Subunternehmern — mit Skonto und „geprüft“-Häkchen"
                    />
                    <Button asChild>
                        <Link href="/incoming-invoices/create">
                            <Plus className="size-4" />
                            Neue Eingangsrechnung
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-x-4 gap-y-3">
                    <SearchInput
                        value={filters.q}
                        placeholder="Lieferant, Nummer, Projekt …"
                        onSearch={(q) => apply({ q: q || undefined })}
                    />
                    <SortSelect
                        value={filters.sort}
                        options={SORT_OPTIONS}
                        onChange={(sort) =>
                            apply({
                                sort: sort === 'date_desc' ? undefined : sort,
                            })
                        }
                    />
                    <Label className="flex items-center gap-2 text-sm font-normal">
                        <Checkbox
                            checked={filters.open}
                            onCheckedChange={(checked) =>
                                apply({ open: checked ? 1 : undefined })
                            }
                        />
                        Nur unbezahlte
                    </Label>
                    <Label className="flex items-center gap-2 text-sm font-normal">
                        <Checkbox
                            checked={filters.unchecked}
                            onCheckedChange={(checked) =>
                                apply({ unchecked: checked ? 1 : undefined })
                            }
                        />
                        Nur ungeprüfte
                    </Label>
                </div>

                <p className="text-sm text-muted-foreground">
                    {summary.count}{' '}
                    {summary.count === 1 ? 'Rechnung' : 'Rechnungen'}
                    {filtered && ' gefunden'} · Summe brutto{' '}
                    <span className="font-medium text-foreground">
                        {formatEUR(summary.gross_sum)}
                    </span>
                </p>

                <div className="grid gap-2">
                    {invoices.map((invoice) => (
                        <Link
                            key={invoice.id}
                            href={`/incoming-invoices/${invoice.id}/edit`}
                            className="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border border-sidebar-border/70 p-3 text-sm hover:bg-accent/50 dark:border-sidebar-border"
                        >
                            <span className="w-20 shrink-0 text-muted-foreground tabular-nums">
                                {formatDate(invoice.invoice_date)}
                            </span>
                            <span className="min-w-40 flex-1">
                                <span className="font-medium">
                                    {invoice.supplier}
                                </span>
                                <span className="text-muted-foreground">
                                    {invoice.supplier_invoice_no &&
                                        ` · ${invoice.supplier_invoice_no}`}
                                    {invoice.project && ` · ${invoice.project}`}
                                </span>
                            </span>
                            {invoice.reverse_charge && (
                                <Badge variant="outline">§19</Badge>
                            )}
                            <span className="text-muted-foreground">
                                {invoice.cost_type}
                            </span>
                            <span className="text-muted-foreground">
                                {invoice.payment_due_on &&
                                    `zahlbar bis ${formatDate(invoice.payment_due_on)}`}
                                {invoice.skonto_until &&
                                    ` · Skonto bis ${formatDate(invoice.skonto_until)}`}
                            </span>
                            <span className="w-28 text-right font-medium tabular-nums">
                                {formatEUR(invoice.gross)}
                            </span>
                            <Badge variant="secondary">
                                {invoice.payment_status_label}
                            </Badge>
                            {!invoice.checked && (
                                <Badge variant="outline">ungeprüft</Badge>
                            )}
                        </Link>
                    ))}
                    {invoices.length === 0 && (
                        <p className="py-8 text-center text-muted-foreground">
                            Keine Eingangsrechnungen gefunden.
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

IncomingInvoicesIndex.layout = {
    breadcrumbs: [{ title: 'Eingangsrechnungen', href: '/incoming-invoices' }],
};

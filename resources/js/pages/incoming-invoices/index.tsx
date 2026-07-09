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

export default function IncomingInvoicesIndex({
    invoices,
    filters,
}: {
    invoices: Row[];
    filters: { q: string; open: boolean; unchecked: boolean };
}) {
    const applyFilter = (partial: Record<string, unknown>) =>
        router.get(
            '/incoming-invoices',
            {
                open: filters.open ? 1 : undefined,
                unchecked: filters.unchecked ? 1 : undefined,
                ...partial,
            },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Eingangsrechnungen" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Eingangsrechnungen"
                        description="Lieferanten- und Subunternehmerrechnungen mit Skonto und Prüfvermerk"
                    />
                    <Button asChild>
                        <Link href="/incoming-invoices/create">
                            <Plus className="size-4" />
                            Neue Eingangsrechnung
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-4">
                    <Label className="flex items-center gap-2 text-sm font-normal">
                        <Checkbox
                            checked={filters.open}
                            onCheckedChange={(checked) =>
                                applyFilter({ open: checked ? 1 : undefined })
                            }
                        />
                        Nur unbezahlte
                    </Label>
                    <Label className="flex items-center gap-2 text-sm font-normal">
                        <Checkbox
                            checked={filters.unchecked}
                            onCheckedChange={(checked) =>
                                applyFilter({ unchecked: checked ? 1 : undefined })
                            }
                        />
                        Nur ungeprüfte
                    </Label>
                </div>

                <div className="grid gap-2">
                    {invoices.map((invoice) => (
                        <Link
                            key={invoice.id}
                            href={`/incoming-invoices/${invoice.id}/edit`}
                            className="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border border-sidebar-border/70 p-3 text-sm hover:bg-accent/50 dark:border-sidebar-border"
                        >
                            <span className="min-w-40 flex-1">
                                <span className="font-medium">{invoice.supplier}</span>
                                <span className="text-muted-foreground">
                                    {invoice.supplier_invoice_no && ` · ${invoice.supplier_invoice_no}`}
                                    {invoice.project && ` · ${invoice.project}`}
                                </span>
                            </span>
                            {invoice.reverse_charge && <Badge variant="outline">§19</Badge>}
                            <span className="text-muted-foreground">{invoice.cost_type}</span>
                            <span className="text-muted-foreground">
                                {invoice.payment_due_on && `zahlbar bis ${formatDate(invoice.payment_due_on)}`}
                                {invoice.skonto_until && ` · Skonto bis ${formatDate(invoice.skonto_until)}`}
                            </span>
                            <span className="w-28 text-right font-medium">{formatEUR(invoice.gross)}</span>
                            <Badge variant="secondary">{invoice.payment_status_label}</Badge>
                            {!invoice.checked && <Badge variant="outline">ungeprüft</Badge>}
                        </Link>
                    ))}
                    {invoices.length === 0 && (
                        <p className="py-8 text-center text-muted-foreground">
                            Keine Eingangsrechnungen gefunden.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

IncomingInvoicesIndex.layout = {
    breadcrumbs: [{ title: 'Eingangsrechnungen', href: '/incoming-invoices' }],
};

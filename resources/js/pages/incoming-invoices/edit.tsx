import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, CircleDashed } from 'lucide-react';
import { DocumentsSection  } from '@/components/documents-section';
import type {DocumentItem} from '@/components/documents-section';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    IncomingInvoiceForm
    
    
    
    
} from '@/components/invoicing/incoming-invoice-form';
import type {IncomingInvoiceFormValues, Option, ProjectOption, SupplierOption} from '@/components/invoicing/incoming-invoice-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { formatDate, formatEUR } from '@/lib/format';

type Props = {
    invoice: IncomingInvoiceFormValues & {
        id: number;
        gross: string;
        payment_status: string;
        payment_status_label: string;
        paid_on: string | null;
        paid_amount: string | null;
        checked: boolean;
    };
    suppliers: SupplierOption[];
    costTypes: Option[];
    projects: ProjectOption[];
    documents: DocumentItem[];
    canWrite: boolean;
};

export default function IncomingInvoicesEdit({
    invoice,
    suppliers,
    costTypes,
    projects,
    documents,
    canWrite,
}: Props) {
    return (
        <>
            <Head title={`Eingangsrechnung ${invoice.supplier_invoice_no ?? invoice.id}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={`Eingangsrechnung ${invoice.supplier_invoice_no ?? `#${invoice.id}`}`}
                        description={`Brutto ${formatEUR(invoice.gross)}${invoice.paid_on ? ` · bezahlt am ${formatDate(invoice.paid_on)} (${formatEUR(invoice.paid_amount)})` : ''}`}
                    />
                    <div className="flex items-center gap-2">
                        <Badge variant="secondary">{invoice.payment_status_label}</Badge>
                        {canWrite && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.patch(`/incoming-invoices/${invoice.id}/checked`, {}, { preserveScroll: true })
                                }
                            >
                                {invoice.checked ? (
                                    <>
                                        <CheckCircle2 className="size-4 text-green-600" />
                                        Geprüft
                                    </>
                                ) : (
                                    <>
                                        <CircleDashed className="size-4" />
                                        Als geprüft markieren
                                    </>
                                )}
                            </Button>
                        )}
                    </div>
                </div>

                {canWrite && invoice.payment_status !== 'paid' && (
                    <PayForm invoiceId={invoice.id} gross={invoice.gross} skontoAmount={invoice.skonto_amount} />
                )}

                <Separator />

                <IncomingInvoiceForm
                    action={`/incoming-invoices/${invoice.id}`}
                    method="patch"
                    invoice={invoice}
                    suppliers={suppliers}
                    costTypes={costTypes}
                    projects={projects}
                    submitLabel="Änderungen speichern"
                    disabled={!canWrite}
                />

                <Separator />

                <DocumentsSection
                    documentableType="incoming_invoice"
                    documentableId={invoice.id}
                    documents={documents}
                    canWrite={canWrite}
                    defaultCategory="invoice"
                />
            </div>
        </>
    );
}

function PayForm({
    invoiceId,
    gross,
    skontoAmount,
}: {
    invoiceId: number;
    gross: string;
    skontoAmount: string | null;
}) {
    const suggested = skontoAmount
        ? (Number(gross) - Number(skontoAmount)).toFixed(2)
        : gross;

    const { data, setData, post, processing, errors } = useForm({
        paid_on: new Date().toISOString().slice(0, 10),
        paid_amount: suggested,
    });

    return (
        <form
            className="flex max-w-xl items-end gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/incoming-invoices/${invoiceId}/pay`, { preserveScroll: true });
            }}
        >
            <div className="grid gap-2">
                <Label htmlFor="pay-date">Zahldatum</Label>
                <Input id="pay-date" type="date" value={data.paid_on} onChange={(e) => setData('paid_on', e.target.value)} required />
            </div>
            <div className="grid flex-1 gap-2">
                <Label htmlFor="pay-amount">Gezahlter Betrag (€{skontoAmount ? ', Vorschlag mit Skonto' : ''})</Label>
                <Input id="pay-amount" type="number" step="0.01" min="0.01" value={data.paid_amount} onChange={(e) => setData('paid_amount', e.target.value)} required />
                <InputError message={errors.paid_amount ?? errors.paid_on} />
            </div>
            <Button type="submit" disabled={processing}>Zahlung buchen</Button>
        </form>
    );
}

IncomingInvoicesEdit.layout = ({ invoice }: Props) => ({
    breadcrumbs: [
        { title: 'Eingangsrechnungen', href: '/incoming-invoices' },
        { title: invoice.supplier_invoice_no ?? `#${invoice.id}`, href: `/incoming-invoices/${invoice.id}/edit` },
    ],
});

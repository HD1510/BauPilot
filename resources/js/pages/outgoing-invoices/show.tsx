import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { DocumentsSection } from '@/components/documents-section';
import type { DocumentItem } from '@/components/documents-section';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { formatDate, formatEUR } from '@/lib/format';

type Props = {
    invoice: {
        id: number;
        number: string;
        doc_type: string;
        doc_type_label: string;
        invoice_date: string;
        due_on: string;
        customer: string | null;
        project: { id: number; title: string } | null;
        net: string;
        vat_rate: string;
        vat: string;
        gross: string;
        zero_rate_reason_label: string | null;
        status: string;
        status_label: string;
        notes: string | null;
    };
    summary: {
        gross_effective: number;
        paid: number;
        retained_open: number;
        due_now: number;
    };
    adjustments: {
        id: number;
        number: string;
        doc_type_label: string;
        invoice_date: string;
        gross: string;
    }[];
    partials: {
        id: number;
        number: string;
        gross: string;
        status_label: string;
    }[];
    payments: {
        id: number;
        paid_on: string;
        amount: string;
        retention_kind: string | null;
    }[];
    retentions: {
        id: number;
        kind: string;
        kind_label: string;
        percent: string | null;
        amount: string;
        due_on: string;
        received_at: string | null;
        note: string | null;
    }[];
    documents: DocumentItem[];
    canWrite: boolean;
};

export default function OutgoingInvoicesShow({
    invoice,
    summary,
    adjustments,
    partials,
    payments,
    retentions,
    documents,
    canWrite,
}: Props) {
    const errors = usePage().props.errors as Record<string, string | undefined>;

    return (
        <>
            <Head title={`Rechnung ${invoice.number}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={`${invoice.doc_type_label} ${invoice.number}`}
                        description={[
                            invoice.customer,
                            invoice.project?.title,
                            `vom ${formatDate(invoice.invoice_date)}`,
                            `fällig am ${formatDate(invoice.due_on)}`,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    />
                    <div className="flex items-center gap-2">
                        <Badge variant="secondary">
                            {invoice.status_label}
                        </Badge>
                        {canWrite && (
                            <Button
                                variant="outline"
                                className="text-red-600 hover:text-red-700"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Diese Rechnung endgültig löschen? Die Rechnungsnummer fehlt dann im Nummernkreis (Lückenprüfung meldet das). Mit gebuchten Zahlungen ist stattdessen der Storno der richtige Weg.',
                                        )
                                    ) {
                                        router.delete(
                                            `/outgoing-invoices/${invoice.id}`,
                                        );
                                    }
                                }}
                            >
                                <Trash2 className="size-4" />
                                Löschen
                            </Button>
                        )}
                    </div>
                </div>

                {errors.delete && (
                    <p className="max-w-3xl rounded-lg border border-red-300/60 bg-red-50/50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-400">
                        {errors.delete}
                    </p>
                )}

                <div className="grid max-w-3xl grid-cols-2 gap-3 md:grid-cols-4">
                    <Stat
                        label="Brutto (Thread)"
                        value={formatEUR(summary.gross_effective)}
                    />
                    <Stat label="Bezahlt" value={formatEUR(summary.paid)} />
                    <Stat
                        label="Einbehalten"
                        value={formatEUR(summary.retained_open)}
                    />
                    <Stat
                        label="Jetzt fällig"
                        value={formatEUR(summary.due_now)}
                        highlight
                    />
                </div>

                <div className="max-w-3xl text-sm text-muted-foreground">
                    Netto {formatEUR(invoice.net)} · USt {invoice.vat_rate} % ={' '}
                    {formatEUR(invoice.vat)} · Brutto {formatEUR(invoice.gross)}
                    {invoice.zero_rate_reason_label &&
                        ` · ${invoice.zero_rate_reason_label}`}
                </div>

                {partials.length > 0 && (
                    <div className="max-w-3xl">
                        <Heading
                            variant="small"
                            title="Zugeordnete Teilrechnungen"
                            description="Diese Schlussrechnung trägt nur den Restbetrag"
                        />
                        <div className="mt-2 grid gap-2">
                            {partials.map((partial) => (
                                <Link
                                    key={partial.id}
                                    href={`/outgoing-invoices/${partial.id}`}
                                    className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-2 text-sm hover:bg-accent/50 dark:border-sidebar-border"
                                >
                                    <span className="font-medium">
                                        {partial.number}
                                    </span>
                                    <span className="flex-1" />
                                    <span>{formatEUR(partial.gross)}</span>
                                    <Badge variant="secondary">
                                        {partial.status_label}
                                    </Badge>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

                {adjustments.length > 0 && (
                    <div className="max-w-3xl">
                        <Heading
                            variant="small"
                            title="Gutschriften und Storni"
                            description="Negativ in diese Rechnung eingerechnet"
                        />
                        <div className="mt-2 grid gap-2">
                            {adjustments.map((adjustment) => (
                                <div
                                    key={adjustment.id}
                                    className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-2 text-sm dark:border-sidebar-border"
                                >
                                    <Badge variant="outline">
                                        {adjustment.doc_type_label}
                                    </Badge>
                                    <span className="font-medium">
                                        {adjustment.number}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {formatDate(adjustment.invoice_date)}
                                    </span>
                                    <span className="flex-1" />
                                    <span>{formatEUR(adjustment.gross)}</span>
                                    {canWrite && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`${adjustment.doc_type_label} ${adjustment.number} löschen`}
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        `${adjustment.doc_type_label} ${adjustment.number} endgültig löschen?`,
                                                    )
                                                ) {
                                                    router.delete(
                                                        `/outgoing-invoices/${adjustment.id}`,
                                                    );
                                                }
                                            }}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <Separator />

                <Heading
                    variant="small"
                    title="Zahlungen"
                    description="Der Zahlstatus wird bei jeder Buchung neu abgeleitet"
                />
                <div className="grid max-w-xl gap-2">
                    {payments.map((payment) => (
                        <div
                            key={payment.id}
                            className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-2 text-sm dark:border-sidebar-border"
                        >
                            <span>{formatDate(payment.paid_on)}</span>
                            {payment.retention_kind && (
                                <Badge variant="outline">
                                    Freigabe {payment.retention_kind}
                                </Badge>
                            )}
                            <span className="flex-1" />
                            <span className="font-medium">
                                {formatEUR(payment.amount)}
                            </span>
                        </div>
                    ))}
                    {payments.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Noch keine Zahlungen.
                        </p>
                    )}
                </div>
                {canWrite && invoice.status !== 'cancelled' && (
                    <AddPaymentForm invoiceId={invoice.id} />
                )}

                <Separator />

                <Heading
                    variant="small"
                    title="Einbehalte"
                    description="Haft- und Deckungsrücklässe mindern den jetzt fälligen Betrag bis zu ihrer Fälligkeit"
                />
                <div className="grid max-w-xl gap-2">
                    {retentions.map((retention) => (
                        <div
                            key={retention.id}
                            className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-2 text-sm dark:border-sidebar-border"
                        >
                            <Badge variant="outline">
                                {retention.kind_label}
                            </Badge>
                            <span className="text-muted-foreground">
                                fällig {formatDate(retention.due_on)}
                            </span>
                            <span className="flex-1" />
                            <span className="font-medium">
                                {formatEUR(retention.amount)}
                            </span>
                            {retention.received_at ? (
                                <Badge variant="secondary">eingegangen</Badge>
                            ) : (
                                canWrite && (
                                    <ReleaseRetentionButton
                                        invoiceId={invoice.id}
                                        retentionId={retention.id}
                                    />
                                )
                            )}
                        </div>
                    ))}
                    {retentions.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Keine Einbehalte.
                        </p>
                    )}
                </div>
                {canWrite && invoice.status !== 'cancelled' && (
                    <AddRetentionForm invoiceId={invoice.id} />
                )}

                {canWrite &&
                    invoice.doc_type !== 'credit_note' &&
                    invoice.doc_type !== 'cancellation' && (
                        <>
                            <Separator />
                            <AddAdjustmentForm invoiceId={invoice.id} />
                        </>
                    )}

                <Separator />

                <DocumentsSection
                    documentableType="outgoing_invoice"
                    documentableId={invoice.id}
                    documents={documents}
                    canWrite={canWrite}
                    defaultCategory="invoice"
                />
            </div>
        </>
    );
}

function Stat({
    label,
    value,
    highlight = false,
}: {
    label: string;
    value: string;
    highlight?: boolean;
}) {
    return (
        <div
            className={`rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border ${highlight ? 'bg-accent/40' : ''}`}
        >
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-lg font-semibold">{value}</div>
        </div>
    );
}

function AddPaymentForm({ invoiceId }: { invoiceId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        paid_on: new Date().toISOString().slice(0, 10),
        amount: '',
    });

    return (
        <form
            className="flex max-w-xl items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/outgoing-invoices/${invoiceId}/payments`, {
                    preserveScroll: true,
                    onSuccess: () => reset('amount'),
                });
            }}
        >
            <div className="grid gap-2">
                <Label htmlFor="payment-date">Zahldatum</Label>
                <Input
                    id="payment-date"
                    type="date"
                    value={data.paid_on}
                    onChange={(e) => setData('paid_on', e.target.value)}
                    required
                />
            </div>
            <div className="grid flex-1 gap-2">
                <Label htmlFor="payment-amount">Zahlungseingang (€)</Label>
                <Input
                    id="payment-amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
                    required
                />
                <InputError message={errors.amount ?? errors.paid_on} />
            </div>
            <Button type="submit" disabled={processing}>
                Zahlung buchen
            </Button>
        </form>
    );
}

function AddRetentionForm({ invoiceId }: { invoiceId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        kind: 'warranty',
        amount: '',
        percent: '',
        due_on: '',
        note: '',
    });

    return (
        <form
            className="flex max-w-xl flex-wrap items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/outgoing-invoices/${invoiceId}/retentions`, {
                    preserveScroll: true,
                    onSuccess: () => reset('amount', 'percent'),
                });
            }}
        >
            <div className="grid w-44 gap-2">
                <Label>Art</Label>
                <Select
                    value={data.kind}
                    onValueChange={(value) => setData('kind', value)}
                >
                    <SelectTrigger aria-label="Art des Einbehalts">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="warranty">Haftrücklass</SelectItem>
                        <SelectItem value="coverage">
                            Deckungsrücklass
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div className="grid w-32 gap-2">
                <Label htmlFor="retention-amount">Betrag (€)</Label>
                <Input
                    id="retention-amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
                    required
                />
                <InputError message={errors.amount ?? errors.due_on} />
            </div>
            <div className="grid w-24 gap-2">
                <Label htmlFor="retention-percent">%</Label>
                <Input
                    id="retention-percent"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    value={data.percent}
                    onChange={(e) => setData('percent', e.target.value)}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="retention-due">Fällig am</Label>
                <Input
                    id="retention-due"
                    type="date"
                    value={data.due_on}
                    onChange={(e) => setData('due_on', e.target.value)}
                    required
                />
            </div>
            <Button type="submit" disabled={processing}>
                Einbehalt erfassen
            </Button>
        </form>
    );
}

function ReleaseRetentionButton({
    invoiceId,
    retentionId,
}: {
    invoiceId: number;
    retentionId: number;
}) {
    const { post, processing, setData, data } = useForm({
        paid_on: new Date().toISOString().slice(0, 10),
    });

    return (
        <div className="flex items-center gap-2">
            <Input
                type="date"
                className="h-8 w-36"
                value={data.paid_on}
                onChange={(e) => setData('paid_on', e.target.value)}
                aria-label="Eingangsdatum"
            />
            <Button
                size="sm"
                variant="outline"
                disabled={processing}
                onClick={() =>
                    post(
                        `/outgoing-invoices/${invoiceId}/retentions/${retentionId}/release`,
                        { preserveScroll: true },
                    )
                }
            >
                Eingegangen
            </Button>
        </div>
    );
}

function AddAdjustmentForm({ invoiceId }: { invoiceId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        doc_type: 'credit_note',
        number: '',
        invoice_date: new Date().toISOString().slice(0, 10),
        amount_mode: 'gross',
        amount: '',
        notes: '',
    });

    return (
        <div className="max-w-xl space-y-3">
            <Heading
                variant="small"
                title="Gutschrift / Storno"
                description="Wird negativ gespeichert und in diese Rechnung eingerechnet"
            />
            <form
                className="flex flex-wrap items-end gap-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    post(`/outgoing-invoices/${invoiceId}/adjustments`, {
                        preserveScroll: true,
                        onSuccess: () => reset('amount', 'number'),
                    });
                }}
            >
                <div className="grid w-36 gap-2">
                    <Label>Belegart</Label>
                    <Select
                        value={data.doc_type}
                        onValueChange={(value) => setData('doc_type', value)}
                    >
                        <SelectTrigger aria-label="Belegart">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="credit_note">
                                Gutschrift
                            </SelectItem>
                            <SelectItem value="cancellation">Storno</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid w-32 gap-2">
                    <Label htmlFor="adjustment-number">Belegnummer</Label>
                    <Input
                        id="adjustment-number"
                        value={data.number}
                        onChange={(e) => setData('number', e.target.value)}
                        required
                    />
                    <InputError
                        message={
                            errors.number ?? errors.amount ?? errors.doc_type
                        }
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="adjustment-date">Datum</Label>
                    <Input
                        id="adjustment-date"
                        type="date"
                        value={data.invoice_date}
                        onChange={(e) =>
                            setData('invoice_date', e.target.value)
                        }
                        required
                    />
                </div>
                <div className="grid w-32 gap-2">
                    <Label htmlFor="adjustment-amount">Brutto (€)</Label>
                    <Input
                        id="adjustment-amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        value={data.amount}
                        onChange={(e) => setData('amount', e.target.value)}
                        required
                    />
                </div>
                <Button type="submit" variant="outline" disabled={processing}>
                    Buchen
                </Button>
            </form>
        </div>
    );
}

OutgoingInvoicesShow.layout = ({ invoice }: Props) => ({
    breadcrumbs: [
        { title: 'Ausgangsrechnungen', href: '/outgoing-invoices' },
        { title: invoice.number, href: `/outgoing-invoices/${invoice.id}` },
    ],
});

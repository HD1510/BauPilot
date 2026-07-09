import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Customer = { id: number; name: string; payment_target_days: number };
type Project = { id: number; title: string; customer_id: number };
type PartialInvoice = { id: number; number: string; customer_id: number };
type ZeroRateReason = { value: string; label: string };

const docTypes = [
    { value: 'invoice', label: 'Rechnung' },
    { value: 'partial', label: 'Teilrechnung' },
    { value: 'final', label: 'Schlussrechnung (Restbetrag)' },
];

export default function OutgoingInvoicesCreate({
    customers,
    projects,
    partialInvoices,
    zeroRateReasons,
}: {
    customers: Customer[];
    projects: Project[];
    partialInvoices: PartialInvoice[];
    zeroRateReasons: ZeroRateReason[];
}) {
    const { data, setData, post, processing, errors, transform } = useForm<{
        doc_type: string;
        number: string;
        invoice_date: string;
        due_on: string;
        customer_id: string;
        project_id: string;
        amount_mode: string;
        amount: string;
        vat_rate: string;
        zero_rate_reason: string;
        partial_ids: number[];
        notes: string;
    }>({
        doc_type: 'invoice',
        number: '',
        invoice_date: new Date().toISOString().slice(0, 10),
        due_on: '',
        customer_id: '',
        project_id: 'none',
        amount_mode: 'net',
        amount: '',
        vat_rate: '20',
        zero_rate_reason: 'none',
        partial_ids: [],
        notes: '',
    });

    const customerPartials = partialInvoices.filter(
        (partial) => String(partial.customer_id) === data.customer_id,
    );

    return (
        <>
            <Head title="Neue Ausgangsrechnung" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Neue Ausgangsrechnung"
                    description="Netto oder brutto eingeben — das Gegenstück rechnet der Server (MoneyHelper)"
                />
                <form
                    className="max-w-xl space-y-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        transform((values) => ({
                            ...values,
                            customer_id: values.customer_id ? Number(values.customer_id) : null,
                            project_id: values.project_id === 'none' ? null : Number(values.project_id),
                            zero_rate_reason: values.zero_rate_reason === 'none' ? null : values.zero_rate_reason,
                            due_on: values.due_on || null,
                        }));
                        post('/outgoing-invoices', { preserveScroll: true });
                    }}
                >
                    <fieldset className="space-y-6" disabled={processing}>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="doc_type">Belegart</Label>
                                <Select value={data.doc_type} onValueChange={(value) => setData('doc_type', value)}>
                                    <SelectTrigger id="doc_type"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {docTypes.map((docType) => (
                                            <SelectItem key={docType.value} value={docType.value}>{docType.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.doc_type} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="number">Rechnungsnummer</Label>
                                <Input id="number" value={data.number} onChange={(e) => setData('number', e.target.value)} placeholder="z. B. 250185" required />
                                <InputError message={errors.number} />
                            </div>
                        </div>

                        {data.doc_type === 'final' && customerPartials.length > 0 && (
                            <div className="grid gap-2 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                                <Label>Teilrechnungen dieser Schlussrechnung zuordnen</Label>
                                {customerPartials.map((partial) => (
                                    <Label key={partial.id} className="flex items-center gap-2 text-sm font-normal">
                                        <Checkbox
                                            checked={data.partial_ids.includes(partial.id)}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'partial_ids',
                                                    checked
                                                        ? [...data.partial_ids, partial.id]
                                                        : data.partial_ids.filter((id) => id !== partial.id),
                                                )
                                            }
                                        />
                                        Teilrechnung {partial.number}
                                    </Label>
                                ))}
                                <p className="text-xs text-muted-foreground">
                                    Die Schlussrechnung trägt nur den Restbetrag; Teilrechnungen bleiben eigenständige Posten.
                                </p>
                            </div>
                        )}

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="customer_id">Kunde</Label>
                                <Select value={data.customer_id} onValueChange={(value) => setData('customer_id', value)}>
                                    <SelectTrigger id="customer_id"><SelectValue placeholder="Kunde wählen" /></SelectTrigger>
                                    <SelectContent>
                                        {customers.map((customer) => (
                                            <SelectItem key={customer.id} value={String(customer.id)}>{customer.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.customer_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="project_id">Projekt (optional)</Label>
                                <Select value={data.project_id} onValueChange={(value) => setData('project_id', value)}>
                                    <SelectTrigger id="project_id"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Kein Projekt</SelectItem>
                                        {projects.map((project) => (
                                            <SelectItem key={project.id} value={String(project.id)}>{project.title}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.project_id} />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="invoice_date">Rechnungsdatum</Label>
                                <Input id="invoice_date" type="date" value={data.invoice_date} onChange={(e) => setData('invoice_date', e.target.value)} required />
                                <InputError message={errors.invoice_date} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="due_on">Fällig am (leer = Kunden-Zahlungsziel)</Label>
                                <Input id="due_on" type="date" value={data.due_on} onChange={(e) => setData('due_on', e.target.value)} />
                                <InputError message={errors.due_on} />
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="amount">Betrag (€)</Label>
                                <Input id="amount" type="number" step="0.01" min="0.01" value={data.amount} onChange={(e) => setData('amount', e.target.value)} required />
                                <InputError message={errors.amount} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="amount_mode">Eingabe als</Label>
                                <Select value={data.amount_mode} onValueChange={(value) => setData('amount_mode', value)}>
                                    <SelectTrigger id="amount_mode"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="net">Netto</SelectItem>
                                        <SelectItem value="gross">Brutto</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="vat_rate">USt-Satz (%)</Label>
                                <Input id="vat_rate" type="number" step="0.01" min="0" max="100" value={data.vat_rate} onChange={(e) => setData('vat_rate', e.target.value)} required />
                                <InputError message={errors.vat_rate} />
                            </div>
                        </div>

                        {Number(data.vat_rate) === 0 && (
                            <div className="grid gap-2">
                                <Label htmlFor="zero_rate_reason">Grund für 0 % USt</Label>
                                <Select value={data.zero_rate_reason} onValueChange={(value) => setData('zero_rate_reason', value)}>
                                    <SelectTrigger id="zero_rate_reason"><SelectValue placeholder="Grund wählen" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Bitte wählen</SelectItem>
                                        {zeroRateReasons.map((reason) => (
                                            <SelectItem key={reason.value} value={reason.value}>{reason.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.zero_rate_reason} />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notizen</Label>
                            <Textarea id="notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            <InputError message={errors.notes} />
                        </div>

                        <Button type="submit">Rechnung anlegen</Button>
                    </fieldset>
                </form>
            </div>
        </>
    );
}

OutgoingInvoicesCreate.layout = {
    breadcrumbs: [
        { title: 'Ausgangsrechnungen', href: '/outgoing-invoices' },
        { title: 'Neue Rechnung', href: '/outgoing-invoices/create' },
    ],
};

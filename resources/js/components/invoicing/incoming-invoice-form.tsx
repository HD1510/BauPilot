import { useForm } from '@inertiajs/react';
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

export type IncomingInvoiceFormValues = {
    supplier_id: number | null;
    supplier_invoice_no: string | null;
    invoice_date: string;
    date_estimated: boolean;
    net: string;
    amount_mode?: 'net' | 'gross';
    amount?: string;
    vat_rate: string;
    reverse_charge: boolean;
    cost_type_id: number | null;
    project_id: number | null;
    payment_method: string | null;
    payment_due_on: string | null;
    skonto_amount: string | null;
    skonto_until: string | null;
    subject: string | null;
    notes: string | null;
    lock_version?: number;
};

export type SupplierOption = {
    id: number;
    name: string;
    payment_target_days: number;
    default_cost_type_id: number | null;
};
export type Option = { id: number; name: string };
export type ProjectOption = { id: number; title: string };

export function IncomingInvoiceForm({
    action,
    method,
    invoice,
    suppliers,
    costTypes,
    projects,
    submitLabel,
    disabled = false,
    scanToken,
}: {
    action: string;
    method: 'post' | 'patch';
    invoice?: Partial<IncomingInvoiceFormValues>;
    suppliers: SupplierOption[];
    costTypes: Option[];
    projects: ProjectOption[];
    submitLabel: string;
    disabled?: boolean;
    scanToken?: string;
}) {
    const { data, setData, post, patch, processing, errors, transform } =
        useForm({
            supplier_id: invoice?.supplier_id
                ? String(invoice.supplier_id)
                : '',
            supplier_invoice_no: invoice?.supplier_invoice_no ?? '',
            invoice_date:
                invoice?.invoice_date ?? new Date().toISOString().slice(0, 10),
            date_estimated: invoice?.date_estimated ?? false,
            amount_mode: invoice?.amount_mode ?? 'net',
            amount: invoice?.amount ?? invoice?.net ?? '',
            vat_rate: invoice?.vat_rate ?? '20',
            reverse_charge: invoice?.reverse_charge ?? false,
            cost_type_id: invoice?.cost_type_id
                ? String(invoice.cost_type_id)
                : '',
            project_id: invoice?.project_id
                ? String(invoice.project_id)
                : 'none',
            payment_method: invoice?.payment_method ?? '',
            payment_due_on: invoice?.payment_due_on ?? '',
            skonto_amount: invoice?.skonto_amount ?? '',
            skonto_until: invoice?.skonto_until ?? '',
            subject: invoice?.subject ?? '',
            notes: invoice?.notes ?? '',
            lock_version: invoice?.lock_version ?? 0,
            scan_token: scanToken ?? '',
        });

    const selectSupplier = (value: string) => {
        setData('supplier_id', value);
        const supplier = suppliers.find((s) => String(s.id) === value);

        if (supplier?.default_cost_type_id && !data.cost_type_id) {
            setData('cost_type_id', String(supplier.default_cost_type_id));
        }
    };

    return (
        <form
            className="max-w-xl space-y-6"
            onSubmit={(event) => {
                event.preventDefault();
                transform((values) => ({
                    ...values,
                    supplier_id: values.supplier_id
                        ? Number(values.supplier_id)
                        : null,
                    cost_type_id: values.cost_type_id
                        ? Number(values.cost_type_id)
                        : null,
                    project_id:
                        values.project_id === 'none'
                            ? null
                            : Number(values.project_id),
                    payment_due_on: values.payment_due_on || null,
                    scan_token: values.scan_token || null,
                }));
                (method === 'post' ? post : patch)(action, {
                    preserveScroll: true,
                });
            }}
        >
            <fieldset className="space-y-6" disabled={disabled || processing}>
                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="supplier_id">Lieferant</Label>
                        <Select
                            value={data.supplier_id}
                            onValueChange={selectSupplier}
                        >
                            <SelectTrigger id="supplier_id">
                                <SelectValue placeholder="Lieferant wählen" />
                            </SelectTrigger>
                            <SelectContent>
                                {suppliers.map((supplier) => (
                                    <SelectItem
                                        key={supplier.id}
                                        value={String(supplier.id)}
                                    >
                                        {supplier.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={errors.supplier_id ?? errors.lock_version}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="supplier_invoice_no">
                            Rechnungsnummer des Lieferanten
                        </Label>
                        <Input
                            id="supplier_invoice_no"
                            value={data.supplier_invoice_no}
                            onChange={(e) =>
                                setData('supplier_invoice_no', e.target.value)
                            }
                        />
                        <InputError message={errors.supplier_invoice_no} />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="invoice_date">Rechnungsdatum</Label>
                        <Input
                            id="invoice_date"
                            type="date"
                            value={data.invoice_date}
                            onChange={(e) =>
                                setData('invoice_date', e.target.value)
                            }
                            required
                        />
                        <InputError message={errors.invoice_date} />
                        <Label className="flex items-center gap-2 text-sm font-normal">
                            <Checkbox
                                checked={data.date_estimated}
                                onCheckedChange={(checked) =>
                                    setData('date_estimated', checked === true)
                                }
                            />
                            Datum geschätzt (z. B. aus Import)
                        </Label>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="subject">Betreff</Label>
                        <Input
                            id="subject"
                            value={data.subject}
                            onChange={(e) => setData('subject', e.target.value)}
                        />
                        <InputError message={errors.subject} />
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="amount">Betrag (€)</Label>
                        <Input
                            id="amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            required
                        />
                        <InputError message={errors.amount} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="amount_mode">Eingabe als</Label>
                        <Select
                            value={data.amount_mode}
                            onValueChange={(value) =>
                                setData('amount_mode', value as 'net' | 'gross')
                            }
                        >
                            <SelectTrigger id="amount_mode">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="net">Netto</SelectItem>
                                <SelectItem value="gross">Brutto</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="vat_rate">USt-Satz (%)</Label>
                        <Input
                            id="vat_rate"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            value={data.vat_rate}
                            onChange={(e) =>
                                setData('vat_rate', e.target.value)
                            }
                            disabled={data.reverse_charge}
                            required
                        />
                        <InputError message={errors.vat_rate} />
                    </div>
                </div>

                <Label className="flex items-center gap-2 text-sm font-normal">
                    <Checkbox
                        checked={data.reverse_charge}
                        onCheckedChange={(checked) =>
                            setData('reverse_charge', checked === true)
                        }
                    />
                    §19 / Reverse Charge (Übergang der Steuerschuld — Satz 0,
                    USt 0)
                </Label>

                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="cost_type_id">Kostenart</Label>
                        <Select
                            value={data.cost_type_id}
                            onValueChange={(value) =>
                                setData('cost_type_id', value)
                            }
                        >
                            <SelectTrigger id="cost_type_id">
                                <SelectValue placeholder="Kostenart wählen" />
                            </SelectTrigger>
                            <SelectContent>
                                {costTypes.map((costType) => (
                                    <SelectItem
                                        key={costType.id}
                                        value={String(costType.id)}
                                    >
                                        {costType.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.cost_type_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="project_id">Projekt (optional)</Label>
                        <Select
                            value={data.project_id}
                            onValueChange={(value) =>
                                setData('project_id', value)
                            }
                        >
                            <SelectTrigger id="project_id">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">
                                    Kein Projekt
                                </SelectItem>
                                {projects.map((project) => (
                                    <SelectItem
                                        key={project.id}
                                        value={String(project.id)}
                                    >
                                        {project.title}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.project_id} />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="payment_due_on">
                            Zahlbar bis (leer = Lieferanten-Ziel)
                        </Label>
                        <Input
                            id="payment_due_on"
                            type="date"
                            value={data.payment_due_on}
                            onChange={(e) =>
                                setData('payment_due_on', e.target.value)
                            }
                        />
                        <InputError message={errors.payment_due_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="payment_method">Zahlungsart</Label>
                        <Input
                            id="payment_method"
                            value={data.payment_method}
                            onChange={(e) =>
                                setData('payment_method', e.target.value)
                            }
                            placeholder="z. B. Überweisung, Abbucher"
                        />
                        <InputError message={errors.payment_method} />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="skonto_amount">Skontobetrag (€)</Label>
                        <Input
                            id="skonto_amount"
                            type="number"
                            step="0.01"
                            min="0"
                            value={data.skonto_amount}
                            onChange={(e) =>
                                setData('skonto_amount', e.target.value)
                            }
                        />
                        <InputError message={errors.skonto_amount} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="skonto_until">Skonto bis</Label>
                        <Input
                            id="skonto_until"
                            type="date"
                            value={data.skonto_until}
                            onChange={(e) =>
                                setData('skonto_until', e.target.value)
                            }
                        />
                        <InputError message={errors.skonto_until} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="notes">Notizen</Label>
                    <Textarea
                        id="notes"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                    <InputError message={errors.notes} />
                </div>

                <Button type="submit">{submitLabel}</Button>
            </fieldset>
        </form>
    );
}

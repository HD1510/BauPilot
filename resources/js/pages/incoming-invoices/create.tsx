import { Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { IncomingInvoiceForm } from '@/components/invoicing/incoming-invoice-form';
import type {
    IncomingInvoiceFormValues,
    Option,
    ProjectOption,
    SupplierOption,
} from '@/components/invoicing/incoming-invoice-form';
import { InvoiceScanCard } from '@/components/invoicing/invoice-scan-card';
import type {
    ChosenSupplier,
    ScanResult,
} from '@/components/invoicing/invoice-scan-card';

type ScanState = {
    key: number;
    token: string;
    invoice: Partial<IncomingInvoiceFormValues>;
};

export default function IncomingInvoicesCreate({
    suppliers,
    costTypes,
    projects,
    scanEnabled,
}: {
    suppliers: SupplierOption[];
    costTypes: Option[];
    projects: ProjectOption[];
    scanEnabled: boolean;
}) {
    const [supplierList, setSupplierList] = useState(suppliers);
    const [scan, setScan] = useState<ScanState | null>(null);

    // Extraktion + gewählten Lieferanten ins Formular übernehmen. Das
    // Formular wird über key neu aufgebaut — der Scan füllt vor, der
    // Mensch prüft und speichert.
    const applyScan = (result: ScanResult, supplier: ChosenSupplier | null) => {
        if (supplier && !supplierList.some((s) => s.id === supplier.id)) {
            setSupplierList([
                ...supplierList,
                {
                    id: supplier.id,
                    name: supplier.name,
                    payment_target_days: 30,
                    default_cost_type_id: supplier.default_cost_type_id,
                },
            ]);
        }

        const prefill = result.prefill;

        setScan({
            key: (scan?.key ?? 0) + 1,
            token: result.scan_token,
            invoice: {
                supplier_id: supplier?.id ?? null,
                supplier_invoice_no: prefill.supplier_invoice_no,
                invoice_date: prefill.invoice_date ?? undefined,
                amount_mode: prefill.amount_mode,
                amount: prefill.amount ?? undefined,
                vat_rate: prefill.vat_rate ?? undefined,
                reverse_charge: prefill.reverse_charge,
                // Nur vorbelegen, wenn die Kostenart auch wählbar ist —
                // sonst zeigt das Pflichtfeld sichtbar „wählen".
                cost_type_id: costTypes.some(
                    (costType) =>
                        costType.id === supplier?.default_cost_type_id,
                )
                    ? (supplier?.default_cost_type_id ?? null)
                    : null,
                subject: prefill.subject,
                payment_due_on: prefill.payment_due_on,
                skonto_amount: prefill.skonto_amount,
                skonto_until: prefill.skonto_until,
            },
        });
    };

    return (
        <>
            <Head title="Neue Eingangsrechnung" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Neue Eingangsrechnung"
                    description="Kostenart Pflicht; §19-Rechnungen ohne USt"
                />
                {scanEnabled && <InvoiceScanCard onApply={applyScan} />}
                <IncomingInvoiceForm
                    key={scan?.key ?? 0}
                    action="/incoming-invoices"
                    method="post"
                    invoice={scan?.invoice}
                    scanToken={scan?.token}
                    suppliers={supplierList}
                    costTypes={costTypes}
                    projects={projects}
                    submitLabel="Eingangsrechnung erfassen"
                />
            </div>
        </>
    );
}

IncomingInvoicesCreate.layout = {
    breadcrumbs: [
        { title: 'Eingangsrechnungen', href: '/incoming-invoices' },
        { title: 'Neu', href: '/incoming-invoices/create' },
    ],
};

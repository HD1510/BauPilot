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
import {
    InvoiceScanCard,
    prefillString,
} from '@/components/invoicing/invoice-scan-card';
import type {
    ChosenPartner,
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
    scanImagesEnabled,
    preselectedProjectId,
}: {
    suppliers: SupplierOption[];
    costTypes: Option[];
    projects: ProjectOption[];
    scanImagesEnabled: boolean;
    preselectedProjectId: number | null;
}) {
    const [supplierList, setSupplierList] = useState(suppliers);
    const [scan, setScan] = useState<ScanState | null>(null);

    // Extraktion + gewählten Lieferanten ins Formular übernehmen. Das
    // Formular wird über key neu aufgebaut — der Scan füllt vor, der
    // Mensch prüft und speichert.
    const applyScan = (result: ScanResult, supplier: ChosenPartner | null) => {
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
                supplier_invoice_no:
                    prefillString(prefill.supplier_invoice_no) ?? null,
                invoice_date: prefillString(prefill.invoice_date),
                amount_mode: prefill.amount_mode === 'gross' ? 'gross' : 'net',
                amount: prefillString(prefill.amount),
                vat_rate: prefillString(prefill.vat_rate),
                reverse_charge: prefill.reverse_charge === true,
                // Nur vorbelegen, wenn die Kostenart auch wählbar ist —
                // sonst zeigt das Pflichtfeld sichtbar „wählen".
                cost_type_id: costTypes.some(
                    (costType) =>
                        costType.id === supplier?.default_cost_type_id,
                )
                    ? (supplier?.default_cost_type_id ?? null)
                    : null,
                project_id: preselectedProjectId,
                subject: prefillString(prefill.subject) ?? null,
                payment_due_on: prefillString(prefill.payment_due_on) ?? null,
                skonto_amount: prefillString(prefill.skonto_amount) ?? null,
                skonto_until: prefillString(prefill.skonto_until) ?? null,
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
                <InvoiceScanCard
                    onApply={applyScan}
                    scanUrl="/incoming-invoices/scan"
                    createPartnerUrl="/incoming-invoices/scan/supplier"
                    partnerLabel="Lieferant"
                    imagesEnabled={scanImagesEnabled}
                />
                <IncomingInvoiceForm
                    key={scan?.key ?? 0}
                    action="/incoming-invoices"
                    method="post"
                    invoice={
                        scan?.invoice ?? { project_id: preselectedProjectId }
                    }
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

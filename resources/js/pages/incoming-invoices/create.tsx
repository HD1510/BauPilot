import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    IncomingInvoiceForm
    
    
    
} from '@/components/invoicing/incoming-invoice-form';
import type {Option, ProjectOption, SupplierOption} from '@/components/invoicing/incoming-invoice-form';

export default function IncomingInvoicesCreate({
    suppliers,
    costTypes,
    projects,
}: {
    suppliers: SupplierOption[];
    costTypes: Option[];
    projects: ProjectOption[];
}) {
    return (
        <>
            <Head title="Neue Eingangsrechnung" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Neue Eingangsrechnung"
                    description="Kostenart Pflicht; §19-Rechnungen ohne USt"
                />
                <IncomingInvoiceForm
                    action="/incoming-invoices"
                    method="post"
                    suppliers={suppliers}
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

import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    SupplierForm
    
} from '@/components/master-data/supplier-form';
import type {CostTypeOption} from '@/components/master-data/supplier-form';

export default function SuppliersCreate({
    costTypes,
}: {
    costTypes: CostTypeOption[];
}) {
    return (
        <>
            <Head title="Neuer Lieferant" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Neuer Lieferant"
                    description="Bei ähnlichen Namen schlägt BauPilot mögliche Dubletten vor"
                />
                <SupplierForm
                    action="/suppliers"
                    method="post"
                    costTypes={costTypes}
                    submitLabel="Lieferant anlegen"
                />
            </div>
        </>
    );
}

SuppliersCreate.layout = {
    breadcrumbs: [
        { title: 'Lieferanten', href: '/suppliers' },
        { title: 'Neuer Lieferant', href: '/suppliers/create' },
    ],
};

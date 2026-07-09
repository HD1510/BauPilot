import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    MaterialForm
    
} from '@/components/master-data/material-form';
import type {SupplierOption} from '@/components/master-data/material-form';

export default function MaterialsCreate({
    suppliers,
}: {
    suppliers: SupplierOption[];
}) {
    return (
        <>
            <Head title="Neuer Artikel" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Neuer Artikel"
                    description="Artikel gehören immer zu einem Lieferanten"
                />
                <MaterialForm
                    action="/materials"
                    method="post"
                    suppliers={suppliers}
                    submitLabel="Artikel anlegen"
                />
            </div>
        </>
    );
}

MaterialsCreate.layout = {
    breadcrumbs: [
        { title: 'Material', href: '/materials' },
        { title: 'Neuer Artikel', href: '/materials/create' },
    ],
};

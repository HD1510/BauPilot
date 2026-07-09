import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { CustomerForm } from '@/components/master-data/customer-form';

export default function CustomersCreate() {
    return (
        <>
            <Head title="Neuer Kunde" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Neuer Kunde"
                    description="Bei ähnlichen Namen schlägt BauPilot mögliche Dubletten vor"
                />
                <CustomerForm
                    action="/customers"
                    method="post"
                    submitLabel="Kunde anlegen"
                />
            </div>
        </>
    );
}

CustomersCreate.layout = {
    breadcrumbs: [
        { title: 'Kunden', href: '/customers' },
        { title: 'Neuer Kunde', href: '/customers/create' },
    ],
};

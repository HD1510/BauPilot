import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    OfferForm
    
    
} from '@/components/sales/offer-form';
import type {Option, StatusOption} from '@/components/sales/offer-form';

export default function OffersCreate({
    customers,
    statuses,
}: {
    customers: Option[];
    statuses: StatusOption[];
}) {
    return (
        <>
            <Head title="Neues Angebot" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Neues Angebot"
                    description="Von der Anfrage über die Besichtigung bis zur Annahme"
                />
                <OfferForm
                    action="/offers"
                    method="post"
                    customers={customers}
                    statuses={statuses}
                    submitLabel="Angebot anlegen"
                />
            </div>
        </>
    );
}

OffersCreate.layout = {
    breadcrumbs: [
        { title: 'Angebote', href: '/offers' },
        { title: 'Neues Angebot', href: '/offers/create' },
    ],
};

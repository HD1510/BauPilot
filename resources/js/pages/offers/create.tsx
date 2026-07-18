import { Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import {
    InvoiceScanCard,
    prefillString,
} from '@/components/invoicing/invoice-scan-card';
import type {
    ChosenPartner,
    ScanResult,
} from '@/components/invoicing/invoice-scan-card';
import { OfferForm } from '@/components/sales/offer-form';
import type {
    OfferFormValues,
    Option,
    StatusOption,
} from '@/components/sales/offer-form';

export default function OffersCreate({
    customers,
    statuses,
    scanImagesEnabled,
}: {
    customers: Option[];
    statuses: StatusOption[];
    scanImagesEnabled: boolean;
}) {
    const [customerList, setCustomerList] = useState(customers);
    const [scan, setScan] = useState<{
        key: number;
        token: string;
        offer: Partial<OfferFormValues>;
    } | null>(null);

    // Erkanntes Angebot + gewählten Kunden ins Formular übernehmen —
    // das Formular wird über key neu aufgebaut, der Mensch prüft.
    const applyScan = (result: ScanResult, customer: ChosenPartner | null) => {
        if (customer && !customerList.some((c) => c.id === customer.id)) {
            setCustomerList([
                ...customerList,
                { id: customer.id, name: customer.name },
            ]);
        }

        const prefill = result.prefill;

        setScan({
            key: (scan?.key ?? 0) + 1,
            token: result.scan_token,
            offer: {
                customer_id: customer?.id ?? null,
                offer_number: prefillString(prefill.offer_number) ?? null,
                offer_amount_net:
                    prefillString(prefill.offer_amount_net) ?? null,
                description: prefillString(prefill.description) ?? null,
                status: 'offered',
            },
        });
    };

    return (
        <>
            <Head title="Neues Angebot" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Neues Angebot"
                    description="Von der Anfrage über die Besichtigung bis zur Annahme"
                />
                <InvoiceScanCard
                    onApply={applyScan}
                    scanUrl="/offers/scan"
                    createPartnerUrl="/scan/customer"
                    partnerLabel="Kunde"
                    imagesEnabled={scanImagesEnabled}
                />
                <OfferForm
                    key={scan?.key ?? 0}
                    action="/offers"
                    method="post"
                    offer={scan?.offer}
                    scanToken={scan?.token}
                    customers={customerList}
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

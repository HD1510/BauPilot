import { Head, Link, router } from '@inertiajs/react';
import { FolderKanban } from 'lucide-react';
import Heading from '@/components/heading';
import {
    OfferForm
    
    
    
} from '@/components/sales/offer-form';
import type {OfferFormValues, Option, StatusOption} from '@/components/sales/offer-form';
import { Button } from '@/components/ui/button';

type Props = {
    offer: OfferFormValues & { id: number; project_id: number | null };
    customers: Option[];
    statuses: StatusOption[];
};

export default function OffersEdit({ offer, customers, statuses }: Props) {
    return (
        <>
            <Head title="Angebot bearbeiten" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={`Angebot ${offer.offer_number ?? `#${offer.id}`}`}
                        description="Statuslauf und Übernahme ins Projekt"
                    />
                    {offer.project_id ? (
                        <Button variant="outline" asChild>
                            <Link href={`/projects/${offer.project_id}`}>
                                <FolderKanban className="size-4" />
                                Zum Projekt
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            onClick={() =>
                                router.post(`/offers/${offer.id}/convert`)
                            }
                        >
                            <FolderKanban className="size-4" />
                            In Projekt übernehmen
                        </Button>
                    )}
                </div>

                <OfferForm
                    action={`/offers/${offer.id}`}
                    method="patch"
                    offer={offer}
                    customers={customers}
                    statuses={statuses}
                    submitLabel="Änderungen speichern"
                />
            </div>
        </>
    );
}

OffersEdit.layout = ({ offer }: Props) => ({
    breadcrumbs: [
        { title: 'Angebote', href: '/offers' },
        {
            title: offer.offer_number ?? `#${offer.id}`,
            href: `/offers/${offer.id}/edit`,
        },
    ],
});

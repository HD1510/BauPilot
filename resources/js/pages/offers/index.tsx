import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate, formatEUR } from '@/lib/format';

type OfferRow = {
    id: number;
    customer: string | null;
    location: string | null;
    status: string;
    status_label: string;
    viewing_on: string | null;
    follow_up_on: string | null;
    offer_number: string | null;
    offer_amount_net: string | null;
    project_id: number | null;
};

type StatusOption = { value: string; label: string };

export default function OffersIndex({
    offers,
    filters,
    statuses,
}: {
    offers: OfferRow[];
    filters: { q: string; status: string };
    statuses: StatusOption[];
}) {
    const { tenancy } = usePage().props;

    return (
        <>
            <Head title="Angebote" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Angebote"
                        description="Anfragen und Angebote an Kunden mit Statuslauf"
                    />
                    <Button asChild>
                        <Link href="/offers/create">
                            <Plus className="size-4" />
                            Neues Angebot
                        </Link>
                    </Button>
                </div>

                <Select
                    value={filters.status || 'all'}
                    onValueChange={(value) =>
                        router.get(
                            '/offers',
                            value === 'all' ? {} : { status: value },
                            { preserveState: true, replace: true },
                        )
                    }
                >
                    <SelectTrigger className="w-56" aria-label="Status-Filter">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Alle Status</SelectItem>
                        {statuses.map((status) => (
                            <SelectItem key={status.value} value={status.value}>
                                {status.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <div className="grid gap-2">
                    {offers.map((offer) => (
                        <Link
                            key={offer.id}
                            href={`/offers/${offer.id}/edit`}
                            className="flex items-center gap-4 rounded-lg border border-sidebar-border/70 p-3 hover:bg-accent/50 dark:border-sidebar-border"
                        >
                            <div className="flex-1">
                                <div className="flex items-center gap-2 font-medium">
                                    {offer.customer}
                                    <Badge variant="outline">
                                        {offer.status_label}
                                    </Badge>
                                    {offer.project_id && (
                                        <Badge variant="secondary">
                                            Projekt
                                        </Badge>
                                    )}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {offer.location ?? '—'}
                                    {offer.viewing_on &&
                                        ` · Besichtigung: ${formatDate(offer.viewing_on)}`}
                                    {offer.follow_up_on &&
                                        ` · Wiedervorlage: ${formatDate(offer.follow_up_on)}`}
                                </div>
                            </div>
                            {tenancy.canViewFinancials &&
                                offer.offer_amount_net && (
                                    <div className="text-sm text-muted-foreground">
                                        {formatEUR(offer.offer_amount_net)}{' '}
                                        netto
                                    </div>
                                )}
                        </Link>
                    ))}
                    {offers.length === 0 && (
                        <p className="py-8 text-center text-muted-foreground">
                            Keine Angebote gefunden.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

OffersIndex.layout = {
    breadcrumbs: [{ title: 'Angebote', href: '/offers' }],
};

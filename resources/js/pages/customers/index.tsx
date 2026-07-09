import { Head, Link } from '@inertiajs/react';
import {
    EmptyState,
    IndexShell
    
} from '@/components/master-data/index-shell';
import type {IndexFilters} from '@/components/master-data/index-shell';
import { Badge } from '@/components/ui/badge';

type CustomerListItem = {
    id: number;
    name: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    payment_target_days: number;
    contacts_count: number;
    archived: boolean;
};

export default function CustomersIndex({
    customers,
    filters,
}: {
    customers: CustomerListItem[];
    filters: IndexFilters;
}) {
    return (
        <>
            <Head title="Kunden" />
            <IndexShell
                title="Kunden"
                description="Kundenstamm der aktiven Firma"
                basePath="/customers"
                createLabel="Neuer Kunde"
                filters={filters}
            >
                {customers.map((customer) => (
                    <Link
                        key={customer.id}
                        href={`/customers/${customer.id}/edit`}
                        className="flex items-center gap-4 rounded-lg border border-sidebar-border/70 p-3 hover:bg-accent/50 dark:border-sidebar-border"
                    >
                        <div className="flex-1">
                            <div className="flex items-center gap-2 font-medium">
                                {customer.name}
                                {customer.archived && (
                                    <Badge variant="secondary">
                                        archiviert
                                    </Badge>
                                )}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {[customer.address, customer.phone, customer.email]
                                    .filter(Boolean)
                                    .join(' · ') || '—'}
                            </div>
                        </div>
                        <div className="text-sm text-muted-foreground">
                            Ziel: {customer.payment_target_days} Tage
                            {customer.contacts_count > 0 &&
                                ` · ${customer.contacts_count} Ansprechpartner`}
                        </div>
                    </Link>
                ))}
                {customers.length === 0 && (
                    <EmptyState archived={filters.archived} />
                )}
            </IndexShell>
        </>
    );
}

CustomersIndex.layout = {
    breadcrumbs: [{ title: 'Kunden', href: '/customers' }],
};

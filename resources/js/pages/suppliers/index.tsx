import { Head, Link } from '@inertiajs/react';
import {
    EmptyState,
    IndexShell
    
} from '@/components/master-data/index-shell';
import type {IndexFilters} from '@/components/master-data/index-shell';
import { Badge } from '@/components/ui/badge';

type SupplierListItem = {
    id: number;
    name: string;
    short_code: string | null;
    payment_target_days: number;
    skonto_percent: string | null;
    skonto_days: number | null;
    default_cost_type: string | null;
    archived: boolean;
};

export default function SuppliersIndex({
    suppliers,
    filters,
}: {
    suppliers: SupplierListItem[];
    filters: IndexFilters;
}) {
    return (
        <>
            <Head title="Lieferanten" />
            <IndexShell
                title="Lieferanten"
                description="Lieferantenstamm der aktiven Firma"
                basePath="/suppliers"
                createLabel="Neuer Lieferant"
                filters={filters}
            >
                {suppliers.map((supplier) => (
                    <Link
                        key={supplier.id}
                        href={`/suppliers/${supplier.id}/edit`}
                        className="flex items-center gap-4 rounded-lg border border-sidebar-border/70 p-3 hover:bg-accent/50 dark:border-sidebar-border"
                    >
                        <div className="flex-1">
                            <div className="flex items-center gap-2 font-medium">
                                {supplier.name}
                                {supplier.short_code && (
                                    <Badge variant="outline">
                                        {supplier.short_code}
                                    </Badge>
                                )}
                                {supplier.archived && (
                                    <Badge variant="secondary">
                                        archiviert
                                    </Badge>
                                )}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {supplier.default_cost_type ??
                                    'Keine Standard-Kostenart'}
                            </div>
                        </div>
                        <div className="text-right text-sm text-muted-foreground">
                            <div>Ziel: {supplier.payment_target_days} Tage</div>
                            {supplier.skonto_percent && (
                                <div>
                                    Skonto: {supplier.skonto_percent} % /{' '}
                                    {supplier.skonto_days} Tage
                                </div>
                            )}
                        </div>
                    </Link>
                ))}
                {suppliers.length === 0 && (
                    <EmptyState archived={filters.archived} />
                )}
            </IndexShell>
        </>
    );
}

SuppliersIndex.layout = {
    breadcrumbs: [{ title: 'Lieferanten', href: '/suppliers' }],
};

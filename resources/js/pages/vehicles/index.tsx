import { Head, Link } from '@inertiajs/react';
import {
    EmptyState,
    IndexShell
    
} from '@/components/master-data/index-shell';
import type {IndexFilters} from '@/components/master-data/index-shell';
import { Badge } from '@/components/ui/badge';

type VehicleListItem = {
    id: number;
    plate: string;
    brand: string | null;
    model: string | null;
    inspection_due_on: string | null;
    vignette_until: string | null;
    dates_count: number;
    archived: boolean;
};

function formatDate(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return new Date(value).toLocaleDateString('de-AT');
}

export default function VehiclesIndex({
    vehicles,
    filters,
}: {
    vehicles: VehicleListItem[];
    filters: IndexFilters;
}) {
    return (
        <>
            <Head title="Fahrzeuge" />
            <IndexShell
                title="Fahrzeuge"
                description="Fuhrpark mit Pickerl-, Vignetten- und Zusatzterminen"
                basePath="/vehicles"
                createLabel="Neues Fahrzeug"
                filters={filters}
            >
                {vehicles.map((vehicle) => (
                    <Link
                        key={vehicle.id}
                        href={`/vehicles/${vehicle.id}/edit`}
                        className="flex items-center gap-4 rounded-lg border border-sidebar-border/70 p-3 hover:bg-accent/50 dark:border-sidebar-border"
                    >
                        <div className="flex-1">
                            <div className="flex items-center gap-2 font-medium">
                                {vehicle.plate}
                                {vehicle.archived && (
                                    <Badge variant="secondary">
                                        archiviert
                                    </Badge>
                                )}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {[vehicle.brand, vehicle.model]
                                    .filter(Boolean)
                                    .join(' ') || '—'}
                            </div>
                        </div>
                        <div className="text-right text-sm text-muted-foreground">
                            {vehicle.inspection_due_on && (
                                <div>
                                    Pickerl:{' '}
                                    {formatDate(vehicle.inspection_due_on)}
                                </div>
                            )}
                            {vehicle.vignette_until && (
                                <div>
                                    Vignette:{' '}
                                    {formatDate(vehicle.vignette_until)}
                                </div>
                            )}
                            {vehicle.dates_count > 0 && (
                                <div>{vehicle.dates_count} weitere Termine</div>
                            )}
                        </div>
                    </Link>
                ))}
                {vehicles.length === 0 && (
                    <EmptyState archived={filters.archived} />
                )}
            </IndexShell>
        </>
    );
}

VehiclesIndex.layout = {
    breadcrumbs: [{ title: 'Fahrzeuge', href: '/vehicles' }],
};

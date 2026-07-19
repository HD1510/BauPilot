import { Head, Link, usePage } from '@inertiajs/react';
import { EmptyState, IndexShell } from '@/components/master-data/index-shell';
import type { IndexFilters } from '@/components/master-data/index-shell';
import { MaterialScanCard } from '@/components/master-data/material-scan-card';
import { Badge } from '@/components/ui/badge';

type MaterialListItem = {
    id: number;
    name: string;
    article_no: string | null;
    price_net: string | null;
    package_unit: string | null;
    supplier: string | null;
    archived: boolean;
};

export default function MaterialsIndex({
    materials,
    filters,
    canWrite,
    suppliers,
    scanImagesEnabled,
}: {
    materials: MaterialListItem[];
    filters: IndexFilters;
    canWrite: boolean;
    suppliers: { id: number; name: string }[];
    scanImagesEnabled: boolean;
}) {
    const { tenancy } = usePage().props;

    return (
        <>
            <Head title="Material" />
            <IndexShell
                title="Material"
                description="Artikel-Preisliste je Lieferant"
                basePath="/materials"
                createLabel="Neuer Artikel"
                filters={filters}
            >
                {canWrite && (
                    <MaterialScanCard
                        suppliers={suppliers}
                        imagesEnabled={scanImagesEnabled}
                    />
                )}
                {materials.map((material) => (
                    <Link
                        key={material.id}
                        href={`/materials/${material.id}/edit`}
                        className="flex items-center gap-4 rounded-lg border border-sidebar-border/70 p-3 hover:bg-accent/50 dark:border-sidebar-border"
                    >
                        <div className="flex-1">
                            <div className="flex items-center gap-2 font-medium">
                                {material.name}
                                {material.article_no && (
                                    <Badge variant="outline">
                                        {material.article_no}
                                    </Badge>
                                )}
                                {material.archived && (
                                    <Badge variant="secondary">
                                        archiviert
                                    </Badge>
                                )}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {material.supplier ?? '—'}
                            </div>
                        </div>
                        {tenancy.canViewFinancials && material.price_net && (
                            <div className="text-sm text-muted-foreground">
                                {material.price_net} €
                                {material.package_unit &&
                                    ` / ${material.package_unit}`}
                            </div>
                        )}
                    </Link>
                ))}
                {materials.length === 0 && (
                    <EmptyState archived={filters.archived} />
                )}
            </IndexShell>
        </>
    );
}

MaterialsIndex.layout = {
    breadcrumbs: [{ title: 'Material', href: '/materials' }],
};

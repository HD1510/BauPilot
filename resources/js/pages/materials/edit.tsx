import { Head, router } from '@inertiajs/react';
import { Archive, ArchiveRestore } from 'lucide-react';
import Heading from '@/components/heading';
import {
    MaterialForm
    
    
} from '@/components/master-data/material-form';
import type {MaterialFormValues, SupplierOption} from '@/components/master-data/material-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    material: MaterialFormValues & { id: number; active: boolean };
    suppliers: SupplierOption[];
    canWrite: boolean;
};

export default function MaterialsEdit({ material, suppliers, canWrite }: Props) {
    return (
        <>
            <Head title={`Artikel: ${material.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={material.name}
                        description="Artikel-Stammblatt"
                    />
                    {canWrite && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.patch(
                                    `/materials/${material.id}/archive`,
                                )
                            }
                        >
                            {material.active ? (
                                <>
                                    <Archive className="size-4" />
                                    Archivieren
                                </>
                            ) : (
                                <>
                                    <ArchiveRestore className="size-4" />
                                    Wieder aktivieren
                                </>
                            )}
                        </Button>
                    )}
                </div>

                {!material.active && (
                    <Badge variant="secondary" className="w-fit">
                        Dieser Artikel ist archiviert.
                    </Badge>
                )}

                <MaterialForm
                    action={`/materials/${material.id}`}
                    method="patch"
                    material={material}
                    suppliers={suppliers}
                    submitLabel="Änderungen speichern"
                    disabled={!canWrite || !material.active}
                />
            </div>
        </>
    );
}

MaterialsEdit.layout = ({ material }: Props) => ({
    breadcrumbs: [
        { title: 'Material', href: '/materials' },
        { title: material.name, href: `/materials/${material.id}/edit` },
    ],
});

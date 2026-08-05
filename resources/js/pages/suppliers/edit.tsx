import { Head, router } from '@inertiajs/react';
import { Archive, ArchiveRestore, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { SupplierForm } from '@/components/master-data/supplier-form';
import type {
    CostTypeOption,
    SupplierFormValues,
} from '@/components/master-data/supplier-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    supplier: SupplierFormValues & { id: number; active: boolean };
    costTypes: CostTypeOption[];
    canWrite: boolean;
};

export default function SuppliersEdit({
    supplier,
    costTypes,
    canWrite,
}: Props) {
    return (
        <>
            <Head title={`Lieferant: ${supplier.name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={supplier.name}
                        description="Lieferantenstammblatt"
                    />
                    {canWrite && (
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.patch(
                                        `/suppliers/${supplier.id}/archive`,
                                    )
                                }
                            >
                                {supplier.active ? (
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
                            <Button
                                variant="outline"
                                onClick={() => {
                                    if (
                                        confirm(
                                            `Lieferant „${supplier.name}“ wirklich endgültig löschen?`,
                                        )
                                    ) {
                                        router.delete(
                                            `/suppliers/${supplier.id}`,
                                        );
                                    }
                                }}
                            >
                                <Trash2 className="size-4" />
                                Löschen
                            </Button>
                        </div>
                    )}
                </div>

                {!supplier.active && (
                    <Badge variant="secondary" className="w-fit">
                        Dieser Lieferant ist archiviert.
                    </Badge>
                )}

                <SupplierForm
                    action={`/suppliers/${supplier.id}`}
                    method="patch"
                    supplier={supplier}
                    costTypes={costTypes}
                    submitLabel="Änderungen speichern"
                    disabled={!canWrite || !supplier.active}
                />
            </div>
        </>
    );
}

SuppliersEdit.layout = ({ supplier }: Props) => ({
    breadcrumbs: [
        { title: 'Lieferanten', href: '/suppliers' },
        { title: supplier.name, href: `/suppliers/${supplier.id}/edit` },
    ],
});

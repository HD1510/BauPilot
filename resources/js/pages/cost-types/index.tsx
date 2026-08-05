import { Head, router, useForm } from '@inertiajs/react';
import {
    Archive,
    ArchiveRestore,
    Check,
    Pencil,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { EmptyState } from '@/components/master-data/index-shell';
import type { IndexFilters } from '@/components/master-data/index-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type CostTypeItem = {
    id: number;
    name: string;
    sort_order: number;
    archived: boolean;
    lock_version: number;
};

export default function CostTypesIndex({
    costTypes,
    filters,
    canWrite,
}: {
    costTypes: CostTypeItem[];
    filters: IndexFilters;
    canWrite: boolean;
}) {
    return (
        <>
            <Head title="Kostenarten" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Kostenarten"
                    description="Ordnen Eingangsrechnungen einer Kategorie zu — je Firma frei definierbar"
                />

                <Label className="flex w-fit items-center gap-2 text-sm font-normal">
                    <Checkbox
                        checked={filters.archived}
                        onCheckedChange={(checked) =>
                            router.get(
                                '/cost-types',
                                { archived: checked ? 1 : undefined },
                                { preserveState: true, replace: true },
                            )
                        }
                    />
                    Archivierte anzeigen
                </Label>

                <div className="grid max-w-xl gap-2">
                    {costTypes.map((costType) => (
                        <CostTypeRow
                            key={costType.id}
                            costType={costType}
                            canWrite={canWrite}
                        />
                    ))}
                    {costTypes.length === 0 && (
                        <EmptyState archived={filters.archived} />
                    )}
                </div>

                {canWrite && <AddCostTypeForm />}
            </div>
        </>
    );
}

function CostTypeRow({
    costType,
    canWrite,
}: {
    costType: CostTypeItem;
    canWrite: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const { data, setData, patch, processing, errors } = useForm({
        name: costType.name,
        sort_order: costType.sort_order,
        lock_version: costType.lock_version,
    });

    if (editing) {
        return (
            <form
                className="flex items-end gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                onSubmit={(event) => {
                    event.preventDefault();
                    patch(`/cost-types/${costType.id}`, {
                        preserveScroll: true,
                        onSuccess: () => setEditing(false),
                    });
                }}
            >
                <div className="grid flex-1 gap-2">
                    <Label htmlFor={`name-${costType.id}`}>Name</Label>
                    <Input
                        id={`name-${costType.id}`}
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                    <InputError message={errors.name ?? errors.lock_version} />
                </div>
                <div className="grid w-28 gap-2">
                    <Label htmlFor={`sort-${costType.id}`}>Reihenfolge</Label>
                    <Input
                        id={`sort-${costType.id}`}
                        type="number"
                        min={0}
                        value={data.sort_order}
                        onChange={(e) =>
                            setData('sort_order', Number(e.target.value))
                        }
                    />
                </div>
                <Button
                    type="submit"
                    size="icon"
                    disabled={processing}
                    aria-label="Speichern"
                >
                    <Check className="size-4" />
                </Button>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    onClick={() => setEditing(false)}
                    aria-label="Abbrechen"
                >
                    <X className="size-4" />
                </Button>
            </form>
        );
    }

    return (
        <div className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
            <span className="w-10 text-sm text-muted-foreground">
                {costType.sort_order}
            </span>
            <span className="flex-1 font-medium">
                {costType.name}
                {costType.archived && (
                    <Badge variant="secondary" className="ml-2">
                        archiviert
                    </Badge>
                )}
            </span>
            {canWrite && (
                <>
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setEditing(true)}
                        aria-label={`${costType.name} bearbeiten`}
                    >
                        <Pencil className="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={
                            costType.archived
                                ? `${costType.name} aktivieren`
                                : `${costType.name} archivieren`
                        }
                        onClick={() =>
                            router.patch(
                                `/cost-types/${costType.id}/archive`,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        {costType.archived ? (
                            <ArchiveRestore className="size-4" />
                        ) : (
                            <Archive className="size-4" />
                        )}
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`${costType.name} löschen`}
                        onClick={() => {
                            if (
                                confirm(
                                    `Kostenart „${costType.name}“ wirklich endgültig löschen?`,
                                )
                            ) {
                                router.delete(`/cost-types/${costType.id}`, {
                                    preserveScroll: true,
                                });
                            }
                        }}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </>
            )}
        </div>
    );
}

function AddCostTypeForm() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        sort_order: 0,
    });

    return (
        <form
            className="flex max-w-xl items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post('/cost-types', {
                    preserveScroll: true,
                    onSuccess: () => reset(),
                });
            }}
        >
            <div className="grid flex-1 gap-2">
                <Label htmlFor="new-name">Neue Kostenart</Label>
                <Input
                    id="new-name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="z. B. Material, Fremdleistung, Fuhrpark"
                    required
                />
                <InputError message={errors.name ?? errors.sort_order} />
            </div>
            <div className="grid w-28 gap-2">
                <Label htmlFor="new-sort">Reihenfolge</Label>
                <Input
                    id="new-sort"
                    type="number"
                    min={0}
                    value={data.sort_order}
                    onChange={(e) =>
                        setData('sort_order', Number(e.target.value))
                    }
                />
            </div>
            <Button type="submit" disabled={processing}>
                Hinzufügen
            </Button>
        </form>
    );
}

CostTypesIndex.layout = {
    breadcrumbs: [{ title: 'Kostenarten', href: '/cost-types' }],
};

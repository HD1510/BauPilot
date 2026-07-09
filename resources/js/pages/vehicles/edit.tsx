import { Head, router, useForm } from '@inertiajs/react';
import { Archive, ArchiveRestore, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    VehicleForm
    
} from '@/components/master-data/vehicle-form';
import type {VehicleFormValues} from '@/components/master-data/vehicle-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';

type VehicleDate = { id: number; label: string; due_on: string };

type Props = {
    vehicle: VehicleFormValues & { id: number; active: boolean };
    dates: VehicleDate[];
    canWrite: boolean;
};

export default function VehiclesEdit({ vehicle, dates, canWrite }: Props) {
    return (
        <>
            <Head title={`Fahrzeug: ${vehicle.plate}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={vehicle.plate}
                        description="Fahrzeugstammblatt mit Terminen"
                    />
                    {canWrite && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.patch(`/vehicles/${vehicle.id}/archive`)
                            }
                        >
                            {vehicle.active ? (
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

                {!vehicle.active && (
                    <Badge variant="secondary" className="w-fit">
                        Dieses Fahrzeug ist archiviert.
                    </Badge>
                )}

                <VehicleForm
                    action={`/vehicles/${vehicle.id}`}
                    method="patch"
                    vehicle={vehicle}
                    submitLabel="Änderungen speichern"
                    disabled={!canWrite || !vehicle.active}
                />

                <Separator />

                <Heading
                    variant="small"
                    title="Weitere Termine"
                    description="Service, Eichung, Kranprüfung — fließen später in die Fristenliste ein"
                />

                <div className="grid max-w-xl gap-2">
                    {dates.map((date) => (
                        <div
                            key={date.id}
                            className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                        >
                            <span className="flex-1 font-medium">
                                {date.label}
                            </span>
                            <span className="text-sm text-muted-foreground">
                                {new Date(date.due_on).toLocaleDateString(
                                    'de-AT',
                                )}
                            </span>
                            {canWrite && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`${date.label} entfernen`}
                                    onClick={() =>
                                        router.delete(
                                            `/vehicles/${vehicle.id}/dates/${date.id}`,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            )}
                        </div>
                    ))}
                    {dates.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Keine weiteren Termine.
                        </p>
                    )}
                </div>

                {canWrite && <AddDateForm vehicleId={vehicle.id} />}
            </div>
        </>
    );
}

function AddDateForm({ vehicleId }: { vehicleId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        label: '',
        due_on: '',
    });

    return (
        <form
            className="flex max-w-xl items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/vehicles/${vehicleId}/dates`, {
                    preserveScroll: true,
                    onSuccess: () => reset(),
                });
            }}
        >
            <div className="grid flex-1 gap-2">
                <Label htmlFor="date-label">Bezeichnung</Label>
                <Input
                    id="date-label"
                    value={data.label}
                    onChange={(e) => setData('label', e.target.value)}
                    placeholder="z. B. Service"
                    required
                />
                <InputError message={errors.label ?? errors.due_on} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="date-due">Fällig am</Label>
                <Input
                    id="date-due"
                    type="date"
                    value={data.due_on}
                    onChange={(e) => setData('due_on', e.target.value)}
                    required
                />
            </div>
            <Button type="submit" disabled={processing}>
                Hinzufügen
            </Button>
        </form>
    );
}

VehiclesEdit.layout = ({ vehicle }: Props) => ({
    breadcrumbs: [
        { title: 'Fahrzeuge', href: '/vehicles' },
        { title: vehicle.plate, href: `/vehicles/${vehicle.id}/edit` },
    ],
});

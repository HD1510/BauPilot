import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type VehicleFormValues = {
    plate: string;
    brand: string | null;
    model: string | null;
    inspection_due_on: string | null;
    vignette_until: string | null;
    fuel_card: string | null;
    notes: string | null;
    lock_version?: number;
};

export function VehicleForm({
    action,
    method,
    vehicle,
    submitLabel,
    disabled = false,
}: {
    action: string;
    method: 'post' | 'patch';
    vehicle?: VehicleFormValues;
    submitLabel: string;
    disabled?: boolean;
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        plate: vehicle?.plate ?? '',
        brand: vehicle?.brand ?? '',
        model: vehicle?.model ?? '',
        inspection_due_on: vehicle?.inspection_due_on ?? '',
        vignette_until: vehicle?.vignette_until ?? '',
        fuel_card: vehicle?.fuel_card ?? '',
        notes: vehicle?.notes ?? '',
        lock_version: vehicle?.lock_version ?? 0,
    });

    return (
        <form
            className="max-w-xl space-y-6"
            onSubmit={(event) => {
                event.preventDefault();
                (method === 'post' ? post : patch)(action, {
                    preserveScroll: true,
                });
            }}
        >
            <fieldset className="space-y-6" disabled={disabled || processing}>
                <div className="grid grid-cols-3 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="plate">Kennzeichen</Label>
                        <Input
                            id="plate"
                            value={data.plate}
                            onChange={(e) => setData('plate', e.target.value)}
                            required
                        />
                        <InputError message={errors.plate} />
                        <InputError message={errors.lock_version} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="brand">Marke</Label>
                        <Input
                            id="brand"
                            value={data.brand}
                            onChange={(e) => setData('brand', e.target.value)}
                        />
                        <InputError message={errors.brand} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="model">Modell</Label>
                        <Input
                            id="model"
                            value={data.model}
                            onChange={(e) => setData('model', e.target.value)}
                        />
                        <InputError message={errors.model} />
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="inspection_due_on">
                            Pickerl fällig am
                        </Label>
                        <Input
                            id="inspection_due_on"
                            type="date"
                            value={data.inspection_due_on}
                            onChange={(e) =>
                                setData('inspection_due_on', e.target.value)
                            }
                        />
                        <InputError message={errors.inspection_due_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="vignette_until">
                            Vignette gültig bis
                        </Label>
                        <Input
                            id="vignette_until"
                            type="date"
                            value={data.vignette_until}
                            onChange={(e) =>
                                setData('vignette_until', e.target.value)
                            }
                        />
                        <InputError message={errors.vignette_until} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="fuel_card">Tankkarte</Label>
                        <Input
                            id="fuel_card"
                            value={data.fuel_card}
                            onChange={(e) =>
                                setData('fuel_card', e.target.value)
                            }
                        />
                        <InputError message={errors.fuel_card} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="notes">Notizen</Label>
                    <Textarea
                        id="notes"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                    <InputError message={errors.notes} />
                </div>

                <Button type="submit">{submitLabel}</Button>
            </fieldset>
        </form>
    );
}

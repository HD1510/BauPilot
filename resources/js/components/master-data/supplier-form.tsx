import { useForm } from '@inertiajs/react';
import { DuplicatesWarning } from '@/components/master-data/duplicates-warning';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

export type SupplierFormValues = {
    name: string;
    short_code: string | null;
    payment_target_days: number;
    default_cost_type_id: number | null;
    skonto_percent: string | null;
    skonto_days: number | null;
    notes: string | null;
    lock_version?: number;
};

export type CostTypeOption = { id: number; name: string };

export function SupplierForm({
    action,
    method,
    supplier,
    costTypes,
    submitLabel,
    disabled = false,
}: {
    action: string;
    method: 'post' | 'patch';
    supplier?: SupplierFormValues;
    costTypes: CostTypeOption[];
    submitLabel: string;
    disabled?: boolean;
}) {
    const { data, setData, post, patch, processing, errors, transform } =
        useForm({
            name: supplier?.name ?? '',
            short_code: supplier?.short_code ?? '',
            payment_target_days: supplier?.payment_target_days ?? 30,
            default_cost_type_id: supplier?.default_cost_type_id
                ? String(supplier.default_cost_type_id)
                : 'none',
            skonto_percent: supplier?.skonto_percent ?? '',
            skonto_days: supplier?.skonto_days ?? '',
            notes: supplier?.notes ?? '',
            lock_version: supplier?.lock_version ?? 0,
        });

    const submit = (force = false) => {
        transform((values) => ({
            ...values,
            default_cost_type_id:
                values.default_cost_type_id === 'none'
                    ? null
                    : Number(values.default_cost_type_id),
            ...(force ? { force: true } : {}),
        }));
        (method === 'post' ? post : patch)(action, { preserveScroll: true });
    };

    return (
        <form
            className="max-w-xl space-y-6"
            onSubmit={(event) => {
                event.preventDefault();
                submit();
            }}
        >
            <fieldset className="space-y-6" disabled={disabled || processing}>
                <div className="grid grid-cols-3 gap-4">
                    <div className="col-span-2 grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <InputError message={errors.name} />
                        <InputError message={errors.lock_version} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="short_code">Kurzzeichen</Label>
                        <Input
                            id="short_code"
                            value={data.short_code}
                            onChange={(e) =>
                                setData('short_code', e.target.value)
                            }
                            maxLength={20}
                        />
                        <InputError message={errors.short_code} />
                    </div>
                </div>

                {method === 'post' && (
                    <DuplicatesWarning
                        editPath={(id) => `/suppliers/${id}/edit`}
                        onForce={() => submit(true)}
                        processing={processing}
                    />
                )}

                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="payment_target_days">
                            Zahlungsziel (Tage)
                        </Label>
                        <Input
                            id="payment_target_days"
                            type="number"
                            min={0}
                            max={365}
                            value={data.payment_target_days}
                            onChange={(e) =>
                                setData(
                                    'payment_target_days',
                                    Number(e.target.value),
                                )
                            }
                            required
                        />
                        <InputError message={errors.payment_target_days} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="default_cost_type_id">
                            Standard-Kostenart
                        </Label>
                        <Select
                            value={data.default_cost_type_id}
                            onValueChange={(value) =>
                                setData('default_cost_type_id', value)
                            }
                        >
                            <SelectTrigger id="default_cost_type_id">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">Keine</SelectItem>
                                {costTypes.map((costType) => (
                                    <SelectItem
                                        key={costType.id}
                                        value={String(costType.id)}
                                    >
                                        {costType.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.default_cost_type_id} />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="skonto_percent">Skonto (%)</Label>
                        <Input
                            id="skonto_percent"
                            type="number"
                            step="0.01"
                            min={0}
                            max={100}
                            value={data.skonto_percent}
                            onChange={(e) =>
                                setData('skonto_percent', e.target.value)
                            }
                        />
                        <InputError message={errors.skonto_percent} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="skonto_days">Skontofrist (Tage)</Label>
                        <Input
                            id="skonto_days"
                            type="number"
                            min={0}
                            max={365}
                            value={data.skonto_days}
                            onChange={(e) =>
                                setData('skonto_days', e.target.value)
                            }
                        />
                        <InputError message={errors.skonto_days} />
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

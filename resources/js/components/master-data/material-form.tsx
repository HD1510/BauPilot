import { useForm } from '@inertiajs/react';
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

export type MaterialFormValues = {
    supplier_id: number | null;
    article_no: string | null;
    name: string;
    price_net: string | null;
    package_unit: string | null;
    notes: string | null;
    lock_version?: number;
};

export type SupplierOption = { id: number; name: string };

export function MaterialForm({
    action,
    method,
    material,
    suppliers,
    submitLabel,
    disabled = false,
}: {
    action: string;
    method: 'post' | 'patch';
    material?: MaterialFormValues;
    suppliers: SupplierOption[];
    submitLabel: string;
    disabled?: boolean;
}) {
    const { data, setData, post, patch, processing, errors, transform } =
        useForm({
            supplier_id: material?.supplier_id
                ? String(material.supplier_id)
                : '',
            article_no: material?.article_no ?? '',
            name: material?.name ?? '',
            price_net: material?.price_net ?? '',
            package_unit: material?.package_unit ?? '',
            notes: material?.notes ?? '',
            lock_version: material?.lock_version ?? 0,
        });

    return (
        <form
            className="max-w-xl space-y-6"
            onSubmit={(event) => {
                event.preventDefault();
                transform((values) => ({
                    ...values,
                    supplier_id: values.supplier_id
                        ? Number(values.supplier_id)
                        : null,
                }));
                (method === 'post' ? post : patch)(action, {
                    preserveScroll: true,
                });
            }}
        >
            <fieldset className="space-y-6" disabled={disabled || processing}>
                <div className="grid gap-2">
                    <Label htmlFor="supplier_id">Lieferant</Label>
                    <Select
                        value={data.supplier_id}
                        onValueChange={(value) => setData('supplier_id', value)}
                    >
                        <SelectTrigger id="supplier_id">
                            <SelectValue placeholder="Lieferant wählen" />
                        </SelectTrigger>
                        <SelectContent>
                            {suppliers.map((supplier) => (
                                <SelectItem
                                    key={supplier.id}
                                    value={String(supplier.id)}
                                >
                                    {supplier.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.supplier_id} />
                    <InputError message={errors.lock_version} />
                </div>

                <div className="grid grid-cols-3 gap-4">
                    <div className="col-span-2 grid gap-2">
                        <Label htmlFor="name">Bezeichnung</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="article_no">Artikelnummer</Label>
                        <Input
                            id="article_no"
                            value={data.article_no}
                            onChange={(e) =>
                                setData('article_no', e.target.value)
                            }
                        />
                        <InputError message={errors.article_no} />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="price_net">Preis netto (€)</Label>
                        <Input
                            id="price_net"
                            type="number"
                            step="0.01"
                            min={0}
                            value={data.price_net}
                            onChange={(e) =>
                                setData('price_net', e.target.value)
                            }
                        />
                        <InputError message={errors.price_net} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="package_unit">Gebindeeinheit</Label>
                        <Input
                            id="package_unit"
                            value={data.package_unit}
                            onChange={(e) =>
                                setData('package_unit', e.target.value)
                            }
                            placeholder="z. B. Palette, m², Stk"
                        />
                        <InputError message={errors.package_unit} />
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

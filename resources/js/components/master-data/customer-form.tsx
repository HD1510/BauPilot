import { useForm } from '@inertiajs/react';
import { DuplicatesWarning } from '@/components/master-data/duplicates-warning';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type CustomerFormValues = {
    name: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    vat_id: string | null;
    payment_target_days: number;
    external_ref: string | null;
    notes: string | null;
    lock_version?: number;
};

export function CustomerForm({
    action,
    method,
    customer,
    submitLabel,
    disabled = false,
}: {
    action: string;
    method: 'post' | 'patch';
    customer?: CustomerFormValues;
    submitLabel: string;
    disabled?: boolean;
}) {
    const { data, setData, post, patch, processing, errors, transform } =
        useForm({
            name: customer?.name ?? '',
            address: customer?.address ?? '',
            phone: customer?.phone ?? '',
            email: customer?.email ?? '',
            vat_id: customer?.vat_id ?? '',
            payment_target_days: customer?.payment_target_days ?? 14,
            external_ref: customer?.external_ref ?? '',
            notes: customer?.notes ?? '',
            lock_version: customer?.lock_version ?? 0,
        });

    const submit = (force = false) => {
        transform((values) => (force ? { ...values, force: true } : values));
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
                <div className="grid gap-2">
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

                {method === 'post' && (
                    <DuplicatesWarning
                        editPath={(id) => `/customers/${id}/edit`}
                        onForce={() => submit(true)}
                        processing={processing}
                    />
                )}

                <div className="grid gap-2">
                    <Label htmlFor="address">Adresse</Label>
                    <Input
                        id="address"
                        value={data.address}
                        onChange={(e) => setData('address', e.target.value)}
                    />
                    <InputError message={errors.address} />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="phone">Telefon</Label>
                        <Input
                            id="phone"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                        />
                        <InputError message={errors.phone} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="email">E-Mail</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        <InputError message={errors.email} />
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="vat_id">UID-Nummer</Label>
                        <Input
                            id="vat_id"
                            value={data.vat_id}
                            onChange={(e) => setData('vat_id', e.target.value)}
                            placeholder="ATU…"
                        />
                        <InputError message={errors.vat_id} />
                    </div>
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
                        <Label htmlFor="external_ref">Externe Referenz</Label>
                        <Input
                            id="external_ref"
                            value={data.external_ref}
                            onChange={(e) =>
                                setData('external_ref', e.target.value)
                            }
                        />
                        <InputError message={errors.external_ref} />
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

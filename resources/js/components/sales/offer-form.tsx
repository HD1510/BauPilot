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

export type OfferFormValues = {
    customer_id: number | null;
    location: string | null;
    description: string | null;
    status: string;
    viewing_on: string | null;
    follow_up_on: string | null;
    offer_number: string | null;
    offer_amount_net: string | null;
    notes: string | null;
    lock_version?: number;
};

export type Option = { id: number; name: string };
export type StatusOption = { value: string; label: string };

export function OfferForm({
    action,
    method,
    offer,
    customers,
    statuses,
    submitLabel,
}: {
    action: string;
    method: 'post' | 'patch';
    offer?: OfferFormValues;
    customers: Option[];
    statuses: StatusOption[];
    submitLabel: string;
}) {
    const { data, setData, post, patch, processing, errors, transform } =
        useForm({
            customer_id: offer?.customer_id ? String(offer.customer_id) : '',
            location: offer?.location ?? '',
            description: offer?.description ?? '',
            status: offer?.status ?? 'inquiry',
            viewing_on: offer?.viewing_on ?? '',
            follow_up_on: offer?.follow_up_on ?? '',
            offer_number: offer?.offer_number ?? '',
            offer_amount_net: offer?.offer_amount_net ?? '',
            notes: offer?.notes ?? '',
            lock_version: offer?.lock_version ?? 0,
        });

    return (
        <form
            className="max-w-xl space-y-6"
            onSubmit={(event) => {
                event.preventDefault();
                transform((values) => ({
                    ...values,
                    customer_id: values.customer_id
                        ? Number(values.customer_id)
                        : null,
                }));
                (method === 'post' ? post : patch)(action, {
                    preserveScroll: true,
                });
            }}
        >
            <fieldset className="space-y-6" disabled={processing}>
                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="customer_id">Kunde</Label>
                        <Select
                            value={data.customer_id}
                            onValueChange={(value) =>
                                setData('customer_id', value)
                            }
                        >
                            <SelectTrigger id="customer_id">
                                <SelectValue placeholder="Kunde wählen" />
                            </SelectTrigger>
                            <SelectContent>
                                {customers.map((customer) => (
                                    <SelectItem
                                        key={customer.id}
                                        value={String(customer.id)}
                                    >
                                        {customer.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={errors.customer_id ?? errors.lock_version}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <Select
                            value={data.status}
                            onValueChange={(value) => setData('status', value)}
                        >
                            <SelectTrigger id="status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statuses.map((status) => (
                                    <SelectItem
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="location">Ort/Baustelle</Label>
                    <Input
                        id="location"
                        value={data.location}
                        onChange={(e) => setData('location', e.target.value)}
                    />
                    <InputError message={errors.location} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="description">Beschreibung</Label>
                    <Textarea
                        id="description"
                        value={data.description}
                        onChange={(e) =>
                            setData('description', e.target.value)
                        }
                    />
                    <InputError message={errors.description} />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="viewing_on">Besichtigung am</Label>
                        <Input
                            id="viewing_on"
                            type="date"
                            value={data.viewing_on}
                            onChange={(e) =>
                                setData('viewing_on', e.target.value)
                            }
                        />
                        <InputError message={errors.viewing_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="follow_up_on">Wiedervorlage am</Label>
                        <Input
                            id="follow_up_on"
                            type="date"
                            value={data.follow_up_on}
                            onChange={(e) =>
                                setData('follow_up_on', e.target.value)
                            }
                        />
                        <InputError message={errors.follow_up_on} />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="offer_number">Angebotsnummer</Label>
                        <Input
                            id="offer_number"
                            value={data.offer_number}
                            onChange={(e) =>
                                setData('offer_number', e.target.value)
                            }
                        />
                        <InputError message={errors.offer_number} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="offer_amount_net">
                            Angebotssumme netto (€)
                        </Label>
                        <Input
                            id="offer_amount_net"
                            type="number"
                            step="0.01"
                            min={0}
                            value={data.offer_amount_net}
                            onChange={(e) =>
                                setData('offer_amount_net', e.target.value)
                            }
                        />
                        <InputError message={errors.offer_amount_net} />
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

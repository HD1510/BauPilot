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

export type ProjectFormValues = {
    customer_id: number | null;
    title: string;
    site_address: string | null;
    description: string | null;
    responsible_user_id: number | null;
    commissioned_on: string | null;
    started_on: string | null;
    planned_finish_on: string | null;
    finished_on: string | null;
    status: string;
    warranty_until: string | null;
    notes: string | null;
    lock_version?: number;
};

export type Option = { id: number; name: string };
export type StatusOption = { value: string; label: string };

export function ProjectForm({
    action,
    method,
    project,
    customers,
    users,
    statuses,
    submitLabel,
}: {
    action: string;
    method: 'post' | 'patch';
    project?: ProjectFormValues;
    customers: Option[];
    users: Option[];
    statuses: StatusOption[];
    submitLabel: string;
}) {
    const { data, setData, post, patch, processing, errors, transform } =
        useForm({
            customer_id: project?.customer_id
                ? String(project.customer_id)
                : '',
            title: project?.title ?? '',
            site_address: project?.site_address ?? '',
            description: project?.description ?? '',
            responsible_user_id: project?.responsible_user_id
                ? String(project.responsible_user_id)
                : 'none',
            commissioned_on: project?.commissioned_on ?? '',
            started_on: project?.started_on ?? '',
            planned_finish_on: project?.planned_finish_on ?? '',
            finished_on: project?.finished_on ?? '',
            status: project?.status ?? 'open',
            warranty_until: project?.warranty_until ?? '',
            notes: project?.notes ?? '',
            lock_version: project?.lock_version ?? 0,
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
                    responsible_user_id:
                        values.responsible_user_id === 'none'
                            ? null
                            : Number(values.responsible_user_id),
                }));
                (method === 'post' ? post : patch)(action, {
                    preserveScroll: true,
                });
            }}
        >
            <fieldset className="space-y-6" disabled={processing}>
                <div className="grid gap-2">
                    <Label htmlFor="title">Titel</Label>
                    <Input
                        id="title"
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        required
                    />
                    <InputError message={errors.title ?? errors.lock_version} />
                </div>

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
                        <InputError message={errors.customer_id} />
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
                    <Label htmlFor="site_address">Baustellenadresse</Label>
                    <Input
                        id="site_address"
                        value={data.site_address}
                        onChange={(e) =>
                            setData('site_address', e.target.value)
                        }
                    />
                    <InputError message={errors.site_address} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="responsible_user_id">Verantwortlich</Label>
                    <Select
                        value={data.responsible_user_id}
                        onValueChange={(value) =>
                            setData('responsible_user_id', value)
                        }
                    >
                        <SelectTrigger id="responsible_user_id">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Niemand</SelectItem>
                            {users.map((user) => (
                                <SelectItem
                                    key={user.id}
                                    value={String(user.id)}
                                >
                                    {user.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.responsible_user_id} />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    {(
                        [
                            ['commissioned_on', 'Beauftragt am'],
                            ['started_on', 'Begonnen am'],
                            ['planned_finish_on', 'Geplantes Ende'],
                            ['finished_on', 'Fertiggestellt am'],
                            ['warranty_until', 'Gewährleistung bis'],
                        ] as const
                    ).map(([field, label]) => (
                        <div key={field} className="grid gap-2">
                            <Label htmlFor={field}>{label}</Label>
                            <Input
                                id={field}
                                type="date"
                                value={data[field]}
                                onChange={(e) =>
                                    setData(field, e.target.value)
                                }
                            />
                            <InputError message={errors[field]} />
                        </div>
                    ))}
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

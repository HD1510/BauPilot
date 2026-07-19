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

export type EmployeeFormValues = {
    name: string;
    address: string | null;
    birth_date: string | null;
    started_on: string | null;
    ended_on: string | null;
    social_security_number: string | null;
    iban: string | null;
    overtime_rate: string | null;
    calc_hourly_rate: string | null;
    user_id: number | null;
    notes: string | null;
    lock_version?: number;
};

export type UserOption = { id: number; name: string; login: string };

export function EmployeeForm({
    action,
    method,
    employee,
    users,
    submitLabel,
    disabled = false,
}: {
    action: string;
    method: 'post' | 'patch';
    employee?: EmployeeFormValues;
    users: UserOption[];
    submitLabel: string;
    disabled?: boolean;
}) {
    const { data, setData, post, patch, processing, errors, transform } =
        useForm({
            name: employee?.name ?? '',
            address: employee?.address ?? '',
            birth_date: employee?.birth_date ?? '',
            started_on: employee?.started_on ?? '',
            ended_on: employee?.ended_on ?? '',
            social_security_number: employee?.social_security_number ?? '',
            iban: employee?.iban ?? '',
            overtime_rate: employee?.overtime_rate ?? '',
            calc_hourly_rate: employee?.calc_hourly_rate ?? '',
            user_id: employee?.user_id ? String(employee.user_id) : 'none',
            notes: employee?.notes ?? '',
            lock_version: employee?.lock_version ?? 0,
        });

    return (
        <form
            className="max-w-xl space-y-6"
            onSubmit={(event) => {
                event.preventDefault();
                transform((values) => ({
                    ...values,
                    user_id:
                        values.user_id === 'none'
                            ? null
                            : Number(values.user_id),
                }));
                (method === 'post' ? post : patch)(action, {
                    preserveScroll: true,
                });
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

                <div className="grid gap-2">
                    <Label htmlFor="address">Adresse</Label>
                    <Input
                        id="address"
                        value={data.address}
                        onChange={(e) => setData('address', e.target.value)}
                        placeholder="Straße, PLZ Ort"
                    />
                    <InputError message={errors.address} />
                </div>

                <div className="grid grid-cols-3 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="birth_date">Geburtsdatum</Label>
                        <Input
                            id="birth_date"
                            type="date"
                            value={data.birth_date}
                            onChange={(e) =>
                                setData('birth_date', e.target.value)
                            }
                        />
                        <InputError message={errors.birth_date} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="started_on">Eintritt</Label>
                        <Input
                            id="started_on"
                            type="date"
                            value={data.started_on}
                            onChange={(e) =>
                                setData('started_on', e.target.value)
                            }
                        />
                        <InputError message={errors.started_on} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="ended_on">Austritt</Label>
                        <Input
                            id="ended_on"
                            type="date"
                            value={data.ended_on}
                            onChange={(e) =>
                                setData('ended_on', e.target.value)
                            }
                        />
                        <InputError message={errors.ended_on} />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="social_security_number">
                            SV-Nummer
                        </Label>
                        <Input
                            id="social_security_number"
                            value={data.social_security_number}
                            onChange={(e) =>
                                setData(
                                    'social_security_number',
                                    e.target.value,
                                )
                            }
                            placeholder="z. B. 1234 010190"
                        />
                        <InputError message={errors.social_security_number} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="iban">IBAN</Label>
                        <Input
                            id="iban"
                            value={data.iban}
                            onChange={(e) => setData('iban', e.target.value)}
                            placeholder="AT.."
                        />
                        <InputError message={errors.iban} />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="overtime_rate">
                            Überstundensatz (€)
                        </Label>
                        <Input
                            id="overtime_rate"
                            type="number"
                            step="0.01"
                            min={0}
                            value={data.overtime_rate}
                            onChange={(e) =>
                                setData('overtime_rate', e.target.value)
                            }
                        />
                        <InputError message={errors.overtime_rate} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="calc_hourly_rate">
                            Kalk. Stundensatz (€)
                        </Label>
                        <Input
                            id="calc_hourly_rate"
                            type="number"
                            step="0.01"
                            min={0}
                            value={data.calc_hourly_rate}
                            onChange={(e) =>
                                setData('calc_hourly_rate', e.target.value)
                            }
                            placeholder="leer = Firmenwert"
                        />
                        <InputError message={errors.calc_hourly_rate} />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="user_id">Benutzerkonto (optional)</Label>
                    <Select
                        value={data.user_id}
                        onValueChange={(value) => setData('user_id', value)}
                    >
                        <SelectTrigger id="user_id">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">
                                Kein Benutzerkonto
                            </SelectItem>
                            {users.map((user) => (
                                <SelectItem
                                    key={user.id}
                                    value={String(user.id)}
                                >
                                    {user.name} ({user.login})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.user_id} />
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

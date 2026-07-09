import { Form } from '@inertiajs/react';
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

const months = [
    'Jänner',
    'Februar',
    'März',
    'April',
    'Mai',
    'Juni',
    'Juli',
    'August',
    'September',
    'Oktober',
    'November',
    'Dezember',
];

export type CompanyFormValues = {
    name: string;
    short_code: string;
    color: string;
    legal_form: string | null;
    address: string | null;
    vat_id: string | null;
    fiscal_year_start_month: number;
    calc_hourly_rate: string | null;
    warranty_years: number;
    lock_version?: number;
};

export function CompanyForm({
    action,
    method,
    company,
    submitLabel,
}: {
    action: string;
    method: 'post' | 'patch';
    company?: CompanyFormValues;
    submitLabel: string;
}) {
    return (
        <Form
            action={action}
            method={method}
            options={{ preserveScroll: true }}
            className="max-w-xl space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    {company?.lock_version !== undefined && (
                        <input
                            type="hidden"
                            name="lock_version"
                            value={company.lock_version}
                        />
                    )}
                    <InputError message={errors.lock_version} />

                    <div className="grid gap-2">
                        <Label htmlFor="name">Firmenname</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={company?.name}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="short_code">Kurzzeichen</Label>
                            <Input
                                id="short_code"
                                name="short_code"
                                maxLength={8}
                                defaultValue={company?.short_code}
                                placeholder="z. B. GMBH"
                                required
                            />
                            <InputError message={errors.short_code} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="color">Kennfarbe</Label>
                            <Input
                                id="color"
                                name="color"
                                type="color"
                                defaultValue={company?.color ?? '#2563eb'}
                                className="h-9 w-20 p-1"
                                required
                            />
                            <InputError message={errors.color} />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="legal_form">Rechtsform</Label>
                            <Input
                                id="legal_form"
                                name="legal_form"
                                defaultValue={company?.legal_form ?? ''}
                                placeholder="z. B. GmbH"
                            />
                            <InputError message={errors.legal_form} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="vat_id">UID-Nummer</Label>
                            <Input
                                id="vat_id"
                                name="vat_id"
                                defaultValue={company?.vat_id ?? ''}
                                placeholder="ATU…"
                            />
                            <InputError message={errors.vat_id} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="address">Adresse</Label>
                        <Input
                            id="address"
                            name="address"
                            defaultValue={company?.address ?? ''}
                        />
                        <InputError message={errors.address} />
                    </div>

                    <div className="grid grid-cols-3 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="fiscal_year_start_month">
                                GJ-Beginn
                            </Label>
                            <Select
                                name="fiscal_year_start_month"
                                defaultValue={String(
                                    company?.fiscal_year_start_month ?? 9,
                                )}
                            >
                                <SelectTrigger id="fiscal_year_start_month">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {months.map((label, index) => (
                                        <SelectItem
                                            key={label}
                                            value={String(index + 1)}
                                        >
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={errors.fiscal_year_start_month}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="calc_hourly_rate">
                                Kalk. Stundensatz (€)
                            </Label>
                            <Input
                                id="calc_hourly_rate"
                                name="calc_hourly_rate"
                                type="number"
                                step="0.01"
                                min="0"
                                defaultValue={company?.calc_hourly_rate ?? ''}
                            />
                            <InputError message={errors.calc_hourly_rate} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="warranty_years">
                                Gewährleistung (Jahre)
                            </Label>
                            <Input
                                id="warranty_years"
                                name="warranty_years"
                                type="number"
                                min="0"
                                max="30"
                                defaultValue={company?.warranty_years ?? 3}
                                required
                            />
                            <InputError message={errors.warranty_years} />
                        </div>
                    </div>

                    <Button type="submit" disabled={processing}>
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}

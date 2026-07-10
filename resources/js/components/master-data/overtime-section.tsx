import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatEUR } from '@/lib/format';

export type OvertimeData = {
    entries: {
        id: number;
        year: number;
        month: number;
        hours: number;
        note: string | null;
    }[];
    payouts: {
        id: number;
        paid_on: string;
        hours: number;
        amount: number;
    }[];
    balance: { accrued: number; paid_out: number; balance: number };
};

const formatHours = (hours: number) =>
    `${hours.toLocaleString('de-AT', { maximumFractionDigits: 2 })} h`;

/**
 * Überstunden je Monat und Auszahlungen (M8, Lohndaten): der Saldo wird
 * nie gespeichert, sondern immer aus Einträgen minus Auszahlungen
 * gerechnet. Ein Eintrag je Monat; erneutes Erfassen überschreibt.
 */
export function OvertimeSection({
    employeeId,
    overtime,
    canWrite,
}: {
    employeeId: number;
    overtime: OvertimeData;
    canWrite: boolean;
}) {
    return (
        <div className="grid max-w-xl gap-4">
            <Heading
                variant="small"
                title="Überstunden"
                description="Monatssalden und Auszahlungen — der Saldo wird immer gerechnet"
            />

            <div className="flex flex-wrap gap-3">
                <Stat label="Aufgebaut" value={formatHours(overtime.balance.accrued)} />
                <Stat label="Ausgezahlt" value={formatHours(overtime.balance.paid_out)} />
                <Stat
                    label="Saldo"
                    value={formatHours(overtime.balance.balance)}
                    highlight={overtime.balance.balance !== 0}
                />
            </div>

            <div className="grid gap-2">
                {overtime.entries.map((entry) => (
                    <div
                        key={entry.id}
                        className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
                    >
                        <Badge variant="outline">
                            {String(entry.month).padStart(2, '0')}/{entry.year}
                        </Badge>
                        <span className="font-medium">{formatHours(entry.hours)}</span>
                        <span className="flex-1 text-muted-foreground">{entry.note}</span>
                        {canWrite && (
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={`Eintrag ${entry.month}/${entry.year} löschen`}
                                onClick={() =>
                                    router.delete(
                                        `/employees/${employeeId}/overtime-entries/${entry.id}`,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        )}
                    </div>
                ))}
                {overtime.entries.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Noch keine Monatseinträge.
                    </p>
                )}
            </div>
            {canWrite && <AddEntryForm employeeId={employeeId} />}

            <Heading
                variant="small"
                title="Auszahlungen"
                description="Ziehen Stunden vom Saldo ab"
            />
            <div className="grid gap-2">
                {overtime.payouts.map((payout) => (
                    <div
                        key={payout.id}
                        className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
                    >
                        <Badge variant="outline">{formatDate(payout.paid_on)}</Badge>
                        <span className="font-medium">{formatHours(payout.hours)}</span>
                        <span className="flex-1 text-muted-foreground">
                            {formatEUR(payout.amount)}
                        </span>
                        {canWrite && (
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="Auszahlung löschen"
                                onClick={() =>
                                    router.delete(
                                        `/employees/${employeeId}/overtime-payouts/${payout.id}`,
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        )}
                    </div>
                ))}
                {overtime.payouts.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Noch keine Auszahlungen.
                    </p>
                )}
            </div>
            {canWrite && <AddPayoutForm employeeId={employeeId} />}
        </div>
    );
}

function Stat({
    label,
    value,
    highlight = false,
}: {
    label: string;
    value: string;
    highlight?: boolean;
}) {
    return (
        <div className="rounded-lg border border-sidebar-border/70 px-4 py-2 dark:border-sidebar-border">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className={highlight ? 'text-lg font-semibold' : 'text-lg'}>
                {value}
            </div>
        </div>
    );
}

function AddEntryForm({ employeeId }: { employeeId: number }) {
    const now = new Date();
    const { data, setData, post, processing, errors, reset } = useForm({
        year: String(now.getFullYear()),
        month: String(now.getMonth() + 1),
        hours: '',
        note: '',
    });

    return (
        <form
            className="flex flex-wrap items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/employees/${employeeId}/overtime-entries`, {
                    preserveScroll: true,
                    onSuccess: () => reset('hours', 'note'),
                });
            }}
        >
            <div className="grid w-24 gap-1">
                <Label htmlFor="overtime-month">Monat</Label>
                <Input
                    id="overtime-month"
                    type="number"
                    min={1}
                    max={12}
                    value={data.month}
                    onChange={(e) => setData('month', e.target.value)}
                    required
                />
            </div>
            <div className="grid w-28 gap-1">
                <Label htmlFor="overtime-year">Jahr</Label>
                <Input
                    id="overtime-year"
                    type="number"
                    min={2000}
                    max={2100}
                    value={data.year}
                    onChange={(e) => setData('year', e.target.value)}
                    required
                />
            </div>
            <div className="grid w-28 gap-1">
                <Label htmlFor="overtime-hours">Stunden (±)</Label>
                <Input
                    id="overtime-hours"
                    type="number"
                    step="0.25"
                    value={data.hours}
                    onChange={(e) => setData('hours', e.target.value)}
                    required
                />
            </div>
            <div className="grid flex-1 gap-1">
                <Label htmlFor="overtime-note">Notiz</Label>
                <Input
                    id="overtime-note"
                    value={data.note}
                    onChange={(e) => setData('note', e.target.value)}
                />
            </div>
            <Button type="submit" disabled={processing}>
                Speichern
            </Button>
            <InputError
                message={errors.hours ?? errors.month ?? errors.year}
            />
        </form>
    );
}

function AddPayoutForm({ employeeId }: { employeeId: number }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        paid_on: new Date().toISOString().slice(0, 10),
        hours: '',
        amount: '',
    });

    return (
        <form
            className="flex flex-wrap items-end gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/employees/${employeeId}/overtime-payouts`, {
                    preserveScroll: true,
                    onSuccess: () => reset('hours', 'amount'),
                });
            }}
        >
            <div className="grid w-40 gap-1">
                <Label htmlFor="payout-date">Ausgezahlt am</Label>
                <Input
                    id="payout-date"
                    type="date"
                    value={data.paid_on}
                    onChange={(e) => setData('paid_on', e.target.value)}
                    required
                />
            </div>
            <div className="grid w-28 gap-1">
                <Label htmlFor="payout-hours">Stunden</Label>
                <Input
                    id="payout-hours"
                    type="number"
                    step="0.25"
                    min={0.25}
                    value={data.hours}
                    onChange={(e) => setData('hours', e.target.value)}
                    required
                />
            </div>
            <div className="grid w-32 gap-1">
                <Label htmlFor="payout-amount">Betrag (€)</Label>
                <Input
                    id="payout-amount"
                    type="number"
                    step="0.01"
                    min={0}
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
                    required
                />
            </div>
            <Button type="submit" disabled={processing}>
                Auszahlen
            </Button>
            <InputError message={errors.hours ?? errors.amount ?? errors.paid_on} />
        </form>
    );
}

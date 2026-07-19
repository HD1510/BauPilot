import { UserPlus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { xsrfToken } from '@/lib/offline-queue';

export type CreatedPartner = {
    id: number;
    name: string;
    payment_target_days: number;
    default_cost_type_id: number | null;
};

/**
 * Wählt den frisch angelegten Partner erst einen Render-Zyklus nach dem
 * Einfügen in die Liste aus. Radix' verstecktes natives <select> kennt
 * die neue Option erst dann — wird der Wert im selben Zyklus gesetzt,
 * meldet es sofort onValueChange("") zurück und löscht die Auswahl.
 */
export function useSelectCreatedPartner(select: (id: string) => void) {
    const pendingIdRef = useRef<string | null>(null);
    const [pendingTick, setPendingTick] = useState(0);

    useEffect(() => {
        const id = pendingIdRef.current;

        if (id === null) {
            return;
        }

        pendingIdRef.current = null;
        select(id);
    }, [pendingTick, select]);

    return (id: string) => {
        pendingIdRef.current = id;
        setPendingTick((tick) => tick + 1);
    };
}

/**
 * Lieferant oder Kunde direkt aus der Maske anlegen — ohne
 * Seitenwechsel. Nur die nötigsten Felder; gepflegt wird später in den
 * Stammdaten. Der neue Partner ist danach sofort im Formular gewählt.
 */
export function NewPartnerDialog({
    kind,
    onCreated,
}: {
    kind: 'supplier' | 'customer';
    onCreated: (partner: CreatedPartner) => void;
}) {
    const label = kind === 'supplier' ? 'Lieferant' : 'Kunde';
    const [open, setOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [name, setName] = useState('');
    const [targetDays, setTargetDays] = useState(
        kind === 'supplier' ? '30' : '14',
    );
    const [vatId, setVatId] = useState('');

    const submit = async () => {
        if (name.trim() === '') {
            setError('Bitte einen Namen eingeben.');

            return;
        }

        setSaving(true);
        setError(null);

        try {
            const response = await fetch(`/partners/${kind}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    name: name.trim(),
                    payment_target_days: Number(targetDays) || 0,
                    ...(kind === 'customer' && vatId.trim() !== ''
                        ? { vat_id: vatId.trim() }
                        : {}),
                }),
            });

            const json = (await response.json().catch(() => null)) as
                (CreatedPartner & { message?: string }) | null;

            if (!response.ok || !json) {
                setError(
                    json?.message ??
                        `Der ${label} konnte nicht angelegt werden.`,
                );

                return;
            }

            toast.success(`${label} „${json.name}" angelegt.`);
            onCreated(json);
            setOpen(false);
            setName('');
            setVatId('');
        } catch {
            setError('Keine Verbindung — bitte erneut versuchen.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={`Neuen ${label}n anlegen`}
                    title={`Neuen ${label}n anlegen`}
                >
                    <UserPlus className="size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Neuen {label}n anlegen</DialogTitle>
                    <DialogDescription>
                        Nur das Nötigste — alles Weitere später in den
                        Stammdaten.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor={`new-${kind}-name`}>Name</Label>
                        <Input
                            id={`new-${kind}-name`}
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    void submit();
                                }
                            }}
                            autoFocus
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`new-${kind}-target`}>
                            Zahlungsziel (Tage)
                        </Label>
                        <Input
                            id={`new-${kind}-target`}
                            type="number"
                            min={0}
                            max={365}
                            value={targetDays}
                            onChange={(e) => setTargetDays(e.target.value)}
                        />
                    </div>
                    {kind === 'customer' && (
                        <div className="grid gap-2">
                            <Label htmlFor="new-customer-vat">
                                UID-Nummer (optional)
                            </Label>
                            <Input
                                id="new-customer-vat"
                                value={vatId}
                                onChange={(e) => setVatId(e.target.value)}
                                placeholder="ATU12345678"
                            />
                        </div>
                    )}
                    {error && (
                        <p className="text-sm text-red-600 dark:text-red-400">
                            {error}
                        </p>
                    )}
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        disabled={saving}
                        onClick={() => void submit()}
                    >
                        {label} anlegen
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

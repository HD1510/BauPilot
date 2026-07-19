import { router } from '@inertiajs/react';
import { ScanSearch, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { xsrfToken } from '@/lib/offline-queue';

type SupplierOption = { id: number; name: string };

type ScannedRow = {
    selected: boolean;
    name: string;
    article_no: string;
    package_unit: string;
    price_net: string;
    exists: boolean;
};

const sourceLabels: Record<string, string> = {
    e_rechnung: 'E-Rechnung gelesen',
    text: 'Texterkennung',
    ki: 'KI-Erkennung',
};

/**
 * Preisliste/Rechnung einlesen: Die erkannten Posten erscheinen als
 * Übersicht — je Zeile entscheidet der Mensch per Häkchen, ob daraus
 * ein Artikel wird. Bereits vorhandene Artikel sind abgewählt.
 */
export function MaterialScanCard({
    suppliers,
    imagesEnabled,
}: {
    suppliers: SupplierOption[];
    imagesEnabled: boolean;
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [busy, setBusy] = useState(false);
    const [saving, setSaving] = useState(false);
    const [source, setSource] = useState<string | null>(null);
    const [rows, setRows] = useState<ScannedRow[]>([]);
    const [supplierId, setSupplierId] = useState('');

    const scan = async () => {
        const file = fileInput.current?.files?.[0];

        if (!file) {
            return;
        }

        setBusy(true);

        try {
            const body = new FormData();
            body.append('file', file);

            const response = await fetch('/materials/scan', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body,
            });

            const json = (await response.json().catch(() => null)) as {
                message?: string;
                source?: string;
                items?: {
                    name: string;
                    article_no: string | null;
                    package_unit: string | null;
                    price_net: number | null;
                    exists: boolean;
                }[];
            } | null;

            if (!response.ok || !json?.items) {
                toast.error(
                    json?.message ?? 'Die Datei konnte nicht gelesen werden.',
                );

                return;
            }

            setSource(json.source ?? null);
            setRows(
                json.items.map((item) => ({
                    selected: !item.exists,
                    name: item.name,
                    article_no: item.article_no ?? '',
                    package_unit: item.package_unit ?? '',
                    price_net:
                        item.price_net === null ? '' : String(item.price_net),
                    exists: item.exists,
                })),
            );

            if (json.items.length === 0) {
                toast.info('Keine Artikelzeilen erkannt.');
            }
        } catch {
            toast.error('Keine Verbindung — bitte erneut versuchen.');
        } finally {
            setBusy(false);
        }
    };

    const update = (index: number, patch: Partial<ScannedRow>) => {
        setRows((current) =>
            current.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );
    };

    const selectedRows = rows.filter(
        (row) => row.selected && row.name.trim() !== '',
    );

    const submit = () => {
        setSaving(true);
        router.post(
            '/materials/import',
            {
                supplier_id: Number(supplierId),
                items: selectedRows.map((row) => ({
                    name: row.name.trim(),
                    article_no: row.article_no.trim() || null,
                    package_unit: row.package_unit.trim() || null,
                    price_net: row.price_net === '' ? null : row.price_net,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRows([]);
                    setSource(null);

                    if (fileInput.current) {
                        fileInput.current.value = '';
                    }
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="grid gap-3 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div className="flex items-start gap-3">
                <ScanSearch className="mt-1 size-5 shrink-0 text-muted-foreground" />
                <Heading
                    variant="small"
                    title="Preisliste oder Rechnung einlesen"
                    description={
                        imagesEnabled
                            ? 'PDF oder Foto hochladen — die einzelnen Posten werden erkannt, je Zeile entscheiden Sie, ob ein Artikel entsteht'
                            : 'PDF hochladen — die einzelnen Posten werden erkannt, je Zeile entscheiden Sie, ob ein Artikel entsteht (Fotos erst mit KI-Schlüssel)'
                    }
                />
            </div>

            <div className="flex items-end gap-3">
                <div className="grid flex-1 gap-2">
                    <Label htmlFor="material-scan-file">
                        Datei (max. 20 MB)
                    </Label>
                    <Input
                        id="material-scan-file"
                        ref={fileInput}
                        type="file"
                        accept={
                            imagesEnabled
                                ? '.pdf,image/jpeg,image/png,image/webp'
                                : '.pdf'
                        }
                    />
                </div>
                <Button
                    type="button"
                    variant="outline"
                    disabled={busy}
                    onClick={() => void scan()}
                >
                    {busy ? <Spinner /> : <ScanSearch className="size-4" />}
                    Posten erkennen
                </Button>
            </div>

            {rows.length > 0 && (
                <div className="grid gap-3">
                    <div className="flex items-center gap-3">
                        {source && sourceLabels[source] && (
                            <Badge variant="secondary">
                                {sourceLabels[source]}
                            </Badge>
                        )}
                        <span className="text-sm text-muted-foreground">
                            {rows.length} Posten erkannt — {selectedRows.length}{' '}
                            zum Anlegen gewählt
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                setRows((current) => {
                                    const all = current.every(
                                        (row) => row.selected,
                                    );

                                    return current.map((row) => ({
                                        ...row,
                                        selected: !all,
                                    }));
                                })
                            }
                        >
                            Alle an/abwählen
                        </Button>
                    </div>

                    <div className="grid gap-2">
                        {rows.map((row, index) => (
                            <div
                                key={index}
                                className="flex flex-wrap items-center gap-2 rounded-lg border border-sidebar-border/70 p-2 dark:border-sidebar-border"
                            >
                                <Checkbox
                                    checked={row.selected}
                                    onCheckedChange={(checked) =>
                                        update(index, {
                                            selected: checked === true,
                                        })
                                    }
                                    aria-label={`${row.name} anlegen`}
                                />
                                <Input
                                    className="w-28"
                                    value={row.article_no}
                                    onChange={(e) =>
                                        update(index, {
                                            article_no: e.target.value,
                                        })
                                    }
                                    placeholder="Art.-Nr."
                                    aria-label="Artikelnummer"
                                />
                                <Input
                                    className="min-w-40 flex-1"
                                    value={row.name}
                                    onChange={(e) =>
                                        update(index, { name: e.target.value })
                                    }
                                    placeholder="Bezeichnung"
                                    aria-label="Bezeichnung"
                                />
                                <Input
                                    className="w-20"
                                    value={row.package_unit}
                                    onChange={(e) =>
                                        update(index, {
                                            package_unit: e.target.value,
                                        })
                                    }
                                    placeholder="Einheit"
                                    aria-label="Einheit"
                                />
                                <Input
                                    className="w-28"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={row.price_net}
                                    onChange={(e) =>
                                        update(index, {
                                            price_net: e.target.value,
                                        })
                                    }
                                    placeholder="€ netto"
                                    aria-label="Preis netto"
                                />
                                {row.exists && (
                                    <Badge variant="secondary">
                                        bereits vorhanden
                                    </Badge>
                                )}
                            </div>
                        ))}
                    </div>

                    <div className="flex items-end gap-3">
                        <div className="grid gap-2">
                            <Label id="material-scan-supplier-label">
                                Lieferant für die neuen Artikel
                            </Label>
                            <Select
                                value={supplierId}
                                onValueChange={setSupplierId}
                            >
                                <SelectTrigger
                                    className="w-64"
                                    aria-labelledby="material-scan-supplier-label"
                                >
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
                        </div>
                        <Button
                            type="button"
                            disabled={
                                saving ||
                                supplierId === '' ||
                                selectedRows.length === 0
                            }
                            onClick={submit}
                        >
                            {saving ? (
                                <Spinner />
                            ) : (
                                <Upload className="size-4" />
                            )}
                            {selectedRows.length === 1
                                ? '1 Artikel anlegen'
                                : `${selectedRows.length} Artikel anlegen`}
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

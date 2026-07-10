import { FileUp, FolderDown, ScanText, UserPlus } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { saveFileAs, suggestedFileName } from '@/lib/save-file';

export type ScanMatch = {
    id: number;
    name: string;
    similarity: number;
    payment_target_days: number;
    default_cost_type_id: number | null;
    skonto_percent: string | null;
    skonto_days: number | null;
};

export type ScanPrefill = {
    supplier_invoice_no: string | null;
    invoice_date: string | null;
    amount_mode: 'net' | 'gross';
    amount: string | null;
    vat_rate: string | null;
    reverse_charge: boolean;
    subject: string | null;
    payment_due_on: string | null;
    skonto_amount: string | null;
    skonto_until: string | null;
};

export type ScanResult = {
    scan_token: string;
    source: 'e_rechnung' | 'text' | 'ki';
    extraction: {
        supplier_name: string | null;
        payment_target_days: number | null;
        skonto_percent: number | null;
        skonto_days: number | null;
    };
    matches: ScanMatch[];
    supplier_proposal: {
        name: string | null;
        payment_target_days: number;
        skonto_percent: number | null;
        skonto_days: number | null;
        notes: string | null;
    };
    prefill: ScanPrefill;
};

export type ChosenSupplier = {
    id: number;
    name: string;
    default_cost_type_id: number | null;
};

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

const SOURCE_LABELS: Record<ScanResult['source'], string> = {
    e_rechnung: 'aus E-Rechnung (ZUGFeRD) — exakt',
    text: 'aus Textanalyse',
    ki: 'per KI',
};

/**
 * Rechnungsscan: PDF/Foto hochladen, die Scan-Leiter (E-Rechnung →
 * Textanalyse → KI) erkennt Lieferant samt Konditionen. Bestehende
 * Lieferanten werden zur Auswahl vorgeschlagen; gibt es keinen, wird
 * einer mit den erkannten Konditionen angelegt. Die Auswahl bestätigt
 * immer ein Mensch. Die Datei lässt sich zusätzlich lokal ablegen —
 * der Speicherort ist frei wählbar.
 */
export function InvoiceScanCard({
    onApply,
    imagesEnabled,
}: {
    onApply: (result: ScanResult, supplier: ChosenSupplier | null) => void;
    imagesEnabled: boolean;
}) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [scanning, setScanning] = useState(false);
    const [creating, setCreating] = useState(false);
    const [result, setResult] = useState<ScanResult | null>(null);
    const [chosen, setChosen] = useState<string | null>(null);
    const [lastFile, setLastFile] = useState<File | null>(null);

    const scan = async (file: File) => {
        setScanning(true);
        setResult(null);
        setChosen(null);
        setLastFile(file);

        const body = new FormData();
        body.append('file', file);

        try {
            const response = await fetch('/incoming-invoices/scan', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body,
            });

            const json = (await response.json().catch(() => null)) as
                (ScanResult & { message?: string }) | null;

            if (!response.ok || !json) {
                toast.error(
                    json?.message ??
                        'Die Rechnung konnte nicht ausgelesen werden.',
                );

                return;
            }

            setResult(json);

            if (json.matches.length === 0 && !json.supplier_proposal.name) {
                toast.info(
                    'Kein Lieferant erkannt — bitte manuell wählen. Die Beträge wurden übernommen.',
                );
                onApply(json, null);
                setChosen('none');
            }
        } catch {
            toast.error('Keine Verbindung — der Scan braucht Netz.');
        } finally {
            setScanning(false);
        }
    };

    const applyExisting = (match: ScanMatch) => {
        if (!result) {
            return;
        }

        setChosen(`match-${match.id}`);
        onApply(result, {
            id: match.id,
            name: match.name,
            default_cost_type_id: match.default_cost_type_id,
        });
    };

    const createSupplier = async () => {
        if (!result || !result.supplier_proposal.name) {
            return;
        }

        setCreating(true);

        try {
            const response = await fetch('/incoming-invoices/scan/supplier', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify(result.supplier_proposal),
            });

            const json = (await response.json().catch(() => null)) as
                (ChosenSupplier & { message?: string }) | null;

            if (!response.ok || !json) {
                toast.error(
                    json?.message ??
                        'Der Lieferant konnte nicht angelegt werden.',
                );

                return;
            }

            toast.success(`Lieferant „${json.name}" angelegt.`);
            setChosen('new');
            onApply(result, json);
        } catch {
            toast.error('Keine Verbindung — bitte erneut versuchen.');
        } finally {
            setCreating(false);
        }
    };

    // Datei zusätzlich lokal ablegen — Speicherort wählt der
    // „Speichern unter"-Dialog (Fallback: Download-Ordner).
    const saveLocally = async () => {
        if (!lastFile || !result) {
            return;
        }

        const name = suggestedFileName(
            [
                result.prefill.invoice_date,
                result.extraction.supplier_name,
                result.prefill.supplier_invoice_no,
            ],
            lastFile.name,
        );

        const outcome = await saveFileAs(lastFile, name);

        if (outcome === 'saved') {
            toast.success(`Lokal gespeichert: ${name}`);
        } else if (outcome === 'download') {
            toast.info(
                'Der Browser bietet keinen Speichern-unter-Dialog — die Datei liegt im Download-Ordner.',
            );
        }
    };

    const proposal = result?.supplier_proposal;
    const conditions = proposal
        ? [
              `Zahlungsziel ${proposal.payment_target_days} Tage`,
              proposal.skonto_percent !== null && proposal.skonto_days !== null
                  ? `${proposal.skonto_percent} % Skonto binnen ${proposal.skonto_days} Tagen`
                  : null,
          ].filter(Boolean)
        : [];

    return (
        <div className="max-w-xl rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <ScanText className="size-5 text-muted-foreground" />
                    <div>
                        <p className="text-sm font-medium">
                            Rechnung automatisch auslesen
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {imagesEnabled
                                ? 'PDF oder Foto hochladen — Lieferant, Konditionen und Beträge werden erkannt'
                                : 'PDF hochladen — E-Rechnung und Rechnungstext werden direkt gelesen (Fotos erst mit KI-Schlüssel)'}
                        </p>
                    </div>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    disabled={scanning}
                    onClick={() => fileInput.current?.click()}
                >
                    {scanning ? (
                        <Spinner className="size-4" />
                    ) : (
                        <FileUp className="size-4" />
                    )}
                    {scanning ? 'Wird ausgelesen …' : 'Datei wählen'}
                </Button>
                <input
                    ref={fileInput}
                    type="file"
                    accept={
                        imagesEnabled
                            ? 'application/pdf,image/jpeg,image/png,image/webp'
                            : 'application/pdf'
                    }
                    className="hidden"
                    aria-label="Rechnung für Scan wählen"
                    onChange={(event) => {
                        const file = event.target.files?.[0];

                        if (file) {
                            void scan(file);
                        }

                        event.target.value = '';
                    }}
                />
            </div>

            {result && (
                <div className="mt-4 space-y-3 border-t border-sidebar-border/70 pt-3 dark:border-sidebar-border">
                    <div className="flex items-start justify-between gap-2">
                        <div>
                            <p className="text-sm">
                                Erkannter Lieferant:{' '}
                                <span className="font-medium">
                                    {proposal?.name ?? 'nicht erkennbar'}
                                </span>
                            </p>
                            {conditions.length > 0 && (
                                <p className="text-xs text-muted-foreground">
                                    {conditions.join(' · ')}
                                </p>
                            )}
                        </div>
                        <Badge variant="outline">
                            {SOURCE_LABELS[result.source]}
                        </Badge>
                    </div>

                    {result.matches.length > 0 && (
                        <div className="space-y-2">
                            <p className="text-xs font-medium text-muted-foreground">
                                Passt einer dieser bestehenden Lieferanten?
                            </p>
                            {result.matches.map((match) => (
                                <div
                                    key={match.id}
                                    className="flex items-center justify-between gap-2"
                                >
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm">
                                            {match.name}
                                        </span>
                                        <Badge variant="secondary">
                                            {Math.round(match.similarity * 100)}{' '}
                                            %
                                        </Badge>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant={
                                            chosen === `match-${match.id}`
                                                ? 'default'
                                                : 'outline'
                                        }
                                        onClick={() => applyExisting(match)}
                                    >
                                        {chosen === `match-${match.id}`
                                            ? 'Übernommen'
                                            : 'Übernehmen'}
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}

                    {proposal?.name && (
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-xs text-muted-foreground">
                                {result.matches.length > 0
                                    ? 'Keiner davon? Dann neu anlegen:'
                                    : 'Kein bestehender Lieferant gefunden:'}
                            </p>
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    chosen === 'new' ? 'default' : 'outline'
                                }
                                disabled={creating || chosen === 'new'}
                                onClick={() => void createSupplier()}
                            >
                                {creating ? (
                                    <Spinner className="size-4" />
                                ) : (
                                    <UserPlus className="size-4" />
                                )}
                                {chosen === 'new'
                                    ? 'Angelegt'
                                    : `„${proposal.name}" neu anlegen`}
                            </Button>
                        </div>
                    )}

                    <div className="flex items-center justify-between gap-2">
                        <p className="text-xs text-muted-foreground">
                            Die Datei zusätzlich am eigenen Rechner ablegen —
                            Speicherort frei wählbar:
                        </p>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => void saveLocally()}
                        >
                            <FolderDown className="size-4" />
                            Lokal ablegen …
                        </Button>
                    </div>

                    {chosen !== null && (
                        <p className="text-xs text-muted-foreground">
                            Das Formular unten wurde ausgefüllt — bitte prüfen
                            und speichern. Die Datei wird beim Speichern als
                            Beleg angehängt.
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

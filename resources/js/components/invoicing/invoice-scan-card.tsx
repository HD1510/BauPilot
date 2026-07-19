import { FileUp, FolderDown, ScanText, UserPlus } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { xsrfToken } from '@/lib/offline-queue';
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

/**
 * Vorbefüllung je Belegart — die Schlüssel entsprechen den
 * Formularfeldern (InvoiceScanController::prefill), alle optional.
 */
export type ScanPrefill = Partial<{
    // Eingangsrechnung
    supplier_invoice_no: string | null;
    payment_due_on: string | null;
    skonto_amount: string | null;
    skonto_until: string | null;
    // Ausgangsrechnung
    number: string | null;
    due_on: string | null;
    // Angebot
    offer_number: string | null;
    offer_amount_net: string | null;
    description: string | null;
    // Gemeinsam (Rechnungen)
    invoice_date: string | null;
    amount_mode: 'net' | 'gross';
    amount: string | null;
    vat_rate: string | null;
    reverse_charge: boolean;
    subject: string | null;
}>;

/**
 * Prefill-Wert als nichtleerer String — sonst undefined, damit die
 * Formular-Defaults greifen.
 */
export const prefillString = (
    value: string | boolean | null | undefined,
): string | undefined =>
    typeof value === 'string' && value !== '' ? value : undefined;

export type PartnerProposal = {
    name: string | null;
    payment_target_days: number;
    skonto_percent?: number | null;
    skonto_days?: number | null;
    vat_id?: string | null;
    notes?: string | null;
};

export type ScanResult = {
    scan_token: string;
    source: 'e_rechnung' | 'text' | 'ki';
    extraction: {
        partner_name: string | null;
        payment_target_days: number | null;
        skonto_percent: number | null;
        skonto_days: number | null;
        doc_number: string | null;
        doc_date: string | null;
    };
    matches: ScanMatch[];
    partner_proposal: PartnerProposal;
    prefill: ScanPrefill;
};

export type ChosenPartner = {
    id: number;
    name: string;
    default_cost_type_id: number | null;
};

const SOURCE_LABELS: Record<ScanResult['source'], string> = {
    e_rechnung: 'aus E-Rechnung (ZUGFeRD) — exakt',
    text: 'aus Textanalyse',
    ki: 'per KI',
};

/**
 * Beleg-Scan: PDF/Foto hochladen, die Scan-Leiter (E-Rechnung →
 * Textanalyse → KI) erkennt den Geschäftspartner (Lieferant oder Kunde)
 * samt Konditionen. Bestehende werden zur Auswahl vorgeschlagen; gibt
 * es keinen, wird einer mit den erkannten Daten angelegt. Die Auswahl
 * bestätigt immer ein Mensch. Die Datei lässt sich zusätzlich lokal
 * ablegen — der Speicherort ist frei wählbar.
 */
export function InvoiceScanCard({
    onApply,
    scanUrl,
    createPartnerUrl,
    partnerLabel,
    imagesEnabled,
}: {
    onApply: (result: ScanResult, partner: ChosenPartner | null) => void;
    scanUrl: string;
    createPartnerUrl: string;
    partnerLabel: 'Lieferant' | 'Kunde';
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
            const response = await fetch(scanUrl, {
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
                        'Der Beleg konnte nicht ausgelesen werden.',
                );

                return;
            }

            setResult(json);

            // Erkanntes SOFORT ins Formular übernehmen — der Partner
            // kann danach noch gewählt oder angelegt werden (füllt das
            // Formular dann erneut, diesmal samt Partner).
            onApply(json, null);

            if (json.matches.length === 0 && !json.partner_proposal.name) {
                toast.info(
                    `Kein ${partnerLabel} erkannt — bitte manuell wählen. Die übrigen Daten wurden übernommen.`,
                );
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

    const createPartner = async () => {
        if (!result || !result.partner_proposal.name) {
            return;
        }

        setCreating(true);

        try {
            const response = await fetch(createPartnerUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify(result.partner_proposal),
            });

            const json = (await response.json().catch(() => null)) as
                (ChosenPartner & { message?: string }) | null;

            if (!response.ok || !json) {
                toast.error(
                    json?.message ??
                        `Der ${partnerLabel} konnte nicht angelegt werden.`,
                );

                return;
            }

            toast.success(`${partnerLabel} „${json.name}" angelegt.`);
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
                result.extraction.doc_date,
                result.extraction.partner_name,
                result.extraction.doc_number,
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

    const proposal = result?.partner_proposal;
    const conditions = proposal
        ? [
              `Zahlungsziel ${proposal.payment_target_days} Tage`,
              proposal.skonto_percent != null && proposal.skonto_days != null
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
                            Beleg automatisch auslesen
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {imagesEnabled
                                ? `PDF oder Foto hochladen — ${partnerLabel}, Konditionen und Beträge werden erkannt`
                                : 'PDF hochladen — E-Rechnung und Belegtext werden direkt gelesen (Fotos erst mit KI-Schlüssel)'}
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
                    aria-label="Beleg für Scan wählen"
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
                                Erkannter {partnerLabel}:{' '}
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
                                Passt einer dieser bestehenden{' '}
                                {partnerLabel === 'Lieferant'
                                    ? 'Lieferanten'
                                    : 'Kunden'}
                                ?
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
                                    : `Kein bestehender ${partnerLabel} gefunden:`}
                            </p>
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    chosen === 'new' ? 'default' : 'outline'
                                }
                                disabled={creating || chosen === 'new'}
                                onClick={() => void createPartner()}
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

                    <p className="text-xs text-muted-foreground">
                        {chosen === null &&
                        (result.matches.length > 0 || proposal?.name)
                            ? `Die erkannten Daten stehen bereits im Formular — bitte noch den ${partnerLabel} übernehmen oder neu anlegen, dann prüfen und speichern.`
                            : 'Das Formular unten wurde ausgefüllt — bitte prüfen und speichern. Die Datei wird beim Speichern als Beleg angehängt.'}
                    </p>
                </div>
            )}
        </div>
    );
}

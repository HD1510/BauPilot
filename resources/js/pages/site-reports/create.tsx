import { Head, router } from '@inertiajs/react';
import { CloudOff, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { useEffect } from 'react';
import Heading from '@/components/heading';
import { SignaturePad } from '@/components/site-reports/signature-pad';
import { Badge } from '@/components/ui/badge';
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
import { submitOrQueue, subscribePending } from '@/lib/offline-queue';

type Props = {
    projects: { id: number; title: string }[];
    employees: { id: number; name: string }[];
    companyId: number;
};

type EntryRow = { employee_id: string; hours: string };

/**
 * Regiebericht erfassen (M9) — funktioniert auch im Funkloch: der
 * fertige Bericht (samt Unterschrift) geht als EIN Request an
 * /api/site-reports; ohne Netz wandert er sichtbar in die Warteschlange
 * und synchronisiert bei Verbindung nach.
 */
export default function SiteReportsCreate({ projects, employees, companyId }: Props) {
    const [projectId, setProjectId] = useState('');
    const [reportDate, setReportDate] = useState(new Date().toISOString().slice(0, 10));
    const [bodyText, setBodyText] = useState('');
    const [materialText, setMaterialText] = useState('');
    const [entries, setEntries] = useState<EntryRow[]>([{ employee_id: '', hours: '' }]);
    const [signature, setSignature] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [pending, setPending] = useState(0);

    useEffect(() => subscribePending(setPending), []);

    const submit = async () => {
        setSubmitting(true);

        const payload = {
            company_id: companyId,
            project_id: Number(projectId),
            report_date: reportDate,
            body_text: bodyText || null,
            material_text: materialText || null,
            entries: entries
                .filter((entry) => entry.employee_id && entry.hours)
                .map((entry) => ({
                    employee_id: Number(entry.employee_id),
                    hours: Number(entry.hours),
                })),
            signature,
            client_uuid: crypto.randomUUID(),
        };

        const result = await submitOrQueue(
            '/api/site-reports',
            payload,
            `Regiebericht vom ${reportDate}`,
        );

        setSubmitting(false);

        if (result.queued) {
            // Offline: auf der Seite bleiben (Navigation bräuchte Netz),
            // Formular leeren — der Zähler oben zeigt die Warteschlange.
            toast.info('Kein Netz — der Bericht wartet in der Warteschlange und synchronisiert automatisch nach.');
            setBodyText('');
            setMaterialText('');
            setEntries([{ employee_id: '', hours: '' }]);
            setSignature(null);

            return;
        }

        if (result.ok) {
            const body = result.body as { id: number; number: number } | null;
            toast.success(`Regiebericht Nr. ${body?.number ?? '?'} gespeichert.`);
            router.visit(body ? `/site-reports/${body.id}` : '/site-reports');

            return;
        }

        const message =
            (result.body as { message?: string } | null)?.message ??
            'Der Bericht wurde vom Server abgelehnt.';
        toast.error(message);
    };

    return (
        <>
            <Head title="Regiebericht erfassen" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Regiebericht erfassen"
                        description="Funktioniert auch ohne Netz — der Bericht synchronisiert nach"
                    />
                    {pending > 0 && (
                        <Badge variant="secondary" className="gap-1">
                            <CloudOff className="size-3.5" />
                            {pending} in Warteschlange
                        </Badge>
                    )}
                </div>

                <div className="grid max-w-xl gap-4">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid min-w-52 flex-1 gap-1">
                            <Label>Projekt</Label>
                            <Select value={projectId} onValueChange={setProjectId}>
                                <SelectTrigger aria-label="Projekt">
                                    <SelectValue placeholder="Wählen" />
                                </SelectTrigger>
                                <SelectContent>
                                    {projects.map((project) => (
                                        <SelectItem key={project.id} value={String(project.id)}>
                                            {project.title}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid w-40 gap-1">
                            <Label htmlFor="report-date">Datum</Label>
                            <Input
                                id="report-date"
                                type="date"
                                value={reportDate}
                                onChange={(e) => setReportDate(e.target.value)}
                                required
                            />
                        </div>
                    </div>

                    <div className="grid gap-1">
                        <Label htmlFor="report-body">Ausgeführte Arbeiten</Label>
                        <Textarea
                            id="report-body"
                            rows={4}
                            value={bodyText}
                            onChange={(e) => setBodyText(e.target.value)}
                            placeholder="Was wurde gemacht?"
                        />
                    </div>

                    <div className="grid gap-1">
                        <Label htmlFor="report-material">Material</Label>
                        <Textarea
                            id="report-material"
                            rows={2}
                            value={materialText}
                            onChange={(e) => setMaterialText(e.target.value)}
                            placeholder="Verbrauchtes Material"
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label>Stunden</Label>
                        {entries.map((entry, index) => (
                            <div key={index} className="flex items-center gap-2">
                                <Select
                                    value={entry.employee_id}
                                    onValueChange={(value) =>
                                        setEntries(entries.map((row, i) =>
                                            i === index ? { ...row, employee_id: value } : row))
                                    }
                                >
                                    <SelectTrigger className="flex-1" aria-label={`Mitarbeiter Zeile ${index + 1}`}>
                                        <SelectValue placeholder="Mitarbeiter" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {employees.map((employee) => (
                                            <SelectItem key={employee.id} value={String(employee.id)}>
                                                {employee.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Input
                                    type="number"
                                    step="0.25"
                                    min={0.25}
                                    max={24}
                                    className="w-24"
                                    placeholder="Std."
                                    aria-label={`Stunden Zeile ${index + 1}`}
                                    value={entry.hours}
                                    onChange={(e) =>
                                        setEntries(entries.map((row, i) =>
                                            i === index ? { ...row, hours: e.target.value } : row))
                                    }
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Zeile ${index + 1} entfernen`}
                                    onClick={() => setEntries(entries.filter((_, i) => i !== index))}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            className="w-fit"
                            onClick={() => setEntries([...entries, { employee_id: '', hours: '' }])}
                        >
                            <Plus className="size-4" />
                            Zeile
                        </Button>
                    </div>

                    <div className="grid gap-1">
                        <Label>Unterschrift (Bauherr / Auftraggeber)</Label>
                        <SignaturePad onChange={setSignature} />
                        <p className="text-xs text-muted-foreground">
                            Mit Unterschrift wird der Bericht abgeschlossen und gesperrt.
                            Ohne Unterschrift bleibt er als Entwurf offen.
                        </p>
                    </div>

                    <Button
                        onClick={() => void submit()}
                        disabled={submitting || !projectId || !reportDate}
                        className="w-fit"
                    >
                        {signature ? 'Unterschreiben & absenden' : 'Als Entwurf speichern'}
                    </Button>
                </div>
            </div>
        </>
    );
}

SiteReportsCreate.layout = {
    breadcrumbs: [
        { title: 'Regieberichte', href: '/site-reports' },
        { title: 'Erfassen', href: '/site-reports/create' },
    ],
};

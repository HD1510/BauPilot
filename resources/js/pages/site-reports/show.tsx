import { Head, router, useForm } from '@inertiajs/react';
import { Lock, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SignaturePad } from '@/components/site-reports/signature-pad';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { formatDate } from '@/lib/format';

type Props = {
    report: {
        id: number;
        number: number;
        report_date: string;
        project: string | null;
        status: string;
        status_label: string;
        body_text: string | null;
        material_text: string | null;
        signed_at: string | null;
        has_signature: boolean;
        entries: { employee: string | null; hours: number }[];
    };
    canSign: boolean;
    canDelete: boolean;
};

export default function SiteReportsShow({ report, canSign, canDelete }: Props) {
    const signed = report.status === 'signed';

    return (
        <>
            <Head title={`Regiebericht Nr. ${report.number}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={`Regiebericht Nr. ${report.number}`}
                        description={`${report.project ?? ''} · ${formatDate(report.report_date)}`}
                    />
                    <div className="flex items-center gap-2">
                        <Badge variant={signed ? 'default' : 'secondary'}>
                            {signed && <Lock className="size-3" />}
                            {report.status_label}
                        </Badge>
                        {canDelete && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.delete(`/site-reports/${report.id}`)
                                }
                            >
                                <Trash2 className="size-4" />
                                Entwurf löschen
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid max-w-xl gap-4">
                    <section>
                        <h3 className="mb-1 text-sm font-medium">Ausgeführte Arbeiten</h3>
                        <p className="text-sm whitespace-pre-line text-muted-foreground">
                            {report.body_text ?? '—'}
                        </p>
                    </section>
                    <section>
                        <h3 className="mb-1 text-sm font-medium">Material</h3>
                        <p className="text-sm whitespace-pre-line text-muted-foreground">
                            {report.material_text ?? '—'}
                        </p>
                    </section>
                    <section>
                        <h3 className="mb-1 text-sm font-medium">Stunden</h3>
                        <div className="grid gap-1">
                            {report.entries.map((entry, index) => (
                                <div key={index} className="flex justify-between text-sm">
                                    <span>{entry.employee}</span>
                                    <span>{entry.hours.toLocaleString('de-AT')} h</span>
                                </div>
                            ))}
                            {report.entries.length === 0 && (
                                <p className="text-sm text-muted-foreground">Keine Stunden erfasst.</p>
                            )}
                        </div>
                    </section>

                    <Separator />

                    {signed && (
                        <section>
                            <h3 className="mb-1 text-sm font-medium">
                                Unterschrieben{report.signed_at ? ` am ${formatDate(report.signed_at)}` : ''}
                            </h3>
                            {report.has_signature && (
                                <img
                                    src={`/site-reports/${report.id}/signature`}
                                    alt="Unterschrift"
                                    className="h-28 rounded-lg border border-sidebar-border/70 bg-white dark:border-sidebar-border"
                                />
                            )}
                            <p className="mt-1 text-xs text-muted-foreground">
                                Der Bericht ist gesperrt und kann nicht mehr geändert werden.
                            </p>
                        </section>
                    )}

                    {!signed && canSign && <SignForm reportId={report.id} />}
                </div>
            </div>
        </>
    );
}

function SignForm({ reportId }: { reportId: number }) {
    const [signature, setSignature] = useState<string | null>(null);
    const { setData, post, processing, errors } = useForm<{ signature: string }>({
        signature: '',
    });

    return (
        <form
            className="grid gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/site-reports/${reportId}/sign`, { preserveScroll: true });
            }}
        >
            <h3 className="text-sm font-medium">Jetzt unterschreiben</h3>
            <SignaturePad
                onChange={(dataUrl) => {
                    setSignature(dataUrl);
                    setData('signature', dataUrl ?? '');
                }}
            />
            <InputError message={errors.signature} />
            <Button type="submit" disabled={processing || !signature} className="w-fit">
                Unterschreiben & sperren
            </Button>
        </form>
    );
}

SiteReportsShow.layout = ({ report }: Props) => ({
    breadcrumbs: [
        { title: 'Regieberichte', href: '/site-reports' },
        { title: `Nr. ${report.number}`, href: `/site-reports/${report.id}` },
    ],
});

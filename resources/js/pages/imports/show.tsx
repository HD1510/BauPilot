import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { formatEUR } from '@/lib/format';

type AreaStats = {
    count: number;
    net: number;
    gross: number;
    open_count?: number;
    open_gross?: number;
    paragraph19_net: number;
};

type Finding = {
    id: number;
    type: string;
    message: string;
    payload: Record<string, unknown>;
    decision: string | null;
    needs_decision: boolean;
};

type Props = {
    run: {
        id: number;
        source_filename: string;
        status: string;
        stats: {
            outgoing?: AreaStats;
            incoming?: AreaStats;
            committed?: Record<string, number>;
        };
    };
    findings: Finding[];
    undecided: number;
};

const typeLabels: Record<string, string> = {
    duplicate: 'Dublette',
    estimate: 'Geschätztes Datum',
    warning: 'Warnung',
};

export default function ImportsShow({ run, findings, undecided }: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };

    return (
        <>
            <Head title={`Import: ${run.source_filename}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={run.source_filename}
                        description="Prüfbericht des Dry-Runs — übernommen wird erst nach Ihren Entscheidungen"
                    />
                    <Badge variant={run.status === 'committed' ? 'secondary' : 'outline'}>
                        {run.status === 'committed' ? 'Übernommen' : 'Dry-Run'}
                    </Badge>
                </div>

                <div className="grid max-w-3xl gap-3 md:grid-cols-2">
                    {run.stats.outgoing && (
                        <StatsCard title="Ausgangsrechnungen" stats={run.stats.outgoing} showOpen />
                    )}
                    {run.stats.incoming && (
                        <StatsCard title="Eingangsrechnungen" stats={run.stats.incoming} />
                    )}
                </div>

                <p className="max-w-3xl text-sm text-muted-foreground">
                    Soll/Ist-Abgleich: Diese Summen mit den bekannten Kontrollwerten der
                    Excel vergleichen (z. B. offene Rechnungen und Brutto-Summe), bevor
                    übernommen wird.
                </p>

                <Separator />

                <Heading
                    variant="small"
                    title={`Prüfbericht (${findings.length})`}
                    description="Dubletten brauchen eine Entscheidung; Schätzungen und Warnungen sind Hinweise"
                />

                <div className="grid max-w-3xl gap-2">
                    {findings.map((finding) => (
                        <div
                            key={finding.id}
                            className="flex flex-wrap items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
                        >
                            <Badge variant={finding.type === 'duplicate' ? 'destructive' : 'outline'}>
                                {typeLabels[finding.type] ?? finding.type}
                            </Badge>
                            <span className="min-w-64 flex-1">{finding.message}</span>
                            {finding.type === 'duplicate' &&
                                (finding.decision ? (
                                    <Badge variant="secondary">
                                        {finding.decision === 'use_existing'
                                            ? 'Bestehenden verwenden'
                                            : 'Neu anlegen'}
                                    </Badge>
                                ) : (
                                    <span className="flex gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.patch(
                                                    `/imports/${run.id}/findings/${finding.id}`,
                                                    { decision: 'use_existing' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Bestehenden verwenden
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.patch(
                                                    `/imports/${run.id}/findings/${finding.id}`,
                                                    { decision: 'create_new' },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Neu anlegen
                                        </Button>
                                    </span>
                                ))}
                        </div>
                    ))}
                    {findings.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Keine Auffälligkeiten. 🎉
                        </p>
                    )}
                </div>

                {run.status !== 'committed' && (
                    <>
                        <Separator />
                        <div className="flex max-w-3xl items-center gap-4">
                            <Button
                                disabled={undecided > 0}
                                onClick={() =>
                                    router.post(`/imports/${run.id}/commit`, {}, { preserveScroll: true })
                                }
                            >
                                <CheckCircle2 className="size-4" />
                                Übernehmen (Commit)
                            </Button>
                            {undecided > 0 && (
                                <span className="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400">
                                    <AlertTriangle className="size-4" />
                                    Noch {undecided} Dubletten zu entscheiden
                                </span>
                            )}
                            <InputError message={errors.commit} />
                        </div>
                    </>
                )}

                {run.stats.committed && (
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        Übernommen: {run.stats.committed.outgoing} Ausgangs- und{' '}
                        {run.stats.committed.incoming} Eingangsrechnungen,{' '}
                        {run.stats.committed.customers} Kunden,{' '}
                        {run.stats.committed.suppliers} Lieferanten,{' '}
                        {run.stats.committed.payments} Zahlungen (
                        {run.stats.committed.skipped} übersprungen).
                    </p>
                )}
            </div>
        </>
    );
}

function StatsCard({
    title,
    stats,
    showOpen = false,
}: {
    title: string;
    stats: AreaStats;
    showOpen?: boolean;
}) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 p-4 text-sm dark:border-sidebar-border">
            <div className="font-medium">{title}</div>
            <dl className="mt-2 grid grid-cols-2 gap-1 text-muted-foreground">
                <dt>Belege</dt>
                <dd className="text-right">{stats.count}</dd>
                <dt>Netto</dt>
                <dd className="text-right">{formatEUR(stats.net)}</dd>
                <dt>Brutto</dt>
                <dd className="text-right">{formatEUR(stats.gross)}</dd>
                {showOpen && (
                    <>
                        <dt>Offen</dt>
                        <dd className="text-right">
                            {stats.open_count} / {formatEUR(stats.open_gross ?? 0)}
                        </dd>
                    </>
                )}
                <dt>§19 netto</dt>
                <dd className="text-right">{formatEUR(stats.paragraph19_net)}</dd>
            </dl>
        </div>
    );
}

ImportsShow.layout = ({ run }: Props) => ({
    breadcrumbs: [
        { title: 'Excel-Import', href: '/imports' },
        { title: run.source_filename, href: `/imports/${run.id}` },
    ],
});

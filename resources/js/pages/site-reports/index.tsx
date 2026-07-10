import { Head, Link } from '@inertiajs/react';
import { CloudOff, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { subscribePending } from '@/lib/offline-queue';

type ReportRow = {
    id: number;
    number: number;
    report_date: string;
    project: string | null;
    status: string;
    status_label: string;
};

export default function SiteReportsIndex({ reports }: { reports: ReportRow[] }) {
    const [pending, setPending] = useState(0);

    useEffect(() => subscribePending(setPending), []);

    return (
        <>
            <Head title="Regieberichte" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Regieberichte"
                        description="Fortlaufend nummeriert — nach der Unterschrift gesperrt"
                    />
                    <Button asChild>
                        <Link href="/site-reports/create">
                            <Plus className="size-4" />
                            Erfassen
                        </Link>
                    </Button>
                </div>

                {pending > 0 && (
                    <div className="flex max-w-3xl items-center gap-2 rounded-lg border border-amber-300/60 bg-amber-50/60 p-3 text-sm dark:border-amber-900 dark:bg-amber-950/30">
                        <CloudOff className="size-4" />
                        {pending === 1
                            ? 'Ein Bericht wartet auf Verbindung und synchronisiert automatisch nach.'
                            : `${pending} Erfassungen warten auf Verbindung und synchronisieren automatisch nach.`}
                    </div>
                )}

                <div className="grid max-w-3xl gap-2">
                    {reports.map((report) => (
                        <Link
                            key={report.id}
                            href={`/site-reports/${report.id}`}
                            className="flex items-center gap-4 rounded-lg border border-sidebar-border/70 p-3 text-sm hover:bg-accent/50 dark:border-sidebar-border"
                        >
                            <span className="font-medium">Nr. {report.number}</span>
                            <span>{formatDate(report.report_date)}</span>
                            <span className="flex-1 text-muted-foreground">{report.project}</span>
                            <Badge variant={report.status === 'signed' ? 'default' : 'secondary'}>
                                {report.status_label}
                            </Badge>
                        </Link>
                    ))}
                    {reports.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Noch keine Regieberichte.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

SiteReportsIndex.layout = {
    breadcrumbs: [{ title: 'Regieberichte', href: '/site-reports' }],
};

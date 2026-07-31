import { Head, Link, useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useRef } from 'react';
import { assignToInput, FileDropZone } from '@/components/file-drop-zone';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';

type RunRow = {
    id: number;
    source_filename: string;
    status: string;
    created_at: string | null;
    stats: {
        outgoing?: { count: number; open_count: number };
        incoming?: { count: number };
    };
};

export default function ImportsIndex({ runs }: { runs: RunRow[] }) {
    const fileInput = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, reset } = useForm<{
        file: File | null;
    }>({ file: null });

    return (
        <>
            <Head title="Excel-Import" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Excel-Import"
                    description="Zweiphasig: Dry-Run mit Prüfbericht, dann Übernahme — erneute Läufe derselben Datei legen nichts doppelt an"
                />

                <FileDropZone
                    disabled={processing}
                    onFiles={(files) => {
                        assignToInput(fileInput.current, files.slice(0, 1));
                        setData('file', files[0]);
                    }}
                    className="max-w-xl"
                >
                    <form
                        className="flex items-end gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            post('/imports', {
                                forceFormData: true,
                                onSuccess: () => reset(),
                            });
                        }}
                    >
                        <div className="grid flex-1 gap-2">
                            <Label htmlFor="import-file">
                                Übersicht (.xlsx) hochladen
                            </Label>
                            <Input
                                id="import-file"
                                ref={fileInput}
                                type="file"
                                accept=".xlsx,.xls"
                                onChange={(event) =>
                                    setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                                required
                            />
                            <InputError message={errors.file} />
                        </div>
                        <Button
                            type="submit"
                            disabled={processing || !data.file}
                        >
                            <Upload className="size-4" />
                            Dry-Run starten
                        </Button>
                    </form>
                </FileDropZone>

                <div className="grid max-w-3xl gap-2">
                    {runs.map((run) => (
                        <Link
                            key={run.id}
                            href={`/imports/${run.id}`}
                            className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 text-sm hover:bg-accent/50 dark:border-sidebar-border"
                        >
                            <span className="font-medium">
                                {run.source_filename}
                            </span>
                            <span className="text-muted-foreground">
                                {run.created_at
                                    ? formatDate(run.created_at)
                                    : ''}
                            </span>
                            <span className="flex-1" />
                            {run.stats.outgoing && (
                                <span className="text-muted-foreground">
                                    {run.stats.outgoing.count} AR ·{' '}
                                    {run.stats.incoming?.count ?? 0} ER
                                </span>
                            )}
                            <Badge
                                variant={
                                    run.status === 'committed'
                                        ? 'secondary'
                                        : 'outline'
                                }
                            >
                                {run.status === 'committed'
                                    ? 'Übernommen'
                                    : 'Dry-Run'}
                            </Badge>
                        </Link>
                    ))}
                    {runs.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Noch keine Import-Läufe.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

ImportsIndex.layout = {
    breadcrumbs: [{ title: 'Excel-Import', href: '/imports' }],
};

import { Head, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useEffect } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { formatDate } from '@/lib/format';

type EntryRow = {
    id: number;
    work_date: string;
    hours: number;
    activity: string | null;
    project: string | null;
    employee: string | null;
    can_delete: boolean;
};

type Props = {
    entries: EntryRow[];
    projects: { id: number; title: string }[];
    employees: { id: number; name: string; is_me: boolean }[];
};

/**
 * Zeiterfassung (M8): mobile Schnellerfassung — Datum steht auf heute,
 * der eigene Mitarbeiter ist vorausgewählt; Projekt, Stunden, fertig.
 */
export default function TimeEntriesIndex({ entries, projects, employees }: Props) {
    const me = employees.find((employee) => employee.is_me);

    const { data, setData, post, processing, errors, reset } = useForm({
        work_date: new Date().toISOString().slice(0, 10),
        employee_id: me ? String(me.id) : '',
        project_id: '',
        hours: '',
        activity: '',
    });

    // Nach dem Speichern bleiben Datum/Mitarbeiter/Projekt stehen —
    // typisch werden mehrere Einträge hintereinander erfasst.
    useEffect(() => {
        if (!data.employee_id && me) {
            setData('employee_id', String(me.id));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <>
            <Head title="Zeiten" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Zeiterfassung"
                    description="Stunden je Projekt — bewertet wird erst in den Projektzahlen"
                />

                <form
                    className="grid max-w-xl gap-3 rounded-lg border border-dashed border-sidebar-border/70 p-4 dark:border-sidebar-border"
                    onSubmit={(event) => {
                        event.preventDefault();
                        post('/time-entries', {
                            preserveScroll: true,
                            onSuccess: () => reset('hours', 'activity'),
                        });
                    }}
                >
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid w-40 gap-1">
                            <Label htmlFor="time-date">Datum</Label>
                            <Input
                                id="time-date"
                                type="date"
                                value={data.work_date}
                                onChange={(e) => setData('work_date', e.target.value)}
                                required
                            />
                        </div>
                        <div className="grid min-w-44 flex-1 gap-1">
                            <Label>Mitarbeiter</Label>
                            <Select
                                value={data.employee_id}
                                onValueChange={(value) => setData('employee_id', value)}
                            >
                                <SelectTrigger aria-label="Mitarbeiter">
                                    <SelectValue placeholder="Wählen" />
                                </SelectTrigger>
                                <SelectContent>
                                    {employees.map((employee) => (
                                        <SelectItem key={employee.id} value={String(employee.id)}>
                                            {employee.name}
                                            {employee.is_me ? ' (ich)' : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid min-w-44 flex-1 gap-1">
                            <Label>Projekt</Label>
                            <Select
                                value={data.project_id}
                                onValueChange={(value) => setData('project_id', value)}
                            >
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
                    </div>
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid w-28 gap-1">
                            <Label htmlFor="time-hours">Stunden</Label>
                            <Input
                                id="time-hours"
                                type="number"
                                step="0.25"
                                min={0.25}
                                max={24}
                                value={data.hours}
                                onChange={(e) => setData('hours', e.target.value)}
                                required
                            />
                        </div>
                        <div className="grid flex-1 gap-1">
                            <Label htmlFor="time-activity">Tätigkeit (optional)</Label>
                            <Input
                                id="time-activity"
                                value={data.activity}
                                onChange={(e) => setData('activity', e.target.value)}
                                placeholder="z. B. Verputzarbeiten EG"
                            />
                        </div>
                        <Button
                            type="submit"
                            disabled={processing || !data.project_id || !data.employee_id || !data.hours}
                        >
                            Erfassen
                        </Button>
                    </div>
                    <InputError
                        message={
                            errors.hours ??
                            errors.project_id ??
                            errors.employee_id ??
                            errors.work_date
                        }
                    />
                </form>

                <Heading variant="small" title="Letzte Einträge" description="" />
                <div className="grid max-w-3xl gap-2">
                    {entries.map((entry) => (
                        <div
                            key={entry.id}
                            className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border"
                        >
                            <Badge variant="outline">{formatDate(entry.work_date)}</Badge>
                            <span className="font-medium">
                                {entry.hours.toLocaleString('de-AT')} h
                            </span>
                            <span className="flex-1">
                                {entry.project}
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {entry.employee}
                                    {entry.activity && ` · ${entry.activity}`}
                                </span>
                            </span>
                            {entry.can_delete && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Eintrag löschen"
                                    onClick={() =>
                                        router.delete(`/time-entries/${entry.id}`, {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            )}
                        </div>
                    ))}
                    {entries.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Noch keine Einträge.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

TimeEntriesIndex.layout = {
    breadcrumbs: [{ title: 'Zeiten', href: '/time-entries' }],
};

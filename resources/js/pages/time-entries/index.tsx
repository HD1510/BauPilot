import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarRange,
    Check,
    ChevronLeft,
    ChevronRight,
    Pencil,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { confirmDelete } from '@/lib/confirm-delete';
import { formatDate } from '@/lib/format';

type EntryRow = {
    id: number;
    work_date: string;
    hours: number;
    activity: string | null;
    project: string | null;
    employee: string | null;
    can_edit: boolean;
};

type PlanRow = {
    assignment_id: number;
    project_id: number;
    project: string;
    employees: { id: number; name: string; skip: string | null }[];
};

type Week = {
    monday: string;
    previous: string;
    next: string;
    days: string[];
    rows: { employee: string; hours: number[]; total: number }[];
    total: number;
};

type Props = {
    entries: EntryRow[];
    projects: { id: number; title: string }[];
    employees: { id: number; name: string; is_me: boolean }[];
    date: string;
    plan: PlanRow[];
    week: Week;
};

const hoursLabel = (hours: number) =>
    hours === 0 ? '–' : hours.toLocaleString('de-AT');

const weekdayShort = (date: string) =>
    new Date(`${date}T00:00:00`).toLocaleDateString('de-AT', {
        weekday: 'short',
        day: 'numeric',
        month: 'numeric',
    });

/**
 * Zeiterfassung (M8): Datum steht auf heute, der eigene Mitarbeiter ist
 * vorausgewählt. Eine Partie lässt sich in einem Zug erfassen oder direkt
 * aus der Einteilung des Tages übernehmen.
 */
export default function TimeEntriesIndex({
    entries,
    projects,
    employees,
    date,
    plan,
    week,
}: Props) {
    const me = employees.find((employee) => employee.is_me);

    const { data, setData, post, processing, errors, reset } = useForm<{
        work_date: string;
        employee_ids: number[];
        project_id: string;
        hours: string;
        activity: string;
    }>({
        work_date: date,
        employee_ids: me ? [me.id] : [],
        project_id: '',
        hours: '',
        activity: '',
    });

    // Einteilung und Wochenübersicht folgen dem gewählten Datum.
    const showDate = (next: string) => {
        setData('work_date', next);

        if (next) {
            router.get(
                '/time-entries',
                { date: next },
                {
                    only: ['date', 'plan', 'week'],
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }
    };

    const toggleEmployee = (id: number) =>
        setData(
            'employee_ids',
            data.employee_ids.includes(id)
                ? data.employee_ids.filter((value) => value !== id)
                : [...data.employee_ids, id],
        );

    return (
        <>
            <Head title="Zeiten" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Zeiterfassung"
                    description="Stunden je Projekt — für einen oder gleich mehrere Mitarbeiter"
                />

                <div className="grid items-start gap-4 xl:grid-cols-2">
                    <form
                        className="grid gap-3 rounded-lg border border-dashed border-sidebar-border/70 p-4 dark:border-sidebar-border"
                        onSubmit={(event) => {
                            event.preventDefault();
                            post('/time-entries', {
                                preserveScroll: true,
                                // Datum, Projekt und Mitarbeiter bleiben
                                // stehen — meist folgt gleich der nächste
                                // Eintrag.
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
                                    onChange={(e) => showDate(e.target.value)}
                                    required
                                />
                            </div>
                            <div className="grid min-w-44 flex-1 gap-1">
                                <Label>Projekt</Label>
                                <Select
                                    value={data.project_id}
                                    onValueChange={(value) =>
                                        setData('project_id', value)
                                    }
                                >
                                    <SelectTrigger aria-label="Projekt">
                                        <SelectValue placeholder="Wählen" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {projects.map((project) => (
                                            <SelectItem
                                                key={project.id}
                                                value={String(project.id)}
                                            >
                                                {project.title}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid gap-1">
                            <Label>Mitarbeiter</Label>
                            <div className="flex flex-wrap gap-x-4 gap-y-2">
                                {employees.map((employee) => (
                                    <Label
                                        key={employee.id}
                                        className="flex items-center gap-2 text-sm font-normal"
                                    >
                                        <Checkbox
                                            checked={data.employee_ids.includes(
                                                employee.id,
                                            )}
                                            onCheckedChange={() =>
                                                toggleEmployee(employee.id)
                                            }
                                        />
                                        {employee.name}
                                        {employee.is_me ? ' (ich)' : ''}
                                    </Label>
                                ))}
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
                                    onChange={(e) =>
                                        setData('hours', e.target.value)
                                    }
                                    required
                                />
                            </div>
                            <div className="grid min-w-40 flex-1 gap-1">
                                <Label htmlFor="time-activity">
                                    Tätigkeit (optional)
                                </Label>
                                <Input
                                    id="time-activity"
                                    value={data.activity}
                                    onChange={(e) =>
                                        setData('activity', e.target.value)
                                    }
                                    placeholder="z. B. Verputzarbeiten EG"
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={
                                    processing ||
                                    !data.project_id ||
                                    data.employee_ids.length === 0 ||
                                    !data.hours
                                }
                            >
                                {data.employee_ids.length > 1
                                    ? `Für ${data.employee_ids.length} erfassen`
                                    : 'Erfassen'}
                            </Button>
                        </div>
                        <InputError
                            message={
                                errors.hours ??
                                errors.project_id ??
                                errors.employee_ids ??
                                errors.work_date
                            }
                        />
                    </form>

                    <PlanCard key={date} date={date} plan={plan} />
                </div>

                <WeekTable week={week} onWeek={showDate} />

                <Heading
                    variant="small"
                    title="Letzte Einträge"
                    description=""
                />
                <div className="grid max-w-3xl gap-2">
                    {entries.map((entry) => (
                        <EntryLine key={entry.id} entry={entry} />
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

/**
 * Übernahme aus der Einteilung: zeigt, wer an dem Tag auf welchem
 * Projekt eingeteilt ist, und legt die Zeiten mit einem Klick an.
 */
function PlanCard({ date, plan }: { date: string; plan: PlanRow[] }) {
    const [hours, setHours] = useState('8');
    const [processing, setProcessing] = useState(false);

    const open = plan.reduce(
        (sum, row) => sum + row.employees.filter((e) => e.skip === null).length,
        0,
    );

    return (
        <div className="grid gap-3 rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border">
            <div className="flex items-center gap-2 text-sm font-medium">
                <CalendarRange className="size-4" />
                Aus der Einteilung vom {formatDate(date)}
            </div>

            {plan.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Für diesen Tag ist niemand auf ein Projekt eingeteilt.
                </p>
            )}

            {plan.map((row) => (
                <div key={row.assignment_id} className="text-sm">
                    <span className="font-medium">{row.project}</span>
                    <div className="mt-1 flex flex-wrap gap-1">
                        {row.employees.map((employee) => (
                            <Badge
                                key={employee.id}
                                variant="secondary"
                                className={
                                    employee.skip ? 'opacity-50' : undefined
                                }
                            >
                                {employee.name}
                                {employee.skip && ` (${employee.skip})`}
                            </Badge>
                        ))}
                        {row.employees.length === 0 && (
                            <span className="text-xs text-muted-foreground">
                                niemand eingeteilt
                            </span>
                        )}
                    </div>
                </div>
            ))}

            {plan.length > 0 && (
                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid w-28 gap-1">
                        <Label htmlFor="plan-hours">Stunden je Person</Label>
                        <Input
                            id="plan-hours"
                            type="number"
                            step="0.25"
                            min={0.25}
                            max={24}
                            value={hours}
                            onChange={(e) => setHours(e.target.value)}
                        />
                    </div>
                    <Button
                        variant="outline"
                        disabled={processing || open === 0 || !hours}
                        onClick={() =>
                            router.post(
                                '/time-entries/from-assignments',
                                { work_date: date, hours },
                                {
                                    preserveScroll: true,
                                    onStart: () => setProcessing(true),
                                    onFinish: () => setProcessing(false),
                                },
                            )
                        }
                    >
                        {open === 0
                            ? 'Alles schon erfasst'
                            : `${open} ${open === 1 ? 'Eintrag' : 'Einträge'} übernehmen`}
                    </Button>
                </div>
            )}
        </div>
    );
}

function WeekTable({
    week,
    onWeek,
}: {
    week: Week;
    onWeek: (date: string) => void;
}) {
    return (
        <div className="grid max-w-3xl gap-2">
            <div className="flex items-center justify-between gap-3">
                <Heading
                    variant="small"
                    title={`Woche ab ${formatDate(week.monday)}`}
                    description="Stunden je Mitarbeiter und Tag"
                />
                <div className="flex gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Vorige Woche"
                        onClick={() => onWeek(week.previous)}
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Nächste Woche"
                        onClick={() => onWeek(week.next)}
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>

            {week.rows.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    In dieser Woche sind noch keine Zeiten erfasst.
                </p>
            ) : (
                <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm tabular-nums">
                        <thead>
                            <tr className="border-b border-sidebar-border/70 text-xs text-muted-foreground dark:border-sidebar-border">
                                <th className="p-2 text-left font-medium">
                                    Mitarbeiter
                                </th>
                                {week.days.map((day) => (
                                    <th
                                        key={day}
                                        className="p-2 text-right font-medium whitespace-nowrap"
                                    >
                                        {weekdayShort(day)}
                                    </th>
                                ))}
                                <th className="p-2 text-right font-medium">
                                    Summe
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {week.rows.map((row) => (
                                <tr
                                    key={row.employee}
                                    className="border-b border-sidebar-border/70 last:border-0 dark:border-sidebar-border"
                                >
                                    <td className="p-2 whitespace-nowrap">
                                        {row.employee}
                                    </td>
                                    {row.hours.map((hours, index) => (
                                        <td
                                            key={week.days[index]}
                                            className="p-2 text-right text-muted-foreground"
                                        >
                                            {hoursLabel(hours)}
                                        </td>
                                    ))}
                                    <td className="p-2 text-right font-medium">
                                        {hoursLabel(row.total)} h
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="border-t border-sidebar-border/70 dark:border-sidebar-border">
                                <td
                                    className="p-2 font-medium"
                                    colSpan={week.days.length + 1}
                                >
                                    Gesamt
                                </td>
                                <td className="p-2 text-right font-medium">
                                    {hoursLabel(week.total)} h
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            )}
        </div>
    );
}

function EntryLine({ entry }: { entry: EntryRow }) {
    const [editing, setEditing] = useState(false);
    const { data, setData, patch, processing, errors } = useForm({
        hours: String(entry.hours),
        activity: entry.activity ?? '',
    });

    if (editing) {
        return (
            <form
                className="flex flex-wrap items-center gap-3 rounded-lg border border-primary p-3 text-sm"
                onSubmit={(event) => {
                    event.preventDefault();
                    patch(`/time-entries/${entry.id}`, {
                        preserveScroll: true,
                        onSuccess: () => setEditing(false),
                    });
                }}
            >
                <Badge variant="outline">{formatDate(entry.work_date)}</Badge>
                <Input
                    className="w-24"
                    type="number"
                    step="0.25"
                    min={0.25}
                    max={24}
                    value={data.hours}
                    onChange={(e) => setData('hours', e.target.value)}
                    aria-label="Stunden"
                    autoFocus
                    required
                />
                <Input
                    className="min-w-40 flex-1"
                    value={data.activity}
                    onChange={(e) => setData('activity', e.target.value)}
                    placeholder="Tätigkeit (optional)"
                    aria-label="Tätigkeit"
                />
                <Button
                    type="submit"
                    size="icon"
                    disabled={processing}
                    aria-label="Speichern"
                >
                    <Check className="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Abbrechen"
                    onClick={() => setEditing(false)}
                >
                    <X className="size-4" />
                </Button>
                <InputError message={errors.hours ?? errors.activity} />
            </form>
        );
    }

    return (
        <div className="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 text-sm dark:border-sidebar-border">
            <Badge variant="outline">{formatDate(entry.work_date)}</Badge>
            <span className="font-medium">
                {entry.hours.toLocaleString('de-AT')} h
            </span>
            <span className="min-w-0 flex-1">
                {entry.project}
                <span className="text-muted-foreground">
                    {' '}
                    · {entry.employee}
                    {entry.activity && ` · ${entry.activity}`}
                </span>
            </span>
            {entry.can_edit && (
                <span className="flex shrink-0">
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Eintrag bearbeiten"
                        onClick={() => setEditing(true)}
                    >
                        <Pencil className="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Eintrag löschen"
                        onClick={() =>
                            confirmDelete(
                                `/time-entries/${entry.id}`,
                                'Zeiteintrag wirklich löschen?',
                            )
                        }
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </span>
            )}
        </div>
    );
}

TimeEntriesIndex.layout = {
    breadcrumbs: [{ title: 'Zeiten', href: '/time-entries' }],
};
